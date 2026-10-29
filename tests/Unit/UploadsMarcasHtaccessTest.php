<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** uploads/marcas/.htaccess: lo que dice el archivo (la prueba contra Apache real está en Integration/UploadsMarcasApacheTest) */
final class UploadsMarcasHtaccessTest extends TestCase {
    private string $h;

    protected function setUp(): void {
        parent::setUp();
        $this->h = (string)file_get_contents(BASE_PATH . "/uploads/marcas/.htaccess");
        $this->assertNotSame("", $this->h);
    }

    public function testNiegaPorDefectoYNoEjecutaNada(): void {
        $this->assertMatchesRegularExpression('/^Require all denied\s*$/m', $this->h);
        $this->assertStringContainsString("-ExecCGI", $this->h);
        $this->assertStringContainsString("-Indexes", $this->h);
        $this->assertStringContainsString("RemoveHandler .php", $this->h);
        $this->assertStringContainsString("php_flag engine off", $this->h);
    }

    public function testElPatronCoincideConLosNombresDeLogoMarca(): void {
        $this->assertSame(1, preg_match('/<FilesMatch "(\^marca_[^"]+)">\s*Require all granted/', $this->h, $m), "no encuentro el FilesMatch permitido");
        $patron = "~" . $m[1] . "~";
        foreach (["png", "webp", "svg"] as $ext) {
            $this->assertSame(1, preg_match($patron, "marca_" . bin2hex(random_bytes(16)) . "." . $ext), $ext);
        }
        foreach (["x.php", "marca_" . str_repeat("a", 32) . ".php", "marca_" . str_repeat("a", 32) . ".php.png", "marca_" . str_repeat("a", 32) . ".gif", "marca_" . str_repeat("a", 32) . ".jpg", "otro.png", ".htaccess", "marca_" . str_repeat("a", 31) . ".png"] as $n) {
            $this->assertSame(0, preg_match($patron, $n), "Apache serviría $n");
        }
    }

    public function testNiegaLoDemasAunqueElPadreLoPermita(): void {
        // uploads/.htaccess deja pasar jpg y gif; aquí tiene que haber un deny explícito para todo lo que no sea el patrón
        $this->assertSame(1, preg_match('/<FilesMatch "\^\(\?!(marca_[^"]+)\)">\s*Require all denied/', $this->h, $m));
        $this->assertStringContainsString('marca_[0-9a-f]{32}\.(svg|png|webp)$', $m[1]);
    }

    public function testSvgLlevaCabecerasQueLoDejanSinPoderes(): void {
        $this->assertStringContainsString("AddType image/svg+xml .svg", $this->h);
        $this->assertStringContainsString('Header set X-Content-Type-Options "nosniff"', $this->h);
        $this->assertMatchesRegularExpression("/Content-Security-Policy \"default-src 'none'; style-src 'unsafe-inline'; sandbox\"/", $this->h);
    }
}
