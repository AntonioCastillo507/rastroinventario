<?php
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatos;
use Tests\Support\Proceso;

/**
 * Reglas sobre los Administradores en el módulo Usuarios, con UserController real en un proceso aparte:
 *  (a) solo un Administrador edita, desactiva o cambia los roles de otro Administrador, y solo un Administrador
 *      puede dar el rol de Administrador;
 *  (b) un Administrador no puede quitarse su propio rol ni desactivarse, y el sistema no se queda sin un
 *      Administrador activo.
 * Usa commits (el hijo es otra conexión). Roles de prueba con permiso de editar usuarios (como un Vendedor
 * al que se le dio ese permiso). Todo se borra al final por ids exactos.
 */
final class UsuariosAdministradoresTest extends TestCase {
    private const CLAVE = "Frase de prueba de usuarios 2026";
    private PDO $db;
    private array $uids = [];
    private array $correosCreados = [];
    private array $rolesPropios = [];
    private int $rolAdmin;
    private int $rolCliente;
    private int $rolGestor;      // usuarios (ver, crear, editar) + roles (ver, editar)
    private int $rolGestorSinRoles; // usuarios (ver, crear, editar), sin permiso de roles

    protected function setUp(): void {
        parent::setUp();
        $this->db = \Database::get();
        BaseDatos::verificarBase($this->db);
        $this->assertFalse($this->db->inTransaction());
        $this->rolAdmin   = $this->idRol("Administrador");
        $this->rolCliente = $this->idRol("Cliente");
        $this->rolGestor        = $this->crearRol(true);
        $this->rolGestorSinRoles = $this->crearRol(false);
    }

    protected function tearDown(): void {
        foreach ($this->uids as $id) $this->db->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id]);
        foreach ($this->correosCreados as $e) $this->db->prepare("DELETE FROM usuarios WHERE email = ?")->execute([$e]); // por si un fallo dejó crear a alguien
        foreach ($this->rolesPropios as $id) $this->db->prepare("DELETE FROM roles WHERE id = ?")->execute([$id]);
        parent::tearDown();
    }

    private function idRol(string $nombre): int {
        $s = $this->db->prepare("SELECT id FROM roles WHERE nombre = ?");
        $s->execute([$nombre]);
        $id = $s->fetchColumn();
        $this->assertNotFalse($id, "falta el rol $nombre en la base de pruebas");
        return (int)$id;
    }

    private function crearRol(bool $conRoles): int {
        $this->db->prepare("INSERT INTO roles (nombre, descripcion) VALUES (?, 'rol de prueba')")->execute(["Gestor prueba " . bin2hex(random_bytes(4))]);
        $id = (int)$this->db->lastInsertId();
        $this->rolesPropios[] = $id;
        $p = $this->db->prepare("INSERT INTO permisos (rol_id, modulo, puede_ver, puede_crear, puede_editar, puede_borrar) VALUES (?, ?, 1, ?, ?, 0)");
        $p->execute([$id, "usuarios", 1, 1]);
        if ($conRoles) $p->execute([$id, "roles", 0, 1]);
        return $id;
    }

    /** @param int[] $roles */
    private function usuario(array $roles, int $activo = 1, string $nombre = "Persona de prueba"): array {
        $email = "usr." . bin2hex(random_bytes(4)) . "@example.invalid";
        $this->db->prepare("INSERT INTO usuarios (nombre, email, password, activo) VALUES (?, ?, ?, ?)")
                 ->execute([$nombre, $email, password_hash(self::CLAVE, PASSWORD_BCRYPT), $activo]);
        $id = (int)$this->db->lastInsertId();
        $this->uids[] = $id;
        foreach ($roles as $r) $this->db->prepare("INSERT INTO usuario_roles (usuario_id, rol_id) VALUES (?, ?)")->execute([$id, $r]);
        return ["id" => $id, "email" => $email];
    }

    private function sesion(array $actor): array {
        return ["user" => ["id" => $actor["id"], "nombre" => "Actor", "email" => $actor["email"]], "user_roles" => []];
    }

    private function editar(array $actor, int $objetivo, array $post = [], bool $esPost = true): array {
        return Proceso::accion(["clase" => "UserController", "accion" => "editar", "csrf" => $esPost, "metodo_http" => $esPost ? "POST" : "GET",
            "get" => ["id" => $objetivo], "post" => $post, "sesion" => $this->sesion($actor)]);
    }

    private function toggle(array $actor, int $objetivo): array {
        return Proceso::accion(["clase" => "UserController", "accion" => "toggle", "csrf" => true, "metodo_http" => "POST",
            "get" => ["id" => $objetivo], "sesion" => $this->sesion($actor)]);
    }

    private function crear(array $actor, array $post): array {
        return Proceso::accion(["clase" => "UserController", "accion" => "crear", "csrf" => true, "metodo_http" => "POST",
            "post" => $post, "sesion" => $this->sesion($actor)]);
    }

    private function fila(int $id): array {
        $s = $this->db->prepare("SELECT nombre, email, password, activo FROM usuarios WHERE id = ?");
        $s->execute([$id]);
        return $s->fetch();
    }

    private function roles(int $id): array {
        $s = $this->db->prepare("SELECT rol_id FROM usuario_roles WHERE usuario_id = ? ORDER BY rol_id");
        $s->execute([$id]);
        return array_map("intval", $s->fetchAll(PDO::FETCH_COLUMN));
    }

    private function flash(array $r): string { return (string)($r["sesion"]["flash"]["msg"] ?? ""); }

    private function postEditar(array $objetivo, array $extra = []): array {
        return $extra + ["nombre" => "Nombre nuevo", "email" => $objetivo["email"], "password" => ""];
    }

    // ── (a) Solo un Administrador toca a un Administrador ────────────
    public function testUnGestorNoPuedeEditarAUnAdministrador(): void {
        $gestor = $this->usuario([$this->rolGestor]);
        $admin  = $this->usuario([$this->rolAdmin], 1, "Administrador original");
        $antes  = $this->fila($admin["id"]);
        $r = $this->editar($gestor, $admin["id"], $this->postEditar($admin, ["password" => "Otra frase larga 2026!", "roles" => [$this->rolCliente]]));
        $this->assertSame("Solo un Administrador puede editar a otro Administrador.", $this->flash($r));
        $this->assertSame($antes, $this->fila($admin["id"]), "ni nombre, ni correo, ni clave");
        $this->assertSame([$this->rolAdmin], $this->roles($admin["id"]), "ni roles");
    }

    public function testUnGestorNiSiquieraVeElFormularioDeUnAdministrador(): void {
        $gestor = $this->usuario([$this->rolGestor]);
        $admin  = $this->usuario([$this->rolAdmin]);
        $r = $this->editar($gestor, $admin["id"], [], false);
        $this->assertSame("Solo un Administrador puede editar a otro Administrador.", $this->flash($r));
        $this->assertStringNotContainsString("<form", $r["salida"]);
    }

    public function testUnGestorNoPuedeDesactivarNiReactivarAUnAdministrador(): void {
        $gestor = $this->usuario([$this->rolGestor]);
        $admin  = $this->usuario([$this->rolAdmin]);
        $r = $this->toggle($gestor, $admin["id"]);
        $this->assertSame("Solo un Administrador puede desactivar o reactivar a otro Administrador.", $this->flash($r));
        $this->assertSame(1, (int)$this->fila($admin["id"])["activo"]);

        $inactivo = $this->usuario([$this->rolAdmin], 0);
        $this->toggle($gestor, $inactivo["id"]);
        $this->assertSame(0, (int)$this->fila($inactivo["id"])["activo"], "tampoco lo reactiva");
    }

    public function testUnGestorNoPuedeDarseNiDarleANadieElRolDeAdministrador(): void {
        $gestor = $this->usuario([$this->rolGestor]);
        $cliente = $this->usuario([$this->rolCliente]);
        // a sí mismo
        $r = $this->editar($gestor, $gestor["id"], $this->postEditar($gestor, ["roles" => [$this->rolGestor, $this->rolAdmin]]));
        $this->assertStringContainsString("Solo un Administrador puede dar el rol de Administrador.", $r["salida"]);
        $this->assertSame([$this->rolGestor], $this->roles($gestor["id"]));
        // a otra persona
        $r = $this->editar($gestor, $cliente["id"], $this->postEditar($cliente, ["roles" => [$this->rolAdmin]]));
        $this->assertStringContainsString("Solo un Administrador puede dar el rol de Administrador.", $r["salida"]);
        $this->assertSame([$this->rolCliente], $this->roles($cliente["id"]));
        $this->assertFalse((new \UserModel())->esAdministrador($cliente["id"]));
    }

    public function testUnGestorNoPuedeCrearUnAdministrador(): void {
        $gestor = $this->usuario([$this->rolGestor]);
        $email = "nuevo." . bin2hex(random_bytes(4)) . "@example.invalid";
        $this->correosCreados[] = $email;
        $r = $this->crear($gestor, ["nombre" => "Aspirante", "email" => $email, "password" => self::CLAVE, "roles" => [$this->rolAdmin]]);
        $this->assertStringContainsString("Solo un Administrador puede dar el rol de Administrador.", $r["salida"]);
        $n = $this->db->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ?");
        $n->execute([$email]);
        $this->assertSame(0, (int)$n->fetchColumn(), "no se creó nada");
    }

    public function testUnGestorSigueEditandoAPersonasQueNoSonAdministradores(): void {
        $gestor = $this->usuario([$this->rolGestor]);
        $cliente = $this->usuario([$this->rolCliente]);
        $r = $this->editar($gestor, $cliente["id"], $this->postEditar($cliente, ["roles" => [$this->rolCliente]]));
        $this->assertSame("Usuario actualizado.", $this->flash($r));
        $this->assertSame("Nombre nuevo", $this->fila($cliente["id"])["nombre"]);
        $this->toggle($gestor, $cliente["id"]);
        $this->assertSame(0, (int)$this->fila($cliente["id"])["activo"]);
    }

    public function testUnGestorSinPermisoDeRolesNoCambiaRolesPeroSiLosDatos(): void {
        $gestor = $this->usuario([$this->rolGestorSinRoles]);
        $cliente = $this->usuario([$this->rolCliente]);
        $r = $this->editar($gestor, $cliente["id"], $this->postEditar($cliente, ["roles" => [$this->rolAdmin]]));
        $this->assertSame("Usuario actualizado.", $this->flash($r));
        $this->assertSame([$this->rolCliente], $this->roles($cliente["id"]), "los roles se conservan");
    }

    public function testUnAdministradorSiPuedeEditarDesactivarYReactivarAOtro(): void {
        $a = $this->usuario([$this->rolAdmin]);
        $b = $this->usuario([$this->rolAdmin]);
        $r = $this->editar($a, $b["id"], $this->postEditar($b, ["roles" => [$this->rolAdmin]]));
        $this->assertSame("Usuario actualizado.", $this->flash($r));
        $this->assertSame("Nombre nuevo", $this->fila($b["id"])["nombre"]);

        $this->toggle($a, $b["id"]);
        $this->assertSame(0, (int)$this->fila($b["id"])["activo"], "otro administrador sí se puede desactivar");
        $this->toggle($a, $b["id"]);
        $this->assertSame(1, (int)$this->fila($b["id"])["activo"], "y reactivar");
    }

    public function testUnAdministradorPuedeQuitarleElRolAOtroAdministrador(): void {
        $a = $this->usuario([$this->rolAdmin]);
        $b = $this->usuario([$this->rolAdmin]);
        $r = $this->editar($a, $b["id"], $this->postEditar($b, ["roles" => [$this->rolCliente]]));
        $this->assertSame("Usuario actualizado.", $this->flash($r));
        $this->assertSame([$this->rolCliente], $this->roles($b["id"]));
    }

    public function testUnAdministradorPuedeDarElRolDeAdministrador(): void {
        $a = $this->usuario([$this->rolAdmin]);
        $c = $this->usuario([$this->rolCliente]);
        $this->editar($a, $c["id"], $this->postEditar($c, ["roles" => [$this->rolCliente, $this->rolAdmin]]));
        $this->assertTrue((new \UserModel())->esAdministrador($c["id"]));
    }

    // ── (b) Sin auto-daño y sin quedarse sin Administradores ─────────
    public function testUnAdministradorNoPuedeDesactivarseASiMismo(): void {
        $a = $this->usuario([$this->rolAdmin]);
        $this->usuario([$this->rolAdmin]); // hay otro: la regla no depende de cuántos haya
        $r = $this->toggle($a, $a["id"]);
        $this->assertSame("No puedes desactivar tu propia cuenta.", $this->flash($r));
        $this->assertSame(1, (int)$this->fila($a["id"])["activo"]);
    }

    public function testUnAdministradorNoPuedeQuitarseSuPropioRol(): void {
        $a = $this->usuario([$this->rolAdmin]);
        $this->usuario([$this->rolAdmin]);
        $r = $this->editar($a, $a["id"], $this->postEditar($a, ["roles" => [$this->rolCliente]]));
        $this->assertStringContainsString("No puedes quitarte tu propio rol de Administrador.", $r["salida"]);
        $this->assertSame([$this->rolAdmin], $this->roles($a["id"]));
        $this->assertNotSame("Nombre nuevo", $this->fila($a["id"])["nombre"], "no se guardó nada del formulario");
    }

    public function testUnAdministradorPuedeEditarSusPropiosDatosSiConservaElRol(): void {
        $a = $this->usuario([$this->rolAdmin]);
        $r = $this->editar($a, $a["id"], $this->postEditar($a, ["roles" => [$this->rolAdmin]]));
        $this->assertSame("Usuario actualizado.", $this->flash($r));
        $this->assertSame("Nombre nuevo", $this->fila($a["id"])["nombre"]);
        $this->assertSame([$this->rolAdmin], $this->roles($a["id"]));
    }

    public function testElSistemaNoSeQuedaSinAdministradorActivo(): void {
        // Defensa extra: la sesión de un administrador que ya fue desactivado seguiría viva en un caso raro.
        // Si aun así intenta tocar al único administrador activo, la regla lo detiene.
        $inactivo = $this->usuario([$this->rolAdmin], 0);
        $unico    = $this->usuario([$this->rolAdmin]);
        $this->assertSame(1, (new \UserModel())->contarAdministradoresActivos());

        $r = $this->toggle($inactivo, $unico["id"]);
        $this->assertSame("No se puede dejar el sistema sin un Administrador activo.", $this->flash($r));
        $this->assertSame(1, (int)$this->fila($unico["id"])["activo"]);

        $r = $this->editar($inactivo, $unico["id"], $this->postEditar($unico, ["roles" => [$this->rolCliente]]));
        $this->assertStringContainsString("No se puede dejar el sistema sin un Administrador activo.", $r["salida"]);
        $this->assertSame([$this->rolAdmin], $this->roles($unico["id"]));
    }

    public function testQuitarElRolAUnAdministradorInactivoNoCuentaComoPerderUnoActivo(): void {
        $a = $this->usuario([$this->rolAdmin]);
        $inactivo = $this->usuario([$this->rolAdmin], 0);
        $r = $this->editar($a, $inactivo["id"], $this->postEditar($inactivo, ["roles" => [$this->rolCliente]]));
        $this->assertSame("Usuario actualizado.", $this->flash($r));
        $this->assertSame([$this->rolCliente], $this->roles($inactivo["id"]));
    }

    public function testElModeloCuentaSoloAdministradoresActivosYSinBloqueo(): void {
        $um = new \UserModel();
        $antes = $um->contarAdministradoresActivos();
        $a = $this->usuario([$this->rolAdmin]);
        $this->usuario([$this->rolAdmin], 0);
        $bloq = $this->usuario([$this->rolAdmin]);
        $this->db->exec("UPDATE usuarios SET bloqueado = 1 WHERE id = " . (int)$bloq["id"]);
        $this->usuario([$this->rolCliente]);
        $this->assertSame($antes + 1, $um->contarAdministradoresActivos());
        $this->assertTrue($um->esAdministrador($a["id"]));
        $this->assertFalse($um->esAdministrador(0));
    }
}
