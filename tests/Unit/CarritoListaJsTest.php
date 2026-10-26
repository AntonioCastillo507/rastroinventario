<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\Datos;
use Tests\Support\Proceso;

/**
 * assets/js/carrito_lista.js (paso 1 sin recargar): se ejecuta con Node sobre un DOM falso y un fetch simulado
 * (tests/Support/carrito_lista_js_prueba.js). El escenario "contrato" usa la respuesta REAL que da PedidoController::actualizarCarrito
 * en un proceso aparte, para que el JS y el servidor no se desincronicen. El clic real en pantalla no se cubre aquí.
 */
final class CarritoListaJsTest extends TestCase {
    private static array $r = [];

    public static function setUpBeforeClass(): void {
        $db = \Database::get();
        $parte = Datos::parte($db, 12.50, 8, "Pieza del contrato");
        $archivo = RASTRO_TEST_TMP . DIRECTORY_SEPARATOR . "contrato_" . uniqid() . ".json";
        try {
            $r = Proceso::accion(["clase" => "PedidoController", "accion" => "actualizarCarrito", "csrf" => true, "ip" => "203.0.113.82", "get" => ["id" => (string)$parte],
                "post" => ["cantidad" => "3"], "sesion" => ["cart" => [$parte => 1]], "servidor" => ["HTTP_X_REQUESTED_WITH" => "fetch"]]);
            file_put_contents($archivo, trim($r["salida"]));
        } finally {
            $db->prepare("DELETE FROM partes WHERE id = ?")->execute([$parte]);
        }
        $p = proc_open(["node", BASE_PATH . "/tests/Support/carrito_lista_js_prueba.js", $archivo], [1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes);
        if (!is_resource($p)) self::markTestSkipped("No hay Node para probar carrito_lista.js.");
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $codigo = proc_close($p);
        @unlink($archivo);
        if ($codigo !== 0) self::fail("Node falló ($codigo): $err");
        self::$r = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
    }

    public function testMasEnviaConFetchCantidadYSoloEscribeLoQueDevuelveElServidor(): void {
        $e = self::$r["mas"];
        $this->assertSame(1, $e["previnido"], "se frena el envío normal");
        $this->assertSame(1, $e["peticiones"]);
        $this->assertSame("POST", $e["metodo"]);
        $this->assertSame("fetch", $e["cabecera"]);
        $this->assertStringContainsString("actualizar_carrito&id=1", $e["url"]);
        $this->assertSame("3", $e["campos"]["cantidad"], "manda el valor del botón pulsado");
        $this->assertSame(0, $e["recargas"]);
        $this->assertSame("3", $e["linea1"]["cantidad"]);
        $this->assertSame("$30.00", $e["linea1"]["subtotal"]);
        $this->assertSame(["$35.50", "$2.49", "$37.99"], $e["resumen"], "los montos son los textos del servidor");
        $this->assertSame("2 piezas", $e["conteo"]);
        $this->assertSame(["t1", "t1"], $e["tokens"]);
        $this->assertSame("«Faro»: 3 unidades.", $e["estado"], "lo anuncia la región aria-live");
        $this->assertNull($e["ocupado"]);
    }

    public function testLosBotonesSiguenLaCantidadYElStock(): void {
        $this->assertSame(["v" => "2", "d" => false], self::$r["mas"]["linea1"]["menos"]);
        $this->assertSame(["v" => "4", "d" => false], self::$r["mas"]["linea1"]["mas"]);
        $this->assertSame(["v" => "1", "d" => true], self::$r["mas"]["linea2"]["menos"], "con una unidad, − queda desactivado");
        $this->assertSame(["v" => "5", "d" => true], self::$r["tope"]["mas"], "en el tope, + queda desactivado y no pasa del stock");
        $this->assertSame(["v" => "4", "d" => false], self::$r["tope"]["menos"]);
        $this->assertSame(["menos" => true, "mas" => false], self::$r["tope"]["foco"], "el + se desactivó: el foco pasa al − para no perderse");
    }

    public function testQuitarSacaLaLineaYOfreceDeshacer(): void {
        $q = self::$r["quitar"];
        $this->assertSame(["2"], $q["lineas"]);
        $this->assertSame("1 pieza", $q["conteo"]);
        $this->assertSame("1", $q["contador"], "el contador del encabezado baja");
        $this->assertSame(["Quitaste «Faro» de tu carrito.", "Deshacer"], $q["aviso"]);
        $this->assertSame("status", $q["rol"]);
        $this->assertTrue($q["foco"], "al quitar, el foco pasa a Deshacer");
        $this->assertSame("$5.89", $q["resumenTotal"]);
    }

    public function testDeshacerPideRestaurarYRecargaAlTerminar(): void {
        $this->assertSame(1, self::$r["deshacer"]["peticiones"]);
    }

    public function testQuitarLaUltimaRecargaParaQueElServidorPinteElVacio(): void {
        $this->assertSame(1, self::$r["vacio"]["recargas"]);
    }

    public function testRestaurarDesdeElAvisoDelServidorRecarga(): void {
        $this->assertSame(["peticiones" => 1, "recargas" => 1], self::$r["restaurar"]);
    }

    public function testUnErrorSinEstadoMuestraElAvisoYNoToca(): void {
        $c = self::$r["caducado"];
        $this->assertSame(["La página caducó."], $c["avisos"]);
        $this->assertStringContainsString("alert-warning", $c["clase"]);
        $this->assertSame(["t5", "t5"], $c["tokens"], "toma el token vigente");
        $this->assertSame("2", $c["linea1"]["cantidad"], "la pantalla no cambió");
        $this->assertNull($c["ocupado"]);
    }

    public function testLosAvisosDelServidorSeMuestranUnoPorUnoYNoSeAcumulan(): void {
        $a = self::$r["avisosServidor"];
        $this->assertSame(["«Pieza» ya no está disponible y se quitó de tu carrito."], $a["avisos"]);
        $this->assertSame("alert", $a["rol"]);
        $this->assertSame(2, $a["lineas"] + 1, "las líneas que el servidor no devuelve se quitan");
        $this->assertSame(1, $a["trasOtraAccion"], "el aviso viejo se reemplaza");
    }

    public function testSinRedSeEnviaElFormularioNormalConElBotonPulsado(): void {
        $s = self::$r["sinRed"];
        $this->assertSame(1, $s["normal"]);
        $this->assertSame(["name" => "cantidad", "value" => "3", "type" => "hidden"], $s["oculto"], "sin esto el servidor no sabría qué cantidad se pidió");
    }

    public function testDosAccionesVanDeUnaEnUna(): void {
        $c = self::$r["cola"];
        $this->assertSame(2, $c["peticiones"]);
        $this->assertSame("4", $c["cantidad"]);
        $this->assertSame(["B", "B"], $c["tokens"]);
    }

    public function testUnFormularioAjenoNoSeToca(): void {
        $this->assertSame(["previnido" => 0, "peticiones" => 0], self::$r["ajeno"]);
    }

    public function testLaRespuestaRealDelServidorSeAplicaSinTropiezos(): void {
        $c = self::$r["contrato"];
        $this->assertSame("3", $c["cantidad"]);
        $this->assertSame('$37.50', $c["subtotal"]);
        $this->assertSame(['$37.50', '$2.63', '$40.13'], $c["resumen"], "12.50 × 3 + 7 %");
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $c["tokens"][0]);
        $this->assertSame(0, $c["recargas"]);
    }
}
