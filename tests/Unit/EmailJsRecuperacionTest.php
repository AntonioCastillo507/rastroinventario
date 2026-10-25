<?php
namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Correo de recuperación y registro del enlace solo en localhost. Nunca sale a la red. */
final class EmailJsRecuperacionTest extends TestCase {
    private string $dir;

    protected function setUp(): void {
        parent::setUp();
        $this->dir = RASTRO_TEST_TMP . DIRECTORY_SEPARATOR . "logs_" . uniqid();
        mkdir($this->dir, 0777, true);
        \EmailJsService::$dirLogs = $this->dir;
    }

    protected function tearDown(): void {
        \EmailJsService::$transporte = null;
        \EmailJsService::$dirLogs = null;
        foreach (glob($this->dir . "/*") ?: [] as $f) @unlink($f);
        @rmdir($this->dir);
        parent::tearDown();
    }

    public static function urlsLocales(): array {
        return [
            "localhost"            => ["http://localhost/rastroinventario"],
            "localhost con puerto" => ["http://localhost:8080/app"],
            "mayúsculas"           => ["http://LOCALHOST/app"],
            "127.0.0.1"            => ["http://127.0.0.1/app"],
            "::1"                  => ["http://[::1]:8080/app"],
            "https en localhost"   => ["https://localhost/app"],
        ];
    }
    public static function urlsNoLocales(): array {
        return [
            "producción"           => ["https://tienda.com"],
            "subdominio engañoso"  => ["http://localhost.evil.com/app"],
            "usuario engañoso"     => ["http://localhost@evil.com/app"],
            "ip engañosa"          => ["http://127.0.0.1.evil.com/app"],
            "ruta localhost"       => ["https://evil.com/localhost"],
            "consulta localhost"   => ["https://evil.com/?h=localhost"],
            "vacía"                => [""],
            "sin esquema"          => ["tienda.com"],
            "otra IP privada"      => ["http://192.168.1.10/app"],
        ];
    }

    #[DataProvider("urlsLocales")]
    public function testEnLocalSeEscribeElEnlace(string $appUrl): void {
        $this->assertTrue(\EmailJsService::registrarEnlaceDev($appUrl, "a@example.invalid", "http://x/restablecer.php?token=ABC"));
        $log = file_get_contents($this->dir . "/reset_dev.log");
        $this->assertStringContainsString("a@example.invalid http://x/restablecer.php?token=ABC", $log);
    }

    #[DataProvider("urlsNoLocales")]
    public function testFueraDeLocalNoSeEscribeNada(string $appUrl): void {
        $this->assertFalse(\EmailJsService::registrarEnlaceDev($appUrl, "a@example.invalid", "http://x/restablecer.php?token=ABC"));
        $this->assertFileDoesNotExist($this->dir . "/reset_dev.log");
    }

    public function testUnCorreoConSaltosDeLineaNoFalsificaEntradas(): void {
        \EmailJsService::registrarEnlaceDev("http://localhost/app", "a@example.invalid\n[2026] falso", "http://x?t=1");
        $this->assertCount(1, file($this->dir . "/reset_dev.log"));
    }

    public function testElTransporteRecibeLaPlantillaYLosParametros(): void {
        $recibido = null;
        \EmailJsService::$transporte = function (string $tpl, array $p) use (&$recibido): bool { $recibido = [$tpl, $p]; return true; };
        $ok = \EmailJsService::get()->enviarRecuperacion("ana@example.invalid", "Ana", "http://localhost/app/restablecer.php?token=ABC");
        $this->assertTrue($ok);
        $this->assertSame("ana@example.invalid", $recibido[1]["to_email"]);
        $this->assertSame("Ana", $recibido[1]["to_name"]);
        $this->assertSame("http://localhost/app/restablecer.php?token=ABC", $recibido[1]["reset_url"]);
        $this->assertSame(30, $recibido[1]["expira_minutos"]);
        $this->assertSame(\Marca::get("nombre"), $recibido[1]["app_nombre"]);
    }

    public function testSiElEnvioSalioBienNoSeEscribeElRegistroDeDesarrollo(): void {
        \EmailJsService::$transporte = fn(string $t, array $p): bool => true;
        \EmailJsService::get()->enviarRecuperacion("ana@example.invalid", "Ana", "http://localhost/app/restablecer.php?token=ABC");
        $this->assertFileDoesNotExist($this->dir . "/reset_dev.log");
    }

    public function testSiElEnvioFalloEnLocalSeEscribeElEnlace(): void {
        \EmailJsService::$transporte = fn(string $t, array $p): bool => false;
        $ok = \EmailJsService::get()->enviarRecuperacion("ana@example.invalid", "Ana", "http://localhost/app/restablecer.php?token=ABC");
        $this->assertFalse($ok);
        // APP_URL del proyecto es local en este entorno: el enlace queda en el registro de desarrollo
        $this->assertTrue(\EmailJsService::esLocal(APP_URL), "estas pruebas suponen un APP_URL local");
        $this->assertStringContainsString("token=ABC", file_get_contents($this->dir . "/reset_dev.log"));
    }

    public function testUnDestinatarioInvalidNoLlegaALaRed(): void {
        $m = new \ReflectionMethod(\EmailJsService::class, "enviar");
        $this->assertFalse($m->invoke(\EmailJsService::get(), "tpl_cualquiera", ["to_email" => "Alguien@Example.INVALID"]));
        $log = file_get_contents($this->dir . "/emailjs.log");
        $this->assertStringContainsString("SKIP (destinatario .invalid", $log);
        $this->assertStringNotContainsString("FALLO", $log);
    }

    public function testLaPlantillaDeRecuperacionVaProtegidaConDefined(): void {
        $codigo = file_get_contents(BASE_PATH . "/app/Utils/EmailJsService.php");
        $this->assertStringContainsString('defined("EMAILJS_TEMPLATE_RESET") ? EMAILJS_TEMPLATE_RESET : ""', $codigo);
    }

    public function testElTokenNuncaVaAlRegistroDeEmailJs(): void {
        // Con el envío real desactivado por la guarda .invalid, el registro solo anota el correo
        \EmailJsService::get()->enviarRecuperacion("ana@example.invalid", "Ana", "http://localhost/app/restablecer.php?token=SECRETO123");
        $this->assertStringNotContainsString("SECRETO123", file_get_contents($this->dir . "/emailjs.log"));
    }
}
