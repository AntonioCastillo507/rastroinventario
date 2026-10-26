<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\Datos;
use Tests\Support\Html;
use Tests\Support\Imagenes;

/** Si el archivo de la imagen no está en disco, las vistas muestran el marcador sin imagen, no una imagen rota. */
final class ImagenesFaltantesVistasTest extends BaseDatos {
    private int $uid;

    protected function setUp(): void {
        parent::setUp();
        Imagenes::limpiarGuardados();
        $_SERVER["REQUEST_METHOD"] = "GET"; // en consola no existe y los controladores lo leen
        $this->uid = Datos::usuario($this->db);
        $this->db->prepare("INSERT INTO usuario_roles (usuario_id, rol_id) SELECT ?, id FROM roles WHERE nombre = 'Administrador'")->execute([$this->uid]);
        $_SESSION["user"] = ["id" => $this->uid, "nombre" => "Admin", "email" => "a@example.invalid"];
        $_SESSION["user_roles"] = ["Administrador"];
    }

    protected function tearDown(): void {
        $_GET = [];
        unset($_SERVER["REQUEST_METHOD"]);
        unset($_SESSION["user"], $_SESSION["user_roles"]);
        Imagenes::limpiarGuardados();
        parent::tearDown();
    }

    /** Pieza con nombres de imagen guardados; $enDisco decide si los archivos existen */
    private function pieza(string $nombre, bool $enDisco): array {
        if ($enDisco) {
            $r = \ImageHelper::procesar(Imagenes::jpeg());
            [$orig, $thumb] = [$r["original"], $r["thumbnail"]];
        } else {
            $sufijo = bin2hex(random_bytes(16));
            [$orig, $thumb] = ["parte_$sufijo.jpg", "parte_{$sufijo}_th.jpg"];
        }
        $id = Datos::parte($this->db, 10.00, 5, $nombre);
        $this->db->prepare("UPDATE partes SET thumbnail = ?, imagen_grande = ? WHERE id = ?")->execute([$thumb, $orig, $id]);
        return [$id, $orig, $thumb];
    }

    private function html(callable $accion, array $get): \DOMXPath {
        $_GET = $get;
        ob_start();
        try { $accion(); } finally { $html = (string)ob_get_clean(); }
        return Html::xpath($html);
    }

    private function imagenes(\DOMXPath $x, string $nombreArchivo): int {
        return Html::contar($x, "//img[contains(@src, '$nombreArchivo')]");
    }

    public function testCatalogoYPortada(): void {
        [, , $falta] = $this->pieza("Marcador sin archivo", false);
        [, , $hay]   = $this->pieza("Marcador con archivo", true);
        foreach ([["ver" => "catalogo", "q" => "Marcador"]] as $get) {
            $x = $this->html(fn() => (new \PublicController())->home(), $get);
            $this->assertSame(2, Html::contar($x, "//div[@class='pub-card']"), "las dos piezas se listan");
            $this->assertSame(0, $this->imagenes($x, $falta), "sin archivo, sin <img>");
            $this->assertSame(1, $this->imagenes($x, $hay), "con archivo, con <img>");
        }
    }

    public function testDetalle(): void {
        [$idFalta, $origFalta, $thumbFalta] = $this->pieza("Detalle sin archivo", false);
        $x = $this->html(fn() => (new \PublicController())->detalle(), ["id" => (string)$idFalta]);
        $this->assertSame(0, $this->imagenes($x, $origFalta) + $this->imagenes($x, $thumbFalta));

        [$id, $orig] = $this->pieza("Detalle con archivo", true);
        $x = $this->html(fn() => (new \PublicController())->detalle(), ["id" => (string)$id]);
        $this->assertSame(1, $this->imagenes($x, "/uploads/medium/" . \ImageHelper::derivada($orig, "medium")), "usa la derivada de 1200 px");

        // Sin la de 1200 px cae al original; sin original, a la miniatura
        unlink(UPLOAD_PATH . "medium/" . \ImageHelper::derivada($orig, "medium"));
        $x = $this->html(fn() => (new \PublicController())->detalle(), ["id" => (string)$id]);
        $this->assertSame(1, $this->imagenes($x, "/uploads/parts/$orig"), "sin la derivada usa el original");
        unlink(UPLOAD_PATH . "parts/$orig");
        $x = $this->html(fn() => (new \PublicController())->detalle(), ["id" => (string)$id]);
        $this->assertSame(0, $this->imagenes($x, "/uploads/parts/"));
        $this->assertSame(1, $this->imagenes($x, "/uploads/thumbnails/"));
    }

    public function testPanelDeInventario(): void {
        [, , $falta] = $this->pieza("Inventario sin archivo", false);
        [, , $hay]   = $this->pieza("Inventario con archivo", true);
        $x = $this->html(fn() => (new \ParteController())->index(), ["q" => "Inventario"]);
        $this->assertSame(2, Html::contar($x, "//table//tbody/tr"));
        $this->assertSame(0, $this->imagenes($x, $falta));
        $this->assertSame(1, $this->imagenes($x, $hay));
        $this->assertSame(1, Html::contar($x, "//span[contains(@class,'foto-baldosa')][not(img)]/i[contains(@class,'bi-image')]"), "la pieza sin archivo muestra el marcador en la misma baldosa");
    }

    public function testFormularioDeEdicion(): void {
        [$idFalta, , $falta] = $this->pieza("Edición sin archivo", false);
        $x = $this->html(fn() => (new \ParteController())->editar(), ["id" => (string)$idFalta]);
        $this->assertSame(0, $this->imagenes($x, $falta));
        $this->assertSame(0, Html::contar($x, "//*[@data-foto-previa]//img"), "sin archivo, la vista previa muestra el marcador");

        [$id, , $hay] = $this->pieza("Edición con archivo", true);
        $x = $this->html(fn() => (new \ParteController())->editar(), ["id" => (string)$id]);
        $this->assertSame(1, $this->imagenes($x, $hay));
    }
}
