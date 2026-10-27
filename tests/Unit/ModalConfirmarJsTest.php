<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * assets/js/confirmar.js: el modal de confirmación que reemplaza a confirm(). Se ejecuta con Node sobre un DOM falso
 * (tests/Support/confirmar_js_prueba.js); el navegador integrado cubre el render y el <dialog> real.
 */
final class ModalConfirmarJsTest extends TestCase {
    /** @var array<string,mixed> */
    private static array $r = [];

    public static function setUpBeforeClass(): void {
        $p = proc_open(["node", BASE_PATH . "/tests/Support/confirmar_js_prueba.js"], [1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes);
        if (!is_resource($p)) self::markTestSkipped("No hay Node para probar confirmar.js.");
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $codigo = proc_close($p);
        if ($codigo !== 0) self::fail("Node falló ($codigo): $err");
        self::$r = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
    }

    public function testSinDatosNoPreguntaYElEnvioSigue(): void {
        $this->assertSame(["impedido" => false, "aperturas" => 0], self::$r["sinDatos"]);
        foreach (self::$r["rotos"] as $caso => $r) $this->assertSame(["impedido" => false, "aperturas" => 0], $r, "$caso: ni pregunta ni rompe");
    }

    public function testConDatosImpideElEnvioAbreElModalYPoneElFocoEnElBotonQueNoActua(): void {
        $r = self::$r["abre"];
        $this->assertTrue($r["impedido"]);
        $this->assertTrue($r["abierto"]);
        $this->assertSame([], $r["enviado"], "todavía no se envía");
        $this->assertSame(["Desactivar «Faro»", "Deja de verse.", "Desactivar", "Conservar"], [$r["titulo"], $r["texto"], $r["boton"], $r["cancelar"]]);
        $this->assertSame("cancelar", $r["foco"], "el foco empieza en Cancelar/Conservar, no en la acción destructiva");
        $this->assertSame("btn btn-danger", $r["clase"]);
        $this->assertSame("btn btn-gold", self::$r["noPeligro"]["clase"], "lo que no destruye lleva el botón principal");
        $this->assertSame("Cancelar", self::$r["noPeligro"]["cancelar"], "texto por defecto");
    }

    public function testCancelarNoEnviaYElFocoVuelveAlBotonQueLoAbrio(): void {
        $this->assertSame(["abierto" => false, "enviado" => [], "foco" => "boton-abre"], self::$r["cancelar"]);
    }

    public function testEscComoCancelar(): void {
        $this->assertSame(["enviado" => [], "foco" => "boton-abre"], self::$r["esc"], "el navegador cierra el <dialog> con Esc: no envía y devuelve el foco");
    }

    public function testElClicEnElFondoCancelaYEnLaCajaNo(): void {
        $this->assertSame(["abiertoTrasClicDentro" => true, "abiertoTrasClicFuera" => false, "enviado" => []], self::$r["fondo"]);
    }

    public function testAceptarReenviaElMismoFormularioConSuBotonYSoloUnaVez(): void {
        $r = self::$r["confirmar"];
        $this->assertSame([["requestSubmit", "boton-abre"]], $r["enviado"], "requestSubmit con el botón de origen: el POST sale igual, con su token");
        $this->assertFalse($r["segundoImpedido"], "el reenvío no vuelve a preguntar (y deja pasar a envio.js)");
        $this->assertTrue(self::$r["despues"]["impedido"], "el siguiente envío vuelve a preguntar");
    }

    public function testPorCampoSoloPreguntaConLosValoresDelMapa(): void {
        $r = self::$r["porCampo"];
        foreach (["procesando", "(vacío)", "constructor", "sinCampo"] as $v) $this->assertFalse($r[$v]["impedido"], "$v: se envía sin preguntar");
        $this->assertTrue($r["entregado"]["impedido"]);
        $this->assertSame("Entregar", $r["entregado"]["titulo"]);
        $this->assertSame("Cancelar pedido", $r["cancelado"]["titulo"]);
    }

    public function testElFocoQuedaAtrapadoEntreLosDosBotones(): void {
        $t = self::$r["tab"];
        $this->assertSame([true, "cancelar"], $t["desdeUltimo"], "Tab en el último vuelve al primero");
        $this->assertSame([true, "confirmar"], $t["atrasDesdePrimero"], "Shift+Tab en el primero va al último");
        $this->assertFalse($t["delPrimeroAlUltimoLoDejaAlNavegador"][0]);
        $this->assertFalse($t["atrasDesdeUltimoLoDejaAlNavegador"][0]);
        $this->assertFalse($t["otraTecla"]);
    }

    public function testSinDialogoUsaElAvisoNativoYNuncaEnviaSinPreguntar(): void {
        $r = self::$r["sinDialogo"];
        $this->assertSame(["impedido" => true, "enviado" => [], "avisos" => ["Desactivar «Faro». Deja de verse."]], $r["no"]);
        $this->assertSame(["impedido" => true, "enviado" => [["submit"]]], $r["si"]);
    }
}
