<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Token CSRF por sesión: no se gasta al usarlo y solo cambia con rotar(). Sin base de datos. */
final class CsrfTokenSesionTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        $_SESSION = [];
        $_POST = [];
    }

    protected function tearDown(): void {
        $_SESSION = [];
        $_POST = [];
        parent::tearDown();
    }

    public function testGenerateDevuelveSiempreElMismoTokenDeLaSesion(): void {
        $a = \CsrfToken::generate();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $a);
        $this->assertSame($a, \CsrfToken::generate());
        $this->assertStringContainsString($a, \CsrfToken::field());
    }

    public function testElTokenNoSeGastaAlUsarlo(): void {
        $_POST["csrf_token"] = \CsrfToken::generate();
        $this->assertTrue(\CsrfToken::check());
        $this->assertTrue(\CsrfToken::check(), "dos pestañas con el mismo token");
        $this->assertSame($_POST["csrf_token"], $_SESSION["csrf_token"], "sigue en la sesión");
    }

    public function testRotarDaUnoNuevoYElAnteriorYaNoVale(): void {
        $viejo = \CsrfToken::generate();
        $nuevo = \CsrfToken::rotar();
        $this->assertNotSame($viejo, $nuevo);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $nuevo);
        $this->assertSame($nuevo, \CsrfToken::generate());
        $_POST["csrf_token"] = $viejo;
        $this->assertFalse(\CsrfToken::check());
        $_POST["csrf_token"] = $nuevo;
        $this->assertTrue(\CsrfToken::check());
    }

    /** @return array<string,array{0:mixed}> */
    public static function tokensMalos(): array {
        return ["vacío" => [""], "otro" => [str_repeat("a", 64)], "arreglo" => [["x"]], "nulo" => [null], "número" => [123]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("tokensMalos")]
    public function testUnTokenQueNoEsElDeLaSesionNoVale(mixed $malo): void {
        \CsrfToken::generate();
        $_POST["csrf_token"] = $malo;
        $this->assertFalse(\CsrfToken::check());
    }

    public function testSinTokenEnLaSesionUnTokenVacioNoPasa(): void {
        $_POST["csrf_token"] = "";
        $this->assertFalse(\CsrfToken::check());
        unset($_POST["csrf_token"]);
        $this->assertFalse(\CsrfToken::check());
    }
}
