<?php
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatos;
use Tests\Support\Datos;
use Tests\Support\Imagenes;
use Tests\Support\Proceso;

/**
 * "Eliminar" una pieza en el panel (ParteController::eliminar) solo la desactiva y NO borra sus fotos:
 * los pedidos y las ventas viejas siguen refiriéndose a ella. Corre el controlador real en un proceso
 * aparte compartiendo la carpeta de subidas temporal de las pruebas; usa commits y borra lo suyo por ids exactos.
 */
final class EliminarParteTest extends TestCase {
    private PDO $db;
    private int $uid;
    private array $partes = [];
    private array $pedidos = [];

    protected function setUp(): void {
        parent::setUp();
        $this->db = \Database::get();
        BaseDatos::verificarBase($this->db);
        $this->assertFalse($this->db->inTransaction());
        Imagenes::limpiarGuardados();
        $this->db->prepare("INSERT INTO usuarios (nombre, email, password) VALUES ('Admin eliminar', ?, '!')")
                 ->execute(["eliminar." . bin2hex(random_bytes(4)) . "@example.invalid"]);
        $this->uid = (int)$this->db->lastInsertId();
        $this->db->prepare("INSERT INTO usuario_roles (usuario_id, rol_id) SELECT ?, id FROM roles WHERE nombre = 'Administrador'")->execute([$this->uid]);
    }

    protected function tearDown(): void {
        if ($this->pedidos) {
            $in = implode(",", array_map("intval", $this->pedidos));
            $this->db->exec("DELETE FROM pedido_items WHERE pedido_id IN ($in)");
            $this->db->exec("DELETE FROM pedidos WHERE id IN ($in)");
        }
        if ($this->partes) $this->db->exec("DELETE FROM partes WHERE id IN (" . implode(",", array_map("intval", $this->partes)) . ")");
        $this->db->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$this->uid]);
        Imagenes::limpiarGuardados();
        parent::tearDown();
    }

    /** Pieza con su original y su miniatura reales en la carpeta de subidas */
    private function piezaConImagenes(): array {
        $r = \ImageHelper::procesar(Imagenes::jpeg());
        $this->assertNotNull($r, \ImageHelper::$error);
        $id = Datos::parte($this->db, 10.00, 5);
        $this->partes[] = $id;
        $this->db->prepare("UPDATE partes SET thumbnail = ?, imagen_grande = ? WHERE id = ?")->execute([$r["thumbnail"], $r["original"], $id]);
        return [$id, $r["original"], $r["thumbnail"]];
    }

    private function eliminar(int $id, string $metodo = "POST"): array {
        return Proceso::accion(["clase" => "ParteController", "accion" => "eliminar", "csrf" => true, "metodo_http" => $metodo,
            "get" => ["id" => (string)$id], "upload_path" => UPLOAD_PATH,
            "sesion" => ["user" => ["id" => $this->uid, "nombre" => "Admin eliminar", "email" => "x@example.invalid"], "user_roles" => ["Administrador"]]]);
    }

    private function activo(int $id): int {
        $s = $this->db->prepare("SELECT activo FROM partes WHERE id = ?");
        $s->execute([$id]);
        return (int)$s->fetchColumn();
    }

    public function testDesactivarNoBorraLasFotos(): void {
        [$id, $orig, $thumb] = $this->piezaConImagenes();
        $this->assertFileExists(UPLOAD_PATH . "parts/" . $orig);
        $this->assertFileExists(UPLOAD_PATH . "thumbnails/" . $thumb);

        $r = $this->eliminar($id);
        $this->assertSame("Parte desactivada.", $r["sesion"]["flash"]["msg"] ?? null);
        $this->assertSame(0, $this->activo($id), "la pieza queda desactivada");
        $this->assertFileExists(UPLOAD_PATH . "parts/" . $orig, "el original sigue en disco");
        $this->assertFileExists(UPLOAD_PATH . "thumbnails/" . $thumb, "la miniatura sigue en disco");

        $fila = $this->db->query("SELECT thumbnail, imagen_grande FROM partes WHERE id = $id")->fetch();
        $this->assertSame([$thumb, $orig], [$fila["thumbnail"], $fila["imagen_grande"]], "la fila conserva los nombres");
    }

    public function testUnPedidoViejoSigueTeniendoSuImagen(): void {
        [$id, , $thumb] = $this->piezaConImagenes();
        $this->pedidos[] = Datos::pedido($this->db, [[$id, 2]]);
        $this->eliminar($id);

        $items = (new \PedidoModel())->findWithItems($this->pedidos[0])["items"];
        $this->assertSame($id, (int)$items[0]["parte_id"]);
        $this->assertSame($thumb, $items[0]["thumbnail"], "el pedido sigue sabiendo cuál era la miniatura");
        $this->assertFileExists(UPLOAD_PATH . "thumbnails/" . $thumb);
    }

    public function testPorGetNoSeDesactivaNada(): void {
        [$id, $orig, $thumb] = $this->piezaConImagenes();
        $this->eliminar($id, "GET");
        $this->assertSame(1, $this->activo($id));
        $this->assertFileExists(UPLOAD_PATH . "parts/" . $orig);
        $this->assertFileExists(UPLOAD_PATH . "thumbnails/" . $thumb);
    }
}
