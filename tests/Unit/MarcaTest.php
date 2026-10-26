<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Marca: lectura de app/Config/marca.php con valores neutros, escapado, impuesto y pie */
final class MarcaTest extends TestCase {
    protected function tearDown(): void {
        \Marca::usarParaPruebas(null);
        parent::tearDown();
    }

    public function testElArchivoRealTraeTodasLasClavesDeLaMarca(): void {
        $m = \Marca::todo();
        foreach (["nombre", "eslogan", "descripcion", "logo", "telefono", "correo", "direccion", "ciudad", "pais", "horario", "redes", "portada", "nosotros", "contacto", "impuesto", "pie"] as $k) {
            $this->assertArrayHasKey($k, $m, "falta $k en marca.php");
        }
        foreach (["nombre", "telefono", "correo", "direccion"] as $k) $this->assertNotSame("", trim((string)$m[$k]), $k);
        $this->assertIsArray($m["horario"]);
        $this->assertNotEmpty($m["nosotros"]["parrafos"]);
        $this->assertSame(0.07, (float)$m["impuesto"]["tasa"]);
    }

    public function testClavesAnidadasConPuntos(): void {
        \Marca::usarParaPruebas(["impuesto" => ["nombre" => "IVA", "tasa" => 0.16], "nombre" => "Taller Uno"]);
        $this->assertSame("IVA", \Marca::get("impuesto.nombre"));
        $this->assertSame(0.16, \Marca::get("impuesto.tasa"));
        $this->assertSame("Taller Uno", \Marca::get("nombre"));
    }

    public function testSiFaltaUnaClaveDevuelveElValorNeutroYNuncaFalla(): void {
        \Marca::usarParaPruebas([]);
        $this->assertSame("Mi tienda", \Marca::get("nombre"));
        $this->assertSame("", \Marca::get("telefono"));
        $this->assertSame([], \Marca::get("horario"));
        $this->assertSame(0.0, \Marca::impuestoTasa());
        $this->assertSame("Impuesto (0%)", \Marca::impuestoEtiqueta());
        // una clave que ni existe en los valores neutros usa el defecto que se pida
        $this->assertSame("x", \Marca::get("no.existe.nunca", "x"));
        $this->assertSame("", \Marca::get("no.existe.nunca"));
    }

    public function testElNeutroNoTraeLosDatosDeLaMarcaReal(): void {
        $real = (new \ReflectionClass(\Marca::class))->getConstant("DEFECTOS");
        $json = json_encode($real, JSON_UNESCAPED_UNICODE);
        foreach (["nombre", "telefono", "correo"] as $k) {
            $valor = (string)\Marca::todo()[$k];
            $this->assertStringNotContainsStringIgnoringCase($valor, $json, $k);
        }
    }

    public function testFaltaUnaClaveSeAnotaUnaSolaVezEnElRegistro(): void {
        $log = RASTRO_TEST_TMP . DIRECTORY_SEPARATOR . "marca_" . uniqid() . ".log";
        $previo = ini_set("error_log", $log);
        try {
            \Marca::usarParaPruebas([]);
            \Marca::get("telefono");
            \Marca::get("telefono");
            \Marca::get("correo");
        } finally {
            ini_set("error_log", $previo);
        }
        $texto = (string)@file_get_contents($log);
        $this->assertSame(1, substr_count($texto, 'falta la clave "telefono"'));
        $this->assertSame(1, substr_count($texto, 'falta la clave "correo"'));
        @unlink($log);
    }

    public function testHEscapaElTextoParaHtml(): void {
        \Marca::usarParaPruebas(["nombre" => "Pieza <b>\"A\" & Cía'</b>"]);
        $this->assertSame("Pieza &lt;b&gt;&quot;A&quot; &amp; Cía&#039;&lt;/b&gt;", \Marca::h("nombre"));
    }

    public function testUrlDeUnArchivoDeLaMarca(): void {
        \Marca::usarParaPruebas(["logo" => "assets/img/otro.png", "nosotros" => ["imagen" => "/assets/img/x.jpg"]]);
        $this->assertSame(APP_URL . "/assets/img/otro.png", \Marca::url("logo"));
        $this->assertSame(APP_URL . "/assets/img/x.jpg", \Marca::url("nosotros.imagen"));
        \Marca::usarParaPruebas(["logo" => ""]);
        $this->assertSame("", \Marca::url("logo"));
    }

    public function testElPorcentajeYLaEtiquetaDelImpuestoSalenDeLaTasa(): void {
        foreach ([[0.07, "7"], [0.125, "12.5"], [0.16, "16"], [0.0, "0"], [0.10, "10"]] as [$tasa, $pct]) {
            \Marca::usarParaPruebas(["impuesto" => ["nombre" => "IVA", "tasa" => $tasa]]);
            $this->assertSame($pct, \Marca::impuestoPorcentaje(), "tasa $tasa");
            $this->assertSame("IVA ($pct%)", \Marca::impuestoEtiqueta());
        }
    }

    public function testElImpuestoDeLasVentasUsaLaTasaDeLaMarca(): void {
        $this->assertSame(0.07, \Itbms::tasa());
        $this->assertEquals(["subtotal" => 100.0, "itbms" => 7.0, "total" => 107.0], \Itbms::calcular(100.0));
        \Marca::usarParaPruebas(["impuesto" => ["nombre" => "IVA", "tasa" => 0.12]]);
        $this->assertSame(0.12, \Itbms::tasa());
        $this->assertEquals(["subtotal" => 100.0, "itbms" => 12.0, "total" => 112.0], \Itbms::calcular(100.0));
    }

    public function testElPieSustituyeAnioNombreYPais(): void {
        \Marca::usarParaPruebas(["nombre" => "Taller Uno", "pais" => "Chile", "pie" => "(c) {anio} {nombre} / {pais}"]);
        $this->assertSame("(c) " . date("Y") . " Taller Uno / Chile", \Marca::pie());
    }

    public function testElNombreDeLaMarcaNoValeComoContrasena(): void {
        $acepta = fn(string $clave) => (new \Validator())->password("p", $clave, "ana@example.invalid", "Ana")->isValid();
        \Marca::usarParaPruebas(["nombre" => "Taller Cambiado Zeta"]);
        foreach (["tallercambiadozeta", "Taller Cambiado Zeta", "tallercambiadozeta2026", "2026TallerCambiadoZeta"] as $clave) {
            $this->assertFalse($acepta($clave), $clave);
        }
        $this->assertTrue($acepta("frase larga con otro tema 7391"));
        \Marca::usarParaPruebas(["nombre" => "Otra Tienda Distinta"]);
        $this->assertTrue($acepta("tallercambiadozeta2026"), "ya no es el nombre de la marca");
    }

    public function testWhatsappEsLaEntradaDeRedesConEseIconoYUnaDireccionHttp(): void {
        \Marca::usarParaPruebas(["redes" => [["nombre" => "Instagram", "url" => "https://instagram.com/x", "icono" => "instagram"], ["nombre" => "WhatsApp", "url" => "https://wa.me/50760000000", "icono" => "whatsapp"]]]);
        $this->assertSame("https://wa.me/50760000000", \Marca::whatsapp());
    }

    public function testWhatsappVacioSiNoHayEntradaDireccionVaciaOEsquemaPeligroso(): void {
        foreach ([[], [["nombre" => "WhatsApp", "url" => "", "icono" => "whatsapp"]], [["nombre" => "WhatsApp", "url" => "javascript:alert(1)", "icono" => "whatsapp"]],
                  [["nombre" => "WhatsApp", "url" => "ftp://x", "icono" => "whatsapp"]], [["nombre" => "Otra", "url" => "https://wa.me/1", "icono" => "chat"]]] as $redes) {
            \Marca::usarParaPruebas(["redes" => $redes]);
            $this->assertSame("", \Marca::whatsapp(), json_encode($redes));
        }
    }

    public function testUrlSiExisteSoloDevuelveArchivosQueEstanEnDisco(): void {
        \Marca::usarParaPruebas(["logo" => "assets/img/logo.svg", "nosotros" => ["imagen" => "assets/img/no-esta.jpg"], "x" => ["a" => "assets/../README.md", "b" => "/assets/img/logo.svg", "c" => ""]]);
        $this->assertSame(APP_URL . "/assets/img/logo.svg", \Marca::urlSiExiste("logo"));
        $this->assertSame(APP_URL . "/assets/img/logo.svg", \Marca::urlSiExiste("x.b"), "con barra inicial también");
        $this->assertSame("", \Marca::urlSiExiste("nosotros.imagen"));
        $this->assertSame("", \Marca::urlSiExiste("x.a"), "sin subir de carpeta");
        $this->assertSame("", \Marca::urlSiExiste("x.c"));
        $this->assertSame("", \Marca::urlSiExiste("no.existe.la.clave"));
    }

    public function testLasInsigniasSinConfirmarEstanOcultasEnElArchivoReal(): void {
        \Marca::usarParaPruebas(null);
        $this->assertSame(false, \Marca::get("nosotros.destacados_visibles", true));
    }
}
