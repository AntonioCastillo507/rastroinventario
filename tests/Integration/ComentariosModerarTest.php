<?php
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatos;
use Tests\Support\Datos;
use Tests\Support\Proceso;

/**
 * Ocultar o mostrar un comentario lo hace solo un Administrador (aunque otro rol tenga editar comentarios), por POST con token.
 * Corre el controlador real en un proceso aparte; usa commits y borra lo suyo por ids exactos.
 */
final class ComentariosModerarTest extends TestCase {
    private PDO $db;
    private int $admin, $moderador, $rol, $parte, $comentario;

    protected function setUp(): void {
        parent::setUp();
        $this->db = \Database::get();
        BaseDatos::verificarBase($this->db);
        $this->assertFalse($this->db->inTransaction());
        $this->admin = $this->usuario("Administrador");
        $this->db->prepare("INSERT INTO roles (nombre) VALUES (?)")->execute(["Moderador S5 " . bin2hex(random_bytes(3))]);
        $this->rol = (int)$this->db->lastInsertId();
        $this->db->prepare("INSERT INTO permisos (rol_id, modulo, puede_ver, puede_editar) VALUES (?, 'comentarios', 1, 1)")->execute([$this->rol]);
        $this->moderador = $this->usuario(null);
        $this->db->prepare("INSERT INTO usuario_roles (usuario_id, rol_id) VALUES (?, ?)")->execute([$this->moderador, $this->rol]);
        $this->parte = Datos::parte($this->db, 10, 5);
        $this->db->prepare("INSERT INTO comentarios (parte_id, nombre_visitante, comentario, publicado) VALUES (?, 'Visitante S5', 'texto largo suficiente', 1)")->execute([$this->parte]);
        $this->comentario = (int)$this->db->lastInsertId();
    }

    protected function tearDown(): void {
        $this->db->prepare("DELETE FROM comentarios WHERE id = ?")->execute([$this->comentario]);
        $this->db->prepare("DELETE FROM partes WHERE id = ?")->execute([$this->parte]);
        $this->db->prepare("DELETE FROM usuarios WHERE id IN (?, ?)")->execute([$this->admin, $this->moderador]);
        $this->db->prepare("DELETE FROM roles WHERE id = ?")->execute([$this->rol]);
        parent::tearDown();
    }

    private function usuario(?string $rolNombre): int {
        $this->db->prepare("INSERT INTO usuarios (nombre, email, password) VALUES ('Moderación S5', ?, '!')")->execute(["mod." . bin2hex(random_bytes(4)) . "@example.invalid"]);
        $id = (int)$this->db->lastInsertId();
        if ($rolNombre) $this->db->prepare("INSERT INTO usuario_roles (usuario_id, rol_id) SELECT ?, id FROM roles WHERE nombre = ?")->execute([$id, $rolNombre]);
        return $id;
    }

    private function publicado(): int { return (int)$this->db->query("SELECT publicado FROM comentarios WHERE id = " . $this->comentario)->fetchColumn(); }

    private function accion(string $accion, int $uid, string $metodo = "POST", array $roles = ["Administrador"]): array {
        return Proceso::accion(["clase" => "ComentarioController", "accion" => $accion, "csrf" => true, "metodo_http" => $metodo, "get" => ["id" => (string)$this->comentario],
            "sesion" => ["user" => ["id" => $uid, "nombre" => "Prueba", "email" => "x@example.invalid"], "user_roles" => $roles, "last_activity" => time()]]);
    }

    public function testElAdministradorOcultaYMuestra(): void {
        $this->accion("toggle", $this->admin);
        $this->assertSame(0, $this->publicado());
        $r = $this->accion("toggle", $this->admin);
        $this->assertSame(1, $this->publicado());
        $this->assertSame("success", $r["sesion"]["flash"]["type"] ?? null);
    }

    public function testOtroRolConPermisoDeEditarNoPuedeYSeLeAvisa(): void {
        $r = $this->accion("toggle", $this->moderador, "POST", ["Moderador"]);
        $this->assertSame(1, $this->publicado(), "el comentario no cambió");
        $this->assertSame("danger", $r["sesion"]["flash"]["type"] ?? null);
        $this->assertStringContainsString("Solo un Administrador", $r["sesion"]["flash"]["msg"] ?? "");
    }

    public function testPorGetNoCambiaNada(): void {
        $this->accion("toggle", $this->admin, "GET");
        $this->assertSame(1, $this->publicado());
    }

    public function testEliminarYaNoBorraNadaAunqueSeLlameALaRutaVieja(): void {
        // admin/index.php cae en index() cuando la acción no existe: se muestra la lista y el comentario sigue ahí
        $this->accion("eliminar", $this->admin);
        $this->assertSame(1, (int)$this->db->query("SELECT COUNT(*) FROM comentarios WHERE id = " . $this->comentario)->fetchColumn());
        $this->assertFalse(method_exists(\ComentarioController::class, "eliminar"));
    }
}
