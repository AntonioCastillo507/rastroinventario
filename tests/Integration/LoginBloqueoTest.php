<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;

/** Bloqueo temporal del login (2B-4): LogModel::fallosRecientes con las reglas de AuthController */
final class LoginBloqueoTest extends BaseDatos {
    private \LogModel $log;
    private int $max;
    private int $ventana;

    protected function setUp(): void {
        parent::setUp();
        $this->log     = new \LogModel();
        $this->max     = (new \ReflectionClassConstant(\AuthController::class, "MAX_INTENTOS"))->getValue();
        $this->ventana = (new \ReflectionClassConstant(\AuthController::class, "VENTANA_MIN"))->getValue();
    }

    private function fallo(string $email, string $ip, int $minutosAtras = 0, bool $exitoso = false): void {
        $this->db->prepare("INSERT INTO login_logs (usuario_id, email_intento, ip, exitoso, fecha) VALUES (NULL, ?, ?, ?, NOW() - INTERVAL ? MINUTE)")
                 ->execute([$email, $ip, $exitoso ? 1 : 0, $minutosAtras]);
    }

    private function bloqueado(string $email, string $ip): bool {
        return $this->log->fallosRecientes($email, $ip, $this->ventana) >= $this->max;
    }

    public function testLasReglasSonTresIntentosEnQuinceMinutos(): void {
        $this->assertSame(3, $this->max);
        $this->assertSame(15, $this->ventana);
    }

    public function testCuentaLosFallosRecientes(): void {
        $e = "ana@example.invalid";
        $ip = "203.0.113.10";
        $this->assertSame(0, $this->log->fallosRecientes($e, $ip, 15));
        $this->assertFalse($this->bloqueado($e, $ip));

        $this->log->registrar(["usuario_id" => null, "email" => $e, "ip" => $ip, "exitoso" => false]);
        $this->log->registrar(["usuario_id" => null, "email" => $e, "ip" => $ip, "exitoso" => false]);
        $this->assertSame(2, $this->log->fallosRecientes($e, $ip, 15));
        $this->assertFalse($this->bloqueado($e, $ip), "dos fallos todavía dejan intentar");

        $this->log->registrar(["usuario_id" => null, "email" => $e, "ip" => $ip, "exitoso" => false]);
        $this->assertSame(3, $this->log->fallosRecientes($e, $ip, 15));
        $this->assertTrue($this->bloqueado($e, $ip), "el tercer fallo bloquea");
    }

    public function testLosFallosViejosNoCuentan(): void {
        $e = "ana@example.invalid";
        $ip = "203.0.113.11";
        foreach ([$this->ventana - 1, $this->ventana + 1, $this->ventana + 60] as $min) $this->fallo($e, $ip, $min);
        $this->assertSame(1, $this->log->fallosRecientes($e, $ip, $this->ventana), "solo el de hace " . ($this->ventana - 1) . " min entra en la ventana");

        $this->fallo($e, $ip, $this->ventana + 5);
        $this->fallo($e, $ip, $this->ventana + 5);
        $this->assertFalse($this->bloqueado($e, $ip), "pasada la ventana se puede volver a intentar");
    }

    public function testLosIntentosExitososNoCuentan(): void {
        $e = "ana@example.invalid";
        $ip = "203.0.113.12";
        for ($i = 0; $i < 5; $i++) $this->log->registrar(["usuario_id" => null, "email" => $e, "ip" => $ip, "exitoso" => true]);
        $this->assertSame(0, $this->log->fallosRecientes($e, $ip, 15));
    }

    public function testElContadorEsPorCorreoYPorIp(): void {
        $ip = "203.0.113.13";
        for ($i = 0; $i < 3; $i++) $this->fallo("ana@example.invalid", $ip);
        $this->assertTrue($this->bloqueado("ana@example.invalid", $ip));
        $this->assertFalse($this->bloqueado("luis@example.invalid", $ip), "otro correo desde la misma IP");
        $this->assertFalse($this->bloqueado("ana@example.invalid", "203.0.113.99"), "el mismo correo desde otra IP");
    }

    public function testTambienCuentanLosCorreosQueNoExisten(): void {
        $ip = "203.0.113.14";
        for ($i = 0; $i < 3; $i++) $this->fallo("no.existe@example.invalid", $ip);
        $this->assertTrue($this->bloqueado("no.existe@example.invalid", $ip), "bloquear solo cuentas reales delataría cuáles existen");
    }

    public function testRegistrarGuardaLosDatosDelIntento(): void {
        $this->log->registrar(["usuario_id" => null, "email" => "z@example.invalid", "ip" => "203.0.113.15", "exitoso" => true]);
        $fila = $this->db->query("SELECT * FROM login_logs WHERE email_intento = 'z@example.invalid'")->fetch();
        $this->assertSame("203.0.113.15", $fila["ip"]);
        $this->assertSame(1, (int)$fila["exitoso"]);
        $this->assertNull($fila["usuario_id"]);
    }
}
