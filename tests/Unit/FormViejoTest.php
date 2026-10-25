<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** FormViejo: lo escrito vuelve una sola vez, solo con los campos permitidos y sin secretos. */
final class FormViejoTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        unset($_SESSION["form_viejo"]);
    }

    protected function tearDown(): void {
        unset($_SESSION["form_viejo"]);
        parent::tearDown();
    }

    public function testGuardaSoloLosCamposPermitidos(): void {
        \FormViejo::guardar("compra", ["cliente_nombre" => "Ana", "nueva_pass" => "secreta-de-prueba", "password" => "otra", "csrf_token" => "t"], ["cliente_nombre"]);
        $this->assertSame(["cliente_nombre" => "Ana"], \FormViejo::tomar("compra"));
    }

    public function testNoGuardaContrasenasAunqueSePidanSinQuerer(): void {
        // los llamadores reales listan solo campos no secretos; aquí se comprueba que ninguno de esos nombres está en la lista
        foreach (["app/Controllers/PedidoController.php", "app/Controllers/AuthController.php"] as $f) {
            $codigo = (string)file_get_contents(BASE_PATH . "/$f");
            preg_match_all('/FormViejo::guardar\([^;]*\)/', $codigo, $m);
            $this->assertNotEmpty($m[0], $f);
            foreach ($m[0] as $llamada) $this->assertDoesNotMatchRegularExpression('/pass|clave|contrase|password/i', $llamada, "$f guarda un campo secreto: $llamada");
        }
    }

    public function testSeConsumeAlTomarla(): void {
        \FormViejo::guardar("registro", ["nombre" => "Luis"], ["nombre"]);
        $this->assertSame(["nombre" => "Luis"], \FormViejo::tomar("registro"));
        $this->assertSame([], \FormViejo::tomar("registro"), "la segunda vez ya no hay nada");
        $this->assertArrayNotHasKey("form_viejo", $_SESSION);
    }

    public function testCadaFormularioTieneLoSuyo(): void {
        \FormViejo::guardar("login", ["email" => "a@example.invalid"], ["email"]);
        \FormViejo::guardar("registro", ["email" => "b@example.invalid"], ["email"]);
        $this->assertSame(["email" => "a@example.invalid"], \FormViejo::tomar("login"));
        $this->assertSame(["email" => "b@example.invalid"], \FormViejo::tomar("registro"));
    }

    public function testIgnoraArreglosYRecortaLoMuyLargo(): void {
        \FormViejo::guardar("x", ["a" => ["no", "vale"], "b" => str_repeat("é", 5000), "c" => 12], ["a", "b", "c", "falta"]);
        $v = \FormViejo::tomar("x");
        $this->assertArrayNotHasKey("a", $v);
        $this->assertArrayNotHasKey("falta", $v);
        $this->assertSame(2000, mb_strlen($v["b"]));
        $this->assertSame("12", $v["c"]);
    }

    public function testSinNadaGuardadoDevuelveVacio(): void {
        $this->assertSame([], \FormViejo::tomar("nada"));
    }
}
