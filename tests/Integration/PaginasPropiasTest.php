<?php
namespace Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BaseDatos;
use Tests\Support\Html;
use Tests\Support\Pantallas;
use Tests\Support\ServidorTemporal;

/**
 * Quiénes somos y Contacto son páginas con su propia URL, vista y opción del menú; la portada queda corta
 * y no sobrevive ninguna ancla vieja ni un enlace a un destino que no existe.
 */
final class PaginasPropiasTest extends BaseDatos {
    private ?ServidorTemporal $servidor = null;

    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
    }

    protected function tearDown(): void {
        if ($this->servidor) $this->servidor->detener();
        \Marca::usarParaPruebas(null);
        parent::tearDown();
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function paginas(): array {
        return ["nosotros" => ["nosotros", "Quiénes somos"], "contacto" => ["contacto", "Contacto"]];
    }

    #[DataProvider("paginas")]
    public function testCadaPaginaRespondeConCodigo200EnUnServidorReal(string $accion, string $opcion): void {
        $this->servidor = new ServidorTemporal();
        $this->servidor->iniciar();
        $r = $this->servidor->get("/index.php?action=$accion");
        $this->assertSame(200, $r["codigo"], $accion);
        $x = Html::xpath($r["cuerpo"]);
        $this->assertSame(1, Html::contar($x, "//main"), "$accion: un <main>");
        $this->assertSame(1, Html::contar($x, "//h1"), "$accion: un h1");
        $this->assertStringContainsString($opcion, trim($x->query("//title")->item(0)->textContent), $accion);
        $this->assertSame($opcion, trim($x->query("//*[@id='navP']//a[@aria-current='page']")->item(0)->textContent), "$accion: opción activa");
    }

    public function testLaPortadaYElCatalogoSiguenRespondiendoEnElServidorReal(): void {
        $this->servidor = new ServidorTemporal();
        $this->servidor->iniciar();
        foreach (["/index.php", "/index.php?ver=catalogo"] as $ruta) $this->assertSame(200, $this->servidor->get($ruta)["codigo"], $ruta);
    }

    #[DataProvider("paginas")]
    public function testCadaPaginaTieneTituloPropioUnH1YElMain(string $accion, string $opcion): void {
        $x = Html::xpath(Pantallas::render($this->db, $accion));
        $this->assertSame(1, Html::contar($x, "//main"));
        $this->assertSame(1, Html::contar($x, "//h1"));
        $titulo = trim($x->query("//title")->item(0)->textContent);
        $this->assertSame($opcion . " — " . \Marca::get("nombre"), $titulo, "el título nombra la página y la marca");
    }

    public function testLaPortadaQuedaCorta(): void {
        $x = Html::xpath(Pantallas::render($this->db, "home"));
        $this->assertSame(["inicio", "secciones", ""], array_map(fn($s) => $s->getAttribute("id"), iterator_to_array($x->query("//main//section"))), "hero, secciones y destacadas");
        $this->assertSame(0, Html::contar($x, "//*[@id='nosotros' or @id='contacto']"));
        $this->assertSame(0, Html::contar($x, "//*[contains(@class,'about-section')]"));
        $plano = strtoupper(strip_tags(Pantallas::render($this->db, "home")));
        $this->assertStringNotContainsString("QUIÉNES SOMOS", $plano, "el texto de Quiénes somos vive en su página");
    }

    /** @return array<string,array{0:string}> */
    public static function todas(): array {
        $o = [];
        foreach (Pantallas::nombres() as $n) $o[$n] = [$n];
        return $o;
    }

    #[DataProvider("todas")]
    public function testNoQuedanEnlacesAAncladasMuertasNiAnclasViejas(string $pantalla): void {
        $x = Html::xpath(Pantallas::render($this->db, $pantalla));
        foreach (["nosotros", "contacto"] as $viejo) {
            $this->assertSame(0, Html::contar($x, "//*[@id='$viejo']"), "$pantalla: id viejo #$viejo");
            $this->assertSame(0, Html::contar($x, "//a[contains(@href,'#$viejo')]"), "$pantalla: enlace viejo a #$viejo");
        }
        foreach ($x->query("//a[@href]") as $a) {
            $href = $a->getAttribute("href");
            $pos = strpos($href, "#");
            if ($pos === false || $pos === strlen($href) - 1) continue;
            $ancla = substr($href, $pos + 1);
            $ruta = substr($href, 0, $pos);
            // un enlace "#x" o a esta misma pantalla apunta a un id que tiene que existir en ella
            if ($ruta === "" || $ruta === APP_URL . "/") {
                $this->assertGreaterThan(0, Html::contar($x, "//*[@id='" . $ancla . "']"), "$pantalla: el enlace $href no tiene destino");
            }
        }
    }

    public function testNingunaVistaNiHojaDeEstilosNombraLasAnclasViejas(): void {
        $malas = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(BASE_PATH . "/app/Views", \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->getExtension() !== "php") continue;
            $t = (string)file_get_contents($f->getPathname());
            if (preg_match('/#(nosotros|contacto)\b|id="(nosotros|contacto)"/', $t)) $malas[] = $f->getFilename();
        }
        foreach (["base.css", "extra.css", "style.css"] as $css) {
            if (preg_match('/#(nosotros|contacto)\b/', (string)file_get_contents(BASE_PATH . "/assets/css/$css"))) $malas[] = $css;
        }
        $this->assertSame([], $malas);
    }

    public function testQuienesSomosMuestraLosTextosDeLaMarca(): void {
        $m = \Marca::todo();
        $m["nosotros"] = [
            "titulo" => "Titulo nosotros", "titulo_resalte" => "resalte nosotros", "imagen" => "assets/img/carro1.jpg",
            "parrafos" => ["Primer parrafo cambiado.", "Segundo parrafo cambiado."],
            "destacados" => [["icono" => "star", "titulo" => "Destacado Uno", "texto" => "Texto Uno"]],
        ];
        \Marca::usarParaPruebas($m);
        $x = Html::xpath(Pantallas::render($this->db, "nosotros"));
        $this->assertSame("Titulo nosotros resalte nosotros", preg_replace('/\s+/u', " ", trim($x->query("//h1")->item(0)->textContent)));
        $texto = preg_replace('/\s+/u', " ", $x->query("//main")->item(0)->textContent);
        foreach (["Primer parrafo cambiado.", "Segundo parrafo cambiado.", "Destacado Uno", "Texto Uno"] as $t) $this->assertStringContainsString($t, $texto);
        $this->assertSame(1, Html::contar($x, "//*[contains(concat(' ', normalize-space(@class), ' '), ' trust-badge ')]"), "un destacado, no los cuatro del archivo");
        $this->assertSame(1, Html::contar($x, "//main//a[contains(@class,'btn-gold')]"), "un solo botón principal");
    }

    public function testContactoMuestraLosDatosDeLaMarcaConEnlacesDeTelefonoCorreoYRedes(): void {
        \Marca::usarParaPruebas(array_replace_recursive(\Marca::todo(), [
            "contacto" => ["titulo" => "Hablemos", "intro" => "Intro cambiada."],
            "telefono" => "+999 (88) 7777-6666", "correo" => "cambiado@example.invalid", "direccion" => "Calle Cambiada 123",
        ]));
        $m = \Marca::todo();
        $m["horario"] = [["dia" => "Dia Uno", "horas" => "1:00 – 2:00", "abierto" => true], ["dia" => "Dia Dos", "horas" => "Cerrado", "abierto" => false]];
        $m["redes"] = [
            ["nombre" => "Red Buena", "url" => "https://example.invalid/buena", "icono" => "globe"],
            ["nombre" => "Red Mala", "url" => "javascript:alert(1)", "icono" => "globe"],
        ];
        \Marca::usarParaPruebas($m);
        $x = Html::xpath(Pantallas::render($this->db, "contacto"));
        $this->assertSame("Hablemos", trim($x->query("//h1")->item(0)->textContent));
        $texto = preg_replace('/\s+/u', " ", $x->query("//main")->item(0)->textContent);
        foreach (["Intro cambiada.", "Calle Cambiada 123", "+999 (88) 7777-6666", "cambiado@example.invalid", "Dia Uno", "1:00 – 2:00", "Dia Dos", "Cerrado", "Red Buena"] as $t) $this->assertStringContainsString($t, $texto, $t);
        $this->assertSame("tel:+9998877776666", $x->query("//main//a[starts-with(@href,'tel:')]/@href")->item(0)->nodeValue);
        $this->assertSame("mailto:cambiado@example.invalid", $x->query("//main//a[starts-with(@href,'mailto:')]/@href")->item(0)->nodeValue);
        $redes = $x->query("//main//a[@target='_blank']");
        $this->assertSame(1, $redes->length, "la red con javascript: no se enlaza");
        $this->assertSame("noopener noreferrer", $redes->item(0)->getAttribute("rel"));
        $this->assertStringNotContainsString("javascript:", Pantallas::render($this->db, "contacto"));
        $this->assertStringNotContainsString("Red Mala", $texto);
    }

    public function testContactoSinRedesNiHorarioNoDejaSeccionesVacias(): void {
        $m = \Marca::todo();
        $m["redes"] = [];
        $m["horario"] = [];
        \Marca::usarParaPruebas($m);
        $x = Html::xpath(Pantallas::render($this->db, "contacto"));
        $this->assertSame(0, Html::contar($x, "//h2[normalize-space()='Síguenos']"));
        $this->assertStringContainsString("Consulta el horario", $x->query("//main")->item(0)->textContent);
    }
}
