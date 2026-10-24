<?php
/**
 * PasswordResetModel — Recuperación de contraseña (hallazgo 14b).
 * El token (256 bits) solo existe en el correo: en la base se guarda su SHA-256.
 * Una fila por solicitud con correo válido, exista o no la cuenta, para que los límites
 * por correo y por IP cuenten igual y la base haga el mismo trabajo en los dos casos.
 */
class PasswordResetModel extends BaseModel {
    protected string $table = "password_resets";

    public const TTL_MIN         = 30; // vida del enlace
    public const VENTANA_MIN     = 60; // ventana de los límites de solicitudes
    public const MAX_POR_CORREO  = 3;
    public const MAX_POR_IP      = 10;

    /** 43 caracteres base64url (32 bytes aleatorios) */
    public static function generarToken(): string {
        return rtrim(strtr(base64_encode(random_bytes(32)), "+/", "-_"), "=");
    }
    public static function hash(string $token): string {
        return hash("sha256", $token);
    }
    /** Solo texto con la forma exacta de un token; un arreglo, un byte nulo o un largo raro nunca llegan a la base */
    public static function formatoValido(mixed $token): bool {
        return is_string($token) && preg_match('/\A[A-Za-z0-9_-]{43}\z/', $token) === 1;
    }
    /** El enlace del correo se arma con APP_URL, nunca con el encabezado Host */
    public static function enlace(string $token, string $appUrl): string {
        return rtrim($appUrl, "/") . "/restablecer.php?token=" . $token;
    }
    public static function normalizarCorreo(string $email): string {
        return mb_strtolower(trim($email), "UTF-8");
    }

    /** Solicitudes (con o sin cuenta) de ese correo y de esa IP en la ventana */
    public function solicitudesRecientes(string $email, string $ip): array {
        $r = $this->query(
            "SELECT
               (SELECT COUNT(*) FROM password_resets WHERE email=? AND creado_en > (NOW() - INTERVAL ? MINUTE)) AS correo,
               (SELECT COUNT(*) FROM password_resets WHERE ip=?    AND creado_en > (NOW() - INTERVAL ? MINUTE)) AS ip",
            [self::normalizarCorreo($email), self::VENTANA_MIN, $ip, self::VENTANA_MIN]
        );
        return ["correo" => (int)$r[0]["correo"], "ip" => (int)$r[0]["ip"]];
    }

    /**
     * Anota la solicitud. Con $usuarioId y $tokenHash la fila lleva el token (y los pendientes de esa
     * cuenta quedan invalidados: solo vale el último enlace); sin ellos es una fila que solo cuenta.
     */
    public function registrarSolicitud(string $email, string $ip, ?int $usuarioId, ?string $tokenHash): int {
        $propia = !$this->db->inTransaction();
        if ($propia) $this->db->beginTransaction();
        try {
            if ($usuarioId !== null && $tokenHash !== null) {
                $this->exec("UPDATE password_resets SET usado_en=NOW() WHERE usuario_id=? AND usado_en IS NULL AND token_hash IS NOT NULL", [$usuarioId]);
            }
            $this->exec(
                "INSERT INTO password_resets (usuario_id,email,token_hash,ip,expira_en)
                 VALUES (?,?,?,?, IF(? IS NULL, NULL, NOW() + INTERVAL ? MINUTE))",
                [$usuarioId, self::normalizarCorreo($email), $tokenHash, $ip, $tokenHash, self::TTL_MIN]
            );
            $id = (int)$this->db->lastInsertId();
            if ($propia) $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($propia && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /** El enlace sirve si no se usó, no caducó y la cuenta sigue activa y sin bloqueo. No consume nada. */
    public function buscarValido(string $tokenHash): array|false {
        $r = $this->query(
            "SELECT r.id, r.usuario_id, u.email, u.nombre
             FROM password_resets r INNER JOIN usuarios u ON u.id=r.usuario_id
             WHERE r.token_hash=? AND r.usado_en IS NULL AND r.expira_en > NOW()
               AND u.activo=1 AND u.bloqueado=0",
            [$tokenHash]
        );
        return $r[0] ?? false;
    }

    /**
     * Consume el token y cambia la clave en una sola transacción. El token se canjea con un UPDATE
     * atómico (rowCount()==1): dos canjes a la vez no pueden ganar los dos. Devuelve false sin
     * cambiar nada si el token ya no vale o la cuenta dejó de estar activa.
     */
    public function completar(string $tokenHash, string $claveHash): bool {
        $propia = !$this->db->inTransaction();
        if ($propia) $this->db->beginTransaction();
        else $this->db->exec("SAVEPOINT completar_reset"); // dentro de otra transacción (pruebas): deshacer solo lo nuestro
        try {
            $s = $this->db->prepare("UPDATE password_resets SET usado_en=NOW() WHERE token_hash=? AND usado_en IS NULL AND expira_en > NOW()");
            $s->execute([$tokenHash]);
            if ($s->rowCount() !== 1) return $this->abortar($propia);

            $uid = $this->db->prepare("SELECT usuario_id FROM password_resets WHERE token_hash=?");
            $uid->execute([$tokenHash]);
            $usuarioId = (int)$uid->fetchColumn();

            $u = $this->db->prepare("UPDATE usuarios SET password=? WHERE id=? AND activo=1 AND bloqueado=0");
            $u->execute([$claveHash, $usuarioId]);
            if ($u->rowCount() !== 1) return $this->abortar($propia);

            // Con la clave cambiada, ningún otro enlace pendiente de esa cuenta debe seguir vivo
            $this->exec("UPDATE password_resets SET usado_en=NOW() WHERE usuario_id=? AND usado_en IS NULL AND token_hash IS NOT NULL", [$usuarioId]);
            if ($propia) $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($propia && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /** Borra solicitudes de más de un día (los límites solo miran la última hora) */
    public function limpiarViejos(): void {
        $this->exec("DELETE FROM password_resets WHERE creado_en < (NOW() - INTERVAL 1 DAY)");
    }

    private function abortar(bool $propia): bool {
        if ($propia && $this->db->inTransaction()) $this->db->rollBack();
        elseif (!$propia) $this->db->exec("ROLLBACK TO SAVEPOINT completar_reset");
        return false;
    }
}
