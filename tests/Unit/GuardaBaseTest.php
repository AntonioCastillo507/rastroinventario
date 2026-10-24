<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** La suite jamás debe estar conectada a otra base que rastroinventario_test. */
final class GuardaBaseTest extends TestCase {
    public function testLaVariableDeEntornoEsLaDeLaBaseDePruebas(): void {
        $this->assertSame("rastroinventario_test", getenv("RASTRO_DB_NAME"));
    }

    public function testLaConexionDeLaAplicacionEsLaDePruebas(): void {
        $this->assertSame("rastroinventario_test", \Database::get()->query("SELECT DATABASE()")->fetchColumn());
    }

    public function testLasSubidasYElRegistroDeErroresVanAUnaCarpetaTemporal(): void {
        $this->assertStringStartsWith(sys_get_temp_dir(), UPLOAD_PATH);
        $this->assertStringContainsString("rastro_test_", UPLOAD_PATH);
        $this->assertStringStartsWith(RASTRO_TEST_TMP, (string)ini_get("error_log"));
    }

    public function testElOverrideSoloAceptaLasDosBasesPermitidas(): void {
        // Lee el código de Database.php: el patrón permitido tiene que seguir siendo estrecho
        $codigo = file_get_contents(BASE_PATH . "/app/Config/Database.php");
        $this->assertStringContainsString("rastroinventario_(test|check)", $codigo);
        $this->assertStringContainsString('["cli", "cli-server"]', $codigo);
    }
}
