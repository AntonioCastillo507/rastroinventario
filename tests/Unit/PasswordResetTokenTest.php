<?php
namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Token de recuperación: forma, hash y enlace. Sin base de datos. */
final class PasswordResetTokenTest extends TestCase {
    public function testElTokenTiene43CaracteresBase64Url(): void {
        for ($i = 0; $i < 50; $i++) {
            $t = \PasswordResetModel::generarToken();
            $this->assertMatchesRegularExpression('/\A[A-Za-z0-9_-]{43}\z/', $t);
            $this->assertTrue(\PasswordResetModel::formatoValido($t));
        }
    }

    public function testLosTokensNoSeRepiten(): void {
        $vistos = [];
        for ($i = 0; $i < 300; $i++) $vistos[\PasswordResetModel::generarToken()] = true;
        $this->assertCount(300, $vistos);
    }

    public function testElHashEsSha256DelTokenYNoElTokenMismo(): void {
        $t = \PasswordResetModel::generarToken();
        $h = \PasswordResetModel::hash($t);
        $this->assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $h);
        $this->assertNotSame($t, $h);
        $this->assertSame(hash("sha256", $t), $h);
        $this->assertSame($h, \PasswordResetModel::hash($t), "determinista");
        $this->assertNotSame($h, \PasswordResetModel::hash($t . "x"));
        $this->assertStringNotContainsString($t, $h);
    }

    public static function formatosRaros(): array {
        $ok = str_repeat("a", 43);
        return [
            "arreglo"            => [[$ok]],
            "null"               => [null],
            "entero"             => [123],
            "vacío"              => [""],
            "42 caracteres"      => [str_repeat("a", 42)],
            "44 caracteres"      => [str_repeat("a", 44)],
            "símbolo"            => [str_repeat("a", 42) . "+"],
            "barra"              => [str_repeat("a", 42) . "/"],
            "igual"              => [str_repeat("a", 42) . "="],
            "espacio al final"   => [$ok . " "],
            "salto de línea"     => [$ok . "\n"],
            "byte nulo"          => [str_repeat("a", 42) . "\0"],
            "acento"             => [str_repeat("a", 42) . "é"],
            "inyección SQL"      => ["' OR '1'='1" . str_repeat("a", 32)],
        ];
    }

    #[DataProvider("formatosRaros")]
    public function testFormatosQueNuncaValen(mixed $t): void {
        $this->assertFalse(\PasswordResetModel::formatoValido($t));
    }

    public function testElEnlaceSeArmaConAppUrlYNoConElEncabezadoHost(): void {
        $antes = $_SERVER;
        $_SERVER["HTTP_HOST"] = "evil.test";
        $_SERVER["HTTP_X_FORWARDED_HOST"] = "evil.test";
        $_SERVER["SERVER_NAME"] = "evil.test";
        try {
            $e = \PasswordResetModel::enlace("TOKEN", "https://tienda.example/app/");
            $this->assertSame("https://tienda.example/app/restablecer.php?token=TOKEN", $e);
            $this->assertStringNotContainsString("evil.test", $e);
        } finally {
            $_SERVER = $antes;
        }
    }

    public function testLaVistaYElModeloNuncaLeenElEncabezadoHost(): void {
        foreach (["app/Models/PasswordResetModel.php", "app/Controllers/PasswordResetController.php", "app/Utils/EmailJsService.php"] as $rel) {
            $ruta = BASE_PATH . "/" . $rel;
            if (!is_file($ruta)) continue; // el controlador llega en el commit siguiente
            $this->assertDoesNotMatchRegularExpression('/HTTP_HOST|SERVER_NAME|X_FORWARDED|HTTP_ORIGIN|HTTP_REFERER/i', file_get_contents($ruta), $rel);
        }
    }

    public function testElCorreoSeNormalizaAMinusculas(): void {
        $this->assertSame("ana@example.invalid", \PasswordResetModel::normalizarCorreo("  Ana@Example.INVALID "));
    }
}
