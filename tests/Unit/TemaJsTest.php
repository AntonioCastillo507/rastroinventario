<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Comportamiento de assets/js/tema.js: ejecuta el script con Node sobre un DOM falso
 * (tests/Support/tema_js_prueba.js). El navegador integrado cubre el resto (parpadeo, render).
 */
final class TemaJsTest extends TestCase {
    /** @var array<string,array<string,mixed>> */
    private static array $r = [];

    public static function setUpBeforeClass(): void {
        $p = proc_open(["node", BASE_PATH . "/tests/Support/tema_js_prueba.js"], [1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes);
        if (!is_resource($p)) self::markTestSkipped("No hay Node para probar tema.js.");
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $codigo = proc_close($p);
        if ($codigo !== 0) self::fail("Node falló ($codigo): $err");
        self::$r = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
    }

    public function testOscuroPorDefectoYAplicaTambienElTemaDeBootstrap(): void {
        foreach (["antesDeCargar", "sinGuardado"] as $k) {
            $this->assertSame("dark", self::$r[$k]["tema"], $k);
            $this->assertSame("dark", self::$r[$k]["bs"], $k);
        }
        $this->assertNull(self::$r["sinGuardado"]["guardado"], "No debe guardar nada hasta que el usuario elija");
    }

    public function testRecuerdaLaEleccionGuardada(): void {
        $this->assertSame("light", self::$r["guardadoClaro"]["tema"], "Se aplica antes de que cargue la página (sin parpadeo)");
        $this->assertSame("light", self::$r["guardadoClaro"]["bs"]);
        $this->assertSame("light", self::$r["guardadoClaroTrasCargar"]["tema"]);
        $this->assertSame("dark", self::$r["guardadoOscuro"]["tema"]);
    }

    public function testUnValorGuardadoNoValidoSeIgnora(): void {
        foreach (["guardadoBasuraAntes", "guardadoBasura"] as $k) {
            $this->assertSame("dark", self::$r[$k]["tema"], "$k: un valor guardado no válido no puede acabar en el atributo");
            $this->assertSame("dark", self::$r[$k]["bs"], $k);
        }
    }

    public function testElBotonAlternaGuardaYCambiaSuNombreAccesible(): void {
        $this->assertSame("Cambiar a modo claro", self::$r["sinGuardado"]["etiqueta"]);
        $this->assertSame("bi bi-sun", self::$r["sinGuardado"]["icono"]);
        $a = self::$r["tras1Clic"];
        $this->assertSame(["light", "light", "light"], [$a["tema"], $a["bs"], $a["guardado"]]);
        $this->assertSame("Cambiar a modo oscuro", $a["etiqueta"]);
        $this->assertSame("Cambiar a modo oscuro", $a["titulo"]);
        $this->assertSame("bi bi-moon-stars", $a["icono"]);
        $b = self::$r["tras2ClicEnIcono"];
        $this->assertSame(["dark", "dark", "dark"], [$b["tema"], $b["bs"], $b["guardado"]], "Un clic sobre el icono también alterna");
        $this->assertSame("Cambiar a modo claro", $b["etiqueta"]);
    }

    public function testUnClicFueraDelBotonNoHaceNada(): void {
        $this->assertSame(self::$r["tras2ClicEnIcono"], self::$r["clicAjeno"]);
        $this->assertSame(self::$r["tras2ClicEnIcono"], self::$r["clicSinClosest"]);
    }

    public function testSiElNavegadorBloqueaElAlmacenamientoSigueFuncionando(): void {
        $this->assertSame("dark", self::$r["almacenBloqueado"]["tema"]);
        $this->assertSame("light", self::$r["almacenBloqueadoClic"]["tema"], "Alterna aunque no pueda guardar");
        $this->assertArrayNotHasKey("error", self::$r["almacenBloqueadoClic"]);
    }

    public function testSigueLosCambiosHechosEnOtraPestana(): void {
        $this->assertSame("light", self::$r["otraPestanaClaro"]["tema"]);
        $this->assertSame("light", self::$r["otraPestanaBasura"]["tema"], "Un valor no válido de otra pestaña se ignora");
        $this->assertSame("light", self::$r["otraClave"]["tema"], "Otras claves de almacenamiento se ignoran");
    }
}
