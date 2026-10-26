<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\Imagenes;

/**
 * uploads/.htaccess solo sirve imágenes con nombres que genera ImageHelper. Si una derivada nueva (_1200, _2400...) no cabe en el
 * patrón, Apache responde 403 y la foto sale rota aunque exista en disco (pasó con las derivadas de 1200 y 2400 px).
 */
final class UploadsHtaccessTest extends TestCase {
    protected function tearDown(): void {
        Imagenes::limpiarGuardados();
        parent::tearDown();
    }

    private function patron(): string {
        $h = (string)file_get_contents(BASE_PATH . "/uploads/.htaccess");
        $this->assertSame(1, preg_match('/<FilesMatch "([^"]+)">/', $h, $m), "no encuentro el FilesMatch");
        return "~" . $m[1] . "~";
    }

    public function testApacheSirveTodoLoQueGeneraImageHelper(): void {
        $patron = $this->patron();
        foreach (["jpeg", "png", "gif", "webp"] as $fabrica) {
            $r = \ImageHelper::procesar(Imagenes::$fabrica(900, 700));
            $this->assertNotNull($r, \ImageHelper::$error);
            $nombres = [$r["original"], $r["thumbnail"], \ImageHelper::derivada($r["original"], "medium"), \ImageHelper::derivada($r["original"], "large")];
            foreach ($nombres as $n) $this->assertSame(1, preg_match($patron, $n), "Apache negaría $n");
            Imagenes::limpiarGuardados();
        }
    }

    public function testApacheSirveLosNombresViejosConPuntoYNumero(): void {
        $patron = $this->patron();
        foreach (["parte_6a4a000fe47d65.17283908.jpg", "parte_6a4a000fe47d65.17283908_th.jpg", "parte_6a4a000fe47d65.17283908_1200.jpg", "parte_6a4a000fe47d65.17283908_2400.jpg", "parte_6a5179bfcb7fa6.84253492.png"] as $n) {
            $this->assertSame(1, preg_match($patron, $n), $n);
        }
    }

    public function testApacheNiegaTodoLoQueNoSeaUnaImagenDeLaCasa(): void {
        $patron = $this->patron();
        foreach (["x.php", "x.php.jpg", "x.jpg.php", ".htaccess", "x.svg", "x.html", "x.js", "../x.jpg", "x/y.jpg", "x_th.php", "x_1200.php", "X.JPG", "_x.jpg", "x.jpg ", "x\n.php"] as $n) {
            $this->assertSame(0, preg_match($patron, $n), "Apache serviría $n");
        }
    }
}
