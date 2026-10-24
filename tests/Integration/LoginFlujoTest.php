<?php
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatos;
use Tests\Support\Proceso;

/**
 * AuthController::doLogin real, en un proceso aparte (termina en exit): el tercer fallo bloquea,
 * el mensaje es genérico, el intento bloqueado no se registra y la espera se acaba sola.
 * Usa commits (el proceso hijo es otra conexión) y borra lo suyo al final por ids y correos exactos.
 */
final class LoginFlujoTest extends TestCase {
    private const CLAVE = "Frase-segura-de-prueba-2026";
    private PDO $db;
    private string $email;
    private string $ip;
    private ?int $uid = null;
    private array $correos = [];

    protected function setUp(): void {
        parent::setUp();
        $this->db = \Database::get();
        BaseDatos::verificarBase($this->db);
        $this->assertFalse($this->db->inTransaction());
        $this->email = "login." . bin2hex(random_bytes(4)) . "@example.invalid";
        $this->ip    = "198.51.100." . random_int(1, 254);
        $this->correos = [$this->email];
        $this->db->prepare("INSERT INTO usuarios (nombre, email, password) VALUES ('Login de prueba', ?, ?)")
                 ->execute([$this->email, password_hash(self::CLAVE, PASSWORD_BCRYPT)]);
        $this->uid = (int)$this->db->lastInsertId();
    }

    protected function tearDown(): void {
        $in = implode(",", array_fill(0, count($this->correos), "?"));
        $this->db->prepare("DELETE FROM login_logs WHERE email_intento IN ($in)")->execute($this->correos);
        if ($this->uid) $this->db->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$this->uid]);
        parent::tearDown();
    }

    private function login(string $email, string $clave): array {
        return Proceso::accion(["clase" => "AuthController", "accion" => "doLogin", "csrf" => true, "ip" => $this->ip,
            "post" => ["email" => $email, "password" => $clave]]);
    }

    private function filas(string $email): int {
        $s = $this->db->prepare("SELECT COUNT(*) FROM login_logs WHERE email_intento = ?");
        $s->execute([$email]);
        return (int)$s->fetchColumn();
    }

    public function testElTercerFalloBloqueaAunqueLaClaveSeaCorrecta(): void {
        for ($i = 1; $i <= 3; $i++) {
            $r = $this->login($this->email, "clave equivocada $i");
            $this->assertSame("Correo o contraseña incorrectos.", $r["sesion"]["login_error"], "intento $i");
            $this->assertArrayNotHasKey("user", $r["sesion"]);
            if (Proceso::veCabeceras()) $this->assertStringEndsWith("/login.php", (string)Proceso::ubicacion($r), "redirige al login");
        }
        $this->assertSame(3, $this->filas($this->email));

        // Cuarto intento, ya con la clave buena: bloqueado, sin sesión y sin registrar el intento
        $r = $this->login($this->email, self::CLAVE);
        $this->assertStringContainsString("Demasiados intentos fallidos", $r["sesion"]["login_error"]);
        $this->assertArrayNotHasKey("user", $r["sesion"]);
        $this->assertSame(3, $this->filas($this->email), "el intento bloqueado no alarga la espera");
    }

    public function testPasadaLaVentanaSePuedeEntrar(): void {
        for ($i = 1; $i <= 3; $i++) $this->login($this->email, "mala $i");
        $this->db->prepare("UPDATE login_logs SET fecha = NOW() - INTERVAL 16 MINUTE WHERE email_intento = ?")->execute([$this->email]);

        $r = $this->login($this->email, self::CLAVE);
        $this->assertSame($this->uid, (int)$r["sesion"]["user"]["id"], "entró");
        $this->assertSame($this->email, $r["sesion"]["user"]["email"]);
        if (Proceso::veCabeceras()) $this->assertStringEndsWith("/rastroinventario/", (string)Proceso::ubicacion($r), "una cuenta sin rol de personal va a la tienda");
        $this->assertSame(4, $this->filas($this->email), "queda el éxito en la bitácora");
    }

    public function testDosFallosNoBloquean(): void {
        $this->login($this->email, "mala 1");
        $this->login($this->email, "mala 2");
        $r = $this->login($this->email, self::CLAVE);
        $this->assertSame($this->uid, (int)$r["sesion"]["user"]["id"]);
    }

    public function testUnCorreoQueNoExisteSeBloqueaIgualYConElMismoMensaje(): void {
        $fantasma = "fantasma." . bin2hex(random_bytes(4)) . "@example.invalid";
        $this->correos[] = $fantasma;
        for ($i = 1; $i <= 3; $i++) {
            $r = $this->login($fantasma, "cualquiera $i");
            $this->assertSame("Correo o contraseña incorrectos.", $r["sesion"]["login_error"], "mismo mensaje que con un correo real");
        }
        $r = $this->login($fantasma, "otra");
        $this->assertStringContainsString("Demasiados intentos fallidos", $r["sesion"]["login_error"]);
    }

    public function testElBloqueoEsPorIp(): void {
        for ($i = 1; $i <= 3; $i++) $this->login($this->email, "mala $i");
        $r = Proceso::accion(["clase" => "AuthController", "accion" => "doLogin", "csrf" => true, "ip" => "192.0.2.77",
            "post" => ["email" => $this->email, "password" => self::CLAVE]]);
        $this->assertSame($this->uid, (int)$r["sesion"]["user"]["id"], "desde otra IP el mismo correo entra");
        $this->correos[] = $this->email;
    }

    public function testSinTokenCsrfNoSeProcesaNada(): void {
        $r = Proceso::accion(["clase" => "AuthController", "accion" => "doLogin", "csrf" => false, "ip" => $this->ip,
            "post" => ["email" => $this->email, "password" => self::CLAVE]]);
        $this->assertSame(403, $r["codigo"]);
        $this->assertArrayNotHasKey("user", $r["sesion"]);
        $this->assertSame(0, $this->filas($this->email));
    }

    public function testUnaClaveEnFormaDeArregloCuentaComoFallo(): void {
        $r = Proceso::accion(["clase" => "AuthController", "accion" => "doLogin", "csrf" => true, "ip" => $this->ip,
            "post" => ["email" => $this->email, "password" => [self::CLAVE]]]);
        $this->assertSame("Correo o contraseña incorrectos.", $r["sesion"]["login_error"]);
        $this->assertArrayNotHasKey("user", $r["sesion"]);
    }
}
