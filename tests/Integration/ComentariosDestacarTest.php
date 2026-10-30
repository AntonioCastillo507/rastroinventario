<?php
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatos;
use Tests\Support\Datos;
use Tests\Support\Proceso;

/**
 * Destacar una reseña en la portada: solo por POST con token y con el permiso de editar comentarios; no se destaca una oculta,
 * ocultar la quita de la portada y hay un tope de 12. Corre el controlador real en un proceso aparte; usa commits y borra lo suyo por ids.
 */
final class ComentariosDestacarTest extends TestCase {
    private PDO $db;
    private int $editor, $sinPermiso, $rol, $parte;
    private array $comentarios = [];

    protected function setUp(): void {
        parent::setUp();
        $this->db = \Database::get();
        BaseDatos::verificarBase($this->db);
        $this->assertFalse($this->db->inTransaction());
        $this->db->exec("UPDATE comentarios SET destacado = 0"); // la base de pruebas queda sin reseñas, esto solo asegura el tope
        $this->db->prepare("INSERT INTO roles (nombre) VALUES (?)")->execute(["Editor S7bD " . bin2hex(random_bytes(3))]);
        $this->rol = (int)$this->db->lastInsertId();
        $this->db->prepare("INSERT INTO permisos (rol_id, modulo, puede_ver, puede_editar) VALUES (?, 'comentarios', 1, 1)")->execute([$this->rol]);
        $this->editor = $this->usuario();
        $this->db->prepare("INSERT INTO usuario_roles (usuario_id, rol_id) VALUES (?, ?)")->execute([$this->editor, $this->rol]);
        $this->sinPermiso = $this->usuario();
        $this->parte = Datos::parte($this->db, 10, 5);
    }

    protected function tearDown(): void {
        foreach ($this->comentarios as $c) $this->db->prepare("DELETE FROM comentarios WHERE id = ?")->execute([$c]);
        $this->db->prepare("DELETE FROM partes WHERE id = ?")->execute([$this->parte]);
        $this->db->prepare("DELETE FROM usuarios WHERE id IN (?, ?)")->execute([$this->editor, $this->sinPermiso]);
        $this->db->prepare("DELETE FROM roles WHERE id = ?")->execute([$this->rol]);
        parent::tearDown();
    }

    private function usuario(): int {
        $this->db->prepare("INSERT INTO usuarios (nombre, email, password) VALUES ('Portada S7bD', ?, '!')")->execute(["pd." . bin2hex(random_bytes(4)) . "@example.invalid"]);
        return (int)$this->db->lastInsertId();
    }

    private function resena(int $publicado = 1, int $destacado = 0): int {
        $this->db->prepare("INSERT INTO comentarios (parte_id, nombre_visitante, comentario, rating, publicado, destacado) VALUES (?, 'Visitante S7bD', 'texto largo suficiente', 5, ?, ?)")->execute([$this->parte, $publicado, $destacado]);
        return $this->comentarios[] = (int)$this->db->lastInsertId();
    }

    private function destacado(int $id): int { return (int)$this->db->query("SELECT destacado FROM comentarios WHERE id = $id")->fetchColumn(); }

    private function accion(string $accion, int $id, int $uid, string $metodo = "POST", bool $csrf = true): array {
        return Proceso::accion(["clase" => "ComentarioController", "accion" => $accion, "csrf" => $csrf, "metodo_http" => $metodo, "get" => ["id" => (string)$id],
            "sesion" => ["user" => ["id" => $uid, "nombre" => "Prueba", "email" => "x@example.invalid"], "user_roles" => ["Moderador"], "last_activity" => time()]]);
    }

    public function testElEditorDestacaYQuitaDeLaPortadaConAvisoDelPanel(): void {
        $id = $this->resena();
        $r = $this->accion("destacar", $id, $this->editor);
        $this->assertSame(1, $this->destacado($id));
        $this->assertSame("success", $r["sesion"]["flash"]["type"] ?? null);
        $r = $this->accion("quitarPortada", $id, $this->editor);
        $this->assertSame(0, $this->destacado($id));
        $this->assertSame("success", $r["sesion"]["flash"]["type"] ?? null);
    }

    public function testPorGetNoCambiaNada(): void {
        $id = $this->resena();
        $this->accion("destacar", $id, $this->editor, "GET");
        $this->assertSame(0, $this->destacado($id));
        $id2 = $this->resena(1, 1);
        $this->accion("quitarPortada", $id2, $this->editor, "GET");
        $this->assertSame(1, $this->destacado($id2));
    }

    public function testSinTokenNoCambiaNada(): void {
        $id = $this->resena();
        $this->accion("destacar", $id, $this->editor, "POST", false);
        $this->assertSame(0, $this->destacado($id));
    }

    public function testSinPermisoDeEditarNoCambiaNada(): void {
        $id = $this->resena();
        $this->accion("destacar", $id, $this->sinPermiso);
        $this->assertSame(0, $this->destacado($id));
        $id2 = $this->resena(1, 1);
        $this->accion("quitarPortada", $id2, $this->sinPermiso);
        $this->assertSame(1, $this->destacado($id2));
    }

    public function testUnaResenaOcultaNoSePuedeDestacar(): void {
        $id = $this->resena(0, 0);
        $r = $this->accion("destacar", $id, $this->editor);
        $this->assertSame(0, $this->destacado($id));
        $this->assertSame("danger", $r["sesion"]["flash"]["type"] ?? null);
        $this->assertStringContainsString("oculta", $r["sesion"]["flash"]["msg"] ?? "");
    }

    public function testOcultarUnaDestacadaLaQuitaDeLaPortadaYMostrarlaNoLaVuelvePonerSola(): void {
        $id = $this->resena(1, 1);
        (new \ComentarioModel())->togglePublicado($id);
        $f = $this->db->query("SELECT publicado, destacado FROM comentarios WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame([0, 0], [(int)$f["publicado"], (int)$f["destacado"]]);
        (new \ComentarioModel())->togglePublicado($id);
        $f = $this->db->query("SELECT publicado, destacado FROM comentarios WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame([1, 0], [(int)$f["publicado"], (int)$f["destacado"]]);
    }

    public function testHayUnTopeDeDoceDestacadas(): void {
        $this->assertSame(12, \ComentarioModel::MAX_DESTACADAS);
        $antes = (int)$this->db->query("SELECT COUNT(*) FROM comentarios WHERE destacado = 1")->fetchColumn();
        for ($i = $antes; $i < 12; $i++) $this->resena(1, 1);
        $extra = $this->resena();
        $r = $this->accion("destacar", $extra, $this->editor);
        $this->assertSame(0, $this->destacado($extra));
        $this->assertSame("danger", $r["sesion"]["flash"]["type"] ?? null);
        $this->assertStringContainsString("12", $r["sesion"]["flash"]["msg"] ?? "");
        // quitar una deja espacio
        $this->db->prepare("UPDATE comentarios SET destacado = 0 WHERE id = ?")->execute([$this->comentarios[0]]);
        $this->accion("destacar", $extra, $this->editor);
        $this->assertSame(1, $this->destacado($extra));
    }

    public function testLasAccionesDeLaVistaLlegaranAUnMetodoDelControlador(): void {
        // admin/index.php convierte action=quitar_portada en quitarPortada; si el método no existe, cae en index() sin avisar
        $vista = (string)file_get_contents(BASE_PATH . "/app/Views/admin/comentarios/index.php");
        preg_match_all('~mod=comentarios&action=(?:<\?=\$dest\?"(\w+)":"(\w+)"\?>|(\w+))~', $vista, $m);
        $acciones = array_filter(array_merge($m[1], $m[2], $m[3]));
        $this->assertContains("quitar_portada", $acciones);
        foreach ($acciones as $raw) $this->assertTrue(method_exists(\ComentarioController::class, lcfirst(str_replace("_", "", ucwords($raw, "_")))), $raw);
    }

    public function testElModeloDestacaSoloVisiblesYSeColumnaExisteEnElEsquema(): void {
        $m = new \ComentarioModel();
        $oculta = $this->resena(0, 0);
        $this->assertNotSame("", $m->destacar($oculta));
        $this->assertSame("El comentario no existe.", $m->destacar(999999999));
        $esquema = (string)file_get_contents(BASE_PATH . "/database/schema.sql");
        $this->assertMatchesRegularExpression("~CREATE TABLE `comentarios` \\(.*?`destacado` tinyint\\(1\\) NOT NULL DEFAULT '0'~s", $esquema);
        $mig = (string)file_get_contents(BASE_PATH . "/database/migraciones/005_comentarios_destacado.sql");
        $this->assertStringContainsString("information_schema.COLUMNS", $mig, "idempotente");
        $this->assertStringContainsString("tinyint(1) NOT NULL DEFAULT ''0''", $mig);
    }
}
