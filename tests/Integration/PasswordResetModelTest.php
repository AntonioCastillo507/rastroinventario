<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\Datos;

/** PasswordResetModel contra rastroinventario_test (cada prueba dentro de una transacción con rollback). */
final class PasswordResetModelTest extends BaseDatos {
    private \PasswordResetModel $m;

    protected function setUp(): void {
        parent::setUp();
        $this->m = new \PasswordResetModel();
    }

    private function usuario(): array {
        $id = Datos::usuario($this->db);
        $this->db->prepare("UPDATE usuarios SET password = ? WHERE id = ?")->execute([password_hash("clave-vieja-de-prueba", PASSWORD_BCRYPT), $id]);
        $u = $this->db->prepare("SELECT id, email FROM usuarios WHERE id = ?");
        $u->execute([$id]);
        return $u->fetch();
    }

    /** Pide un enlace como lo hace el controlador y devuelve [token, hash, id de la fila] */
    private function pedir(array $u, string $ip = "198.51.100.5"): array {
        $t = \PasswordResetModel::generarToken();
        $h = \PasswordResetModel::hash($t);
        $id = $this->m->registrarSolicitud($u["email"], $ip, (int)$u["id"], $h);
        return [$t, $h, $id];
    }

    private function fila(int $id): array {
        $s = $this->db->prepare("SELECT * FROM password_resets WHERE id = ?");
        $s->execute([$id]);
        return $s->fetch();
    }

    private function hashDeClave(int $uid): string {
        $s = $this->db->prepare("SELECT password FROM usuarios WHERE id = ?");
        $s->execute([$uid]);
        return $s->fetchColumn();
    }

    public function testEnLaBaseSoloQuedaElHashNuncaElToken(): void {
        $u = $this->usuario();
        [$t, $h, $id] = $this->pedir($u);
        $f = $this->fila($id);
        $this->assertSame($h, $f["token_hash"]);
        $this->assertSame(64, strlen($f["token_hash"]));
        foreach ($f as $col => $valor) $this->assertStringNotContainsString($t, (string)$valor, "columna $col");
        $this->assertSame(0, (int)$this->db->query("SELECT COUNT(*) FROM password_resets WHERE token_hash = " . $this->db->quote($t))->fetchColumn());
    }

    public function testUnEnlaceRecienPedidoEsValidoYNoSeConsumeAlMirarlo(): void {
        $u = $this->usuario();
        [, $h] = $this->pedir($u);
        $v = $this->m->buscarValido($h);
        $this->assertSame((int)$u["id"], (int)$v["usuario_id"]);
        $this->assertSame($u["email"], $v["email"]);
        $this->assertNotFalse($this->m->buscarValido($h), "mirarlo dos veces no lo gasta");
    }

    public function testCaducaALos30Minutos(): void {
        $u = $this->usuario();
        [, $h, $id] = $this->pedir($u);
        $f = $this->fila($id);
        $min = (int)$this->db->query("SELECT TIMESTAMPDIFF(MINUTE, creado_en, expira_en) FROM password_resets WHERE id = $id")->fetchColumn();
        $this->assertSame(30, $min);
        $this->db->exec("UPDATE password_resets SET expira_en = NOW() + INTERVAL 5 SECOND WHERE id = $id");
        $this->assertNotFalse($this->m->buscarValido($h), "a punto de caducar todavía vale");
        $this->db->exec("UPDATE password_resets SET expira_en = NOW() - INTERVAL 1 SECOND WHERE id = $id");
        $this->assertFalse($this->m->buscarValido($h), "un segundo después ya no");
        $this->assertFalse($this->m->completar($h, password_hash("otra-clave-segura-1", PASSWORD_BCRYPT)));
        $this->assertTrue(password_verify("clave-vieja-de-prueba", $this->hashDeClave((int)$u["id"])), "la clave no cambió");
        $this->assertNull($this->fila($id)["usado_en"], "un token caducado tampoco se marca como usado");
    }

    public function testSoloSirveUnaVez(): void {
        $u = $this->usuario();
        [, $h, $id] = $this->pedir($u);
        $nueva1 = password_hash("primera-clave-nueva-9", PASSWORD_BCRYPT);
        $this->assertTrue($this->m->completar($h, $nueva1));
        $this->assertNotNull($this->fila($id)["usado_en"]);
        $this->assertFalse($this->m->buscarValido($h));

        $this->assertFalse($this->m->completar($h, password_hash("segunda-clave-nueva-9", PASSWORD_BCRYPT)), "el segundo canje falla");
        $this->assertSame($nueva1, $this->hashDeClave((int)$u["id"]), "la clave sigue siendo la del primer canje");
        $this->assertTrue(password_verify("primera-clave-nueva-9", $this->hashDeClave((int)$u["id"])));
    }

    public function testUnTokenInventadoNoVale(): void {
        $this->assertFalse($this->m->buscarValido(\PasswordResetModel::hash(\PasswordResetModel::generarToken())));
        $this->assertFalse($this->m->completar(\PasswordResetModel::hash("inventado"), password_hash("x-clave-segura-1", PASSWORD_BCRYPT)));
    }

    public function testUnEnlaceNuevoInvalidaAlAnterior(): void {
        $u = $this->usuario();
        [, $h1] = $this->pedir($u);
        [, $h2] = $this->pedir($u);
        $this->assertFalse($this->m->buscarValido($h1), "solo vale el último enlace");
        $this->assertNotFalse($this->m->buscarValido($h2));
        $this->assertFalse($this->m->completar($h1, password_hash("x-clave-segura-1", PASSWORD_BCRYPT)));
    }

    public function testCambiarLaClaveInvalidaLosOtrosPendientes(): void {
        $u = $this->usuario();
        [, $h1, $id1] = $this->pedir($u);
        // Un segundo token vivo a la fuerza (como si dos solicitudes hubieran corrido a la vez)
        $h2 = \PasswordResetModel::hash(\PasswordResetModel::generarToken());
        $this->db->prepare("INSERT INTO password_resets (usuario_id,email,token_hash,ip,expira_en) VALUES (?,?,?,?, NOW() + INTERVAL 30 MINUTE)")
                 ->execute([$u["id"], $u["email"], $h2, "198.51.100.5"]);
        $this->assertNotFalse($this->m->buscarValido($h2));
        $this->assertTrue($this->m->completar($h1, password_hash("clave-nueva-bien-1", PASSWORD_BCRYPT)));
        $this->assertFalse($this->m->buscarValido($h2), "el otro enlace pendiente murió con el cambio");
    }

    public function testUnUsuarioDesactivadoNoPuedeCambiarLaClave(): void {
        $u = $this->usuario();
        [, $h, $id] = $this->pedir($u);
        $this->db->exec("UPDATE usuarios SET activo = 0 WHERE id = " . (int)$u["id"]);
        $this->assertFalse($this->m->buscarValido($h));
        $this->assertFalse($this->m->completar($h, password_hash("clave-nueva-bien-1", PASSWORD_BCRYPT)));
        $this->assertTrue(password_verify("clave-vieja-de-prueba", $this->hashDeClave((int)$u["id"])));
        $this->assertNull($this->fila($id)["usado_en"], "no quedó gastado a medias");
    }

    public function testUnUsuarioBloqueadoNoPuedeCambiarLaClave(): void {
        $u = $this->usuario();
        [, $h] = $this->pedir($u);
        $this->db->exec("UPDATE usuarios SET bloqueado = 1 WHERE id = " . (int)$u["id"]);
        $this->assertFalse($this->m->buscarValido($h));
        $this->assertFalse($this->m->completar($h, password_hash("clave-nueva-bien-1", PASSWORD_BCRYPT)));
        $this->assertTrue(password_verify("clave-vieja-de-prueba", $this->hashDeClave((int)$u["id"])));
    }

    public function testCompletarSoloCambiaLaClaveDeSuCuenta(): void {
        $a = $this->usuario();
        $b = $this->usuario();
        $hashB = $this->hashDeClave((int)$b["id"]);
        [, $h] = $this->pedir($a);
        $this->assertTrue($this->m->completar($h, password_hash("clave-nueva-bien-1", PASSWORD_BCRYPT)));
        $this->assertSame($hashB, $this->hashDeClave((int)$b["id"]));
    }

    public function testElLimitePorCorreoCuentaTambienLosCorreosQueNoExisten(): void {
        $c = "fantasma." . bin2hex(random_bytes(3)) . "@example.invalid";
        for ($i = 1; $i <= 4; $i++) {
            $this->assertSame(["correo" => $i - 1, "ip" => $i - 1], $this->m->solicitudesRecientes($c, "203.0.113.9"));
            $this->m->registrarSolicitud($c, "203.0.113.9", null, null);
        }
        $r = $this->m->solicitudesRecientes($c, "203.0.113.9");
        $this->assertSame(4, $r["correo"]);
        $this->assertGreaterThan(\PasswordResetModel::MAX_POR_CORREO, $r["correo"]);
    }

    public function testLosLimitesSonTresPorCorreoYDiezPorIp(): void {
        $this->assertSame(3, \PasswordResetModel::MAX_POR_CORREO);
        $this->assertSame(10, \PasswordResetModel::MAX_POR_IP);
        $this->assertSame(60, \PasswordResetModel::VENTANA_MIN);
        $this->assertSame(30, \PasswordResetModel::TTL_MIN);
    }

    public function testElCorreoSeCuentaSinImportarMayusculas(): void {
        $c = "Mayus." . bin2hex(random_bytes(3)) . "@Example.INVALID";
        $this->m->registrarSolicitud($c, "203.0.113.20", null, null);
        $this->assertSame(1, $this->m->solicitudesRecientes(strtolower($c), "203.0.113.21")["correo"]);
        $this->assertSame(1, $this->m->solicitudesRecientes(strtoupper($c), "203.0.113.21")["correo"]);
    }

    public function testElLimitePorIpCuentaCorreosDistintos(): void {
        for ($i = 0; $i < 11; $i++) $this->m->registrarSolicitud("n$i." . bin2hex(random_bytes(3)) . "@example.invalid", "203.0.113.30", null, null);
        $this->assertSame(11, $this->m->solicitudesRecientes("otro@example.invalid", "203.0.113.30")["ip"]);
        $this->assertSame(0, $this->m->solicitudesRecientes("otro@example.invalid", "203.0.113.31")["ip"], "otra IP empieza en cero");
    }

    public function testLasSolicitudesViejasDejanDeContar(): void {
        $c = "viejo." . bin2hex(random_bytes(3)) . "@example.invalid";
        $id = $this->m->registrarSolicitud($c, "203.0.113.40", null, null);
        $this->db->exec("UPDATE password_resets SET creado_en = NOW() - INTERVAL 59 MINUTE WHERE id = $id");
        $this->assertSame(1, $this->m->solicitudesRecientes($c, "203.0.113.40")["correo"], "a los 59 minutos todavía cuenta");
        $this->db->exec("UPDATE password_resets SET creado_en = NOW() - INTERVAL 61 MINUTE WHERE id = $id");
        $this->assertSame(["correo" => 0, "ip" => 0], $this->m->solicitudesRecientes($c, "203.0.113.40"));
    }

    public function testUnaSolicitudSinCuentaNoLlevaTokenNiCaducidad(): void {
        $id = $this->m->registrarSolicitud("nadie@example.invalid", "203.0.113.50", null, null);
        $f = $this->fila($id);
        $this->assertNull($f["usuario_id"]);
        $this->assertNull($f["token_hash"]);
        $this->assertNull($f["expira_en"]);
    }

    public function testVariasSolicitudesSinTokenConviven(): void {
        // token_hash es UNIQUE pero NULL no choca consigo mismo
        for ($i = 0; $i < 3; $i++) $this->m->registrarSolicitud("nadie@example.invalid", "203.0.113.51", null, null);
        $this->assertSame(3, $this->m->solicitudesRecientes("nadie@example.invalid", "203.0.113.51")["correo"]);
    }

    public function testLimpiarViejosBorraSoloLoDeMasDeUnDia(): void {
        $viejo = $this->m->registrarSolicitud("a@example.invalid", "203.0.113.60", null, null);
        $ayer  = $this->m->registrarSolicitud("b@example.invalid", "203.0.113.60", null, null);
        $hoy   = $this->m->registrarSolicitud("c@example.invalid", "203.0.113.60", null, null);
        $this->db->exec("UPDATE password_resets SET creado_en = NOW() - INTERVAL 25 HOUR WHERE id = $viejo");
        $this->db->exec("UPDATE password_resets SET creado_en = NOW() - INTERVAL 23 HOUR WHERE id = $ayer");
        $this->m->limpiarViejos();
        $this->assertFalse($this->m->findById($viejo));
        $this->assertNotFalse($this->m->findById($ayer));
        $this->assertNotFalse($this->m->findById($hoy));
    }

    public function testBorrarLaCuentaBorraSusEnlaces(): void {
        $u = $this->usuario();
        [, , $id] = $this->pedir($u);
        $this->db->exec("DELETE FROM usuarios WHERE id = " . (int)$u["id"]);
        $this->assertFalse($this->m->findById($id));
    }
}
