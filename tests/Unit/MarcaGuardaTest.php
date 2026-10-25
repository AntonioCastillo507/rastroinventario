<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\GuardaMarca;

/**
 * Guarda: el nombre, el teléfono y el correo de la marca solo viven en app/Config/marca.php,
 * en docs/ y en database/demo.sql. En cualquier otro archivo del código se lee con Marca::get().
 */
final class MarcaGuardaTest extends TestCase {
    private function patronesReales(): array {
        return GuardaMarca::patrones([
            "nombre" => (string)\Marca::get("nombre"), "telefono" => (string)\Marca::get("telefono"), "correo" => (string)\Marca::get("correo"),
        ]);
    }

    private function borrar(string $raiz): void {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($raiz, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        @rmdir($raiz);
    }

    public function testNingunaVistaControladorNiScriptEscribeLosDatosDeLaMarcaAMano(): void {
        $patrones = $this->patronesReales();
        $this->assertSame(["nombre", "telefono", "correo"], array_keys($patrones));
        $this->assertSame([], GuardaMarca::buscar(BASE_PATH, $patrones), "Datos de la marca escritos fuera de marca.php: usa Marca::get()");
    }

    public function testLaGuardaDetectaCadaDatoYRespetaLosSitiosPermitidos(): void {
        $raiz = RASTRO_TEST_TMP . DIRECTORY_SEPARATOR . "guarda_" . uniqid();
        foreach (["app/Views", "app/Config", "database", "docs", "vendor/x"] as $d) mkdir("$raiz/$d", 0777, true);
        $nombre = (string)\Marca::get("nombre");
        $tel = (string)\Marca::get("telefono");
        $correo = (string)\Marca::get("correo");
        file_put_contents("$raiz/app/Views/a.php", "<h1>" . $nombre . "</h1>\n");
        file_put_contents("$raiz/app/Views/b.php", "<p>" . mb_strtoupper($nombre) . "</p>\n");
        file_put_contents("$raiz/app/Views/c.php", "<p>" . str_replace(" ", "", strtolower($nombre)) . "</p>\n");
        file_put_contents("$raiz/app/Views/d.php", "ok\n<p>$tel</p>\n");
        file_put_contents("$raiz/app/Views/e.php", "<p>" . preg_replace('/\D+/', " ", substr($tel, -9)) . "</p>\n");
        file_put_contents("$raiz/app/Views/f.php", "<p>" . strtoupper($correo) . "</p>\n");
        // estos sitios sí pueden llevarlos
        file_put_contents("$raiz/app/Config/marca.php", "$nombre $tel $correo\n");
        file_put_contents("$raiz/database/demo.sql", "$nombre $tel $correo\n");
        file_put_contents("$raiz/docs/DESIGN.md", "$nombre $tel $correo\n");
        file_put_contents("$raiz/vendor/x/lib.php", "$nombre\n");
        file_put_contents("$raiz/app/Views/limpia.php", "<?=Marca::h(\"nombre\")?>\n");
        try {
            $r = GuardaMarca::buscar($raiz, $this->patronesReales());
        } finally {
            $this->borrar($raiz);
        }
        $this->assertSame([
            "app/Views/a.php:1 (nombre)", "app/Views/b.php:1 (nombre)", "app/Views/c.php:1 (nombre)",
            "app/Views/d.php:2 (telefono)", "app/Views/e.php:1 (telefono)", "app/Views/f.php:1 (correo)",
        ], $r);
    }
}
