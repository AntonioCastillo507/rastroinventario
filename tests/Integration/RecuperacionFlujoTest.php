<?php
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatos;
use Tests\Support\Proceso;

/**
 * Recuperación de contraseña de punta a punta: PasswordResetController real en un proceso aparte (termina
 * en exit), con el envío de correo sustituido. Usa commits (el hijo es otra conexión) y borra lo suyo al
 * final por correos, IPs e ids exactos. Incluye los casos de abuso.
 */
final class RecuperacionFlujoTest extends TestCase {
    private const CLAVE_VIEJA = "Clave-vieja-de-prueba-2026";
    private const CLAVE_NUEVA = "Frase nueva para el flujo 2026";
    private PDO $db;
    private string $email;
    private string $ip;
    private string $logs;
    private array $correos = [];
    private array $ips = [];
    private array $uids = [];
    private int $uid;

    protected function setUp(): void {
        parent::setUp();
        $this->db = \Database::get();
        BaseDatos::verificarBase($this->db);
        $this->assertFalse($this->db->inTransaction());
        $this->logs = RASTRO_TEST_TMP . DIRECTORY_SEPARATOR . "rec_" . uniqid();
        mkdir($this->logs, 0777, true);
        $this->ip = $this->ipNueva();
        [$this->uid, $this->email] = $this->crearUsuario();
    }

    protected function tearDown(): void {
        if ($this->correos) {
            $in = implode(",", array_fill(0, count($this->correos), "?"));
            $this->db->prepare("DELETE FROM password_resets WHERE email IN ($in)")->execute($this->correos);
            $this->db->prepare("DELETE FROM login_logs WHERE email_intento IN ($in)")->execute($this->correos);
        }
        if ($this->ips) {
            $in = implode(",", array_fill(0, count($this->ips), "?"));
            $this->db->prepare("DELETE FROM password_resets WHERE ip IN ($in)")->execute($this->ips);
            $this->db->prepare("DELETE FROM login_logs WHERE ip IN ($in)")->execute($this->ips);
        }
        foreach ($this->uids as $id) $this->db->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id]);
        foreach (glob($this->logs . "/*") ?: [] as $f) @unlink($f);
        @rmdir($this->logs);
        parent::tearDown();
    }

    private function ipNueva(): string {
        // Tres octetos al azar para que dos pruebas no compartan el contador de IP
        $ip = "10." . random_int(100, 250) . "." . random_int(1, 254) . "." . random_int(1, 254);
        $this->ips[] = $ip;
        return $ip;
    }

    /** @return array{0:int,1:string} */
    private function crearUsuario(int $activo = 1, int $bloqueado = 0, string $nombre = "Cuenta de Recuperación"): array {
        $email = "rec." . bin2hex(random_bytes(4)) . "@example.invalid";
        $this->db->prepare("INSERT INTO usuarios (nombre, email, password, activo, bloqueado) VALUES (?, ?, ?, ?, ?)")
                 ->execute([$nombre, $email, password_hash(self::CLAVE_VIEJA, PASSWORD_BCRYPT), $activo, $bloqueado]);
        $id = (int)$this->db->lastInsertId();
        $this->uids[] = $id;
        $this->correos[] = $email;
        return [$id, $email];
    }

    private function solicitar(string $email, ?string $ip = null, string $correo = "ok", array $extra = [], bool $csrf = true): array {
        return Proceso::accion($extra + ["clase" => "PasswordResetController", "accion" => "procesarOlvide", "csrf" => $csrf,
            "ip" => $ip ?? $this->ip, "post" => ["email" => $email], "correo" => $correo, "dir_logs" => $this->logs]);
    }

    private function abrir(mixed $token, array $sesion = []): array {
        return Proceso::accion(["clase" => "PasswordResetController", "accion" => "restablecer", "metodo_http" => "GET",
            "get" => ["token" => $token], "sesion" => $sesion, "ip" => $this->ip]);
    }

    private function verFormulario(array $sesion): array {
        return Proceso::accion(["clase" => "PasswordResetController", "accion" => "restablecer", "metodo_http" => "GET",
            "sesion" => $sesion, "ip" => $this->ip]);
    }

    private function cambiar(array $sesion, mixed $p1, mixed $p2, bool $csrf = true): array {
        return Proceso::accion(["clase" => "PasswordResetController", "accion" => "procesarRestablecer", "csrf" => $csrf,
            "sesion" => $sesion, "ip" => $this->ip, "post" => ["password" => $p1, "password2" => $p2]]);
    }

    private function entrar(string $email, string $clave): array {
        return Proceso::accion(["clase" => "AuthController", "accion" => "doLogin", "csrf" => true, "ip" => $this->ipNueva(),
            "post" => ["email" => $email, "password" => $clave]]);
    }

    private function tokenDelCorreo(array $r): string {
        $this->assertCount(1, $r["correos"], "debía salir un correo");
        $url = $r["correos"][0]["params"]["reset_url"];
        parse_str((string)parse_url($url, PHP_URL_QUERY), $q);
        return $q["token"];
    }

    private function filasDe(string $email): array {
        $s = $this->db->prepare("SELECT * FROM password_resets WHERE email = ? ORDER BY id");
        $s->execute([$email]);
        return $s->fetchAll();
    }

    private function clave(int $uid): string {
        $s = $this->db->prepare("SELECT password FROM usuarios WHERE id = ?");
        $s->execute([$uid]);
        return $s->fetchColumn();
    }

    /** Pide el enlace y lo abre: devuelve [token, sesión lista para el formulario] */
    private function enlaceAbierto(): array {
        $t = $this->tokenDelCorreo($this->solicitar($this->email));
        $r = $this->abrir($t);
        $this->assertArrayHasKey("reset_hash", $r["sesion"]);
        return [$t, $r["sesion"]];
    }

    // ── Pedir el enlace ──────────────────────────────────────────────
    public function testUnCorreoExistenteGuardaSoloElHashYMandaElEnlace(): void {
        $r = $this->solicitar($this->email);
        $token = $this->tokenDelCorreo($r);
        $this->assertMatchesRegularExpression('/\A[A-Za-z0-9_-]{43}\z/', $token);
        $c = $r["correos"][0];
        $this->assertSame($this->email, $c["params"]["to_email"]);
        $this->assertSame(30, $c["params"]["expira_minutos"]);
        $this->assertSame(APP_URL . "/restablecer.php?token=$token", $c["params"]["reset_url"]);

        $filas = $this->filasDe($this->email);
        $this->assertCount(1, $filas);
        $this->assertSame(hash("sha256", $token), $filas[0]["token_hash"]);
        $this->assertSame($this->uid, (int)$filas[0]["usuario_id"]);
        $this->assertSame($this->ip, $filas[0]["ip"]);
        $this->assertStringNotContainsString($token, json_encode($filas));
        $this->assertSame(\PasswordResetController::MSG_SOLICITUD, $r["sesion"]["reset_ok"]);
        $this->assertArrayNotHasKey("user", $r["sesion"]);
    }

    public function testElCorreoSaleDespuesDeCerrarLaSesionYDeResponder(): void {
        $r = $this->solicitar($this->email);
        $this->assertFalse($r["correos"][0]["sesion_activa"], "la sesión ya estaba suelta cuando se envió");
        if (Proceso::veCabeceras()) {
            $this->assertStringEndsWith("/olvide.php", (string)Proceso::ubicacion($r));
            $this->assertContains("Content-Length: 0", $r["cabeceras"]);
            $this->assertContains("Connection: close", $r["cabeceras"]);
        }
    }

    public function testUnCorreoInexistenteDaExactamenteLaMismaRespuesta(): void {
        $real = $this->solicitar($this->email, $this->ipNueva());
        $fantasma = "fantasma." . bin2hex(random_bytes(4)) . "@example.invalid";
        $this->correos[] = $fantasma;
        $falso = $this->solicitar($fantasma, $this->ipNueva());

        $this->assertSame($real["sesion"], $falso["sesion"], "mismo mensaje y mismas claves de sesión");
        $this->assertSame($real["codigo"], $falso["codigo"]);
        $this->assertSame(Proceso::ubicacion($real), Proceso::ubicacion($falso));
        $this->assertSame($real["salida"], $falso["salida"]);
        $this->assertCount(1, $real["correos"]);
        $this->assertSame([], $falso["correos"], "al inexistente no se le manda nada");
        // La base hace el mismo trabajo: una fila en cada caso (sin cuenta ni token en la del fantasma)
        $f = $this->filasDe($fantasma);
        $this->assertCount(1, $f);
        $this->assertNull($f[0]["usuario_id"]);
        $this->assertNull($f[0]["token_hash"]);
        $this->assertFileDoesNotExist($this->logs . "/reset_dev.log", "con envío correcto no se escribe el enlace de desarrollo");
    }

    public static function cuentasSinEnvio(): array {
        return ["desactivada" => [0, 0], "bloqueada" => [1, 1], "desactivada y bloqueada" => [0, 1]];
    }

    #[DataProvider("cuentasSinEnvio")]
    public function testUnaCuentaDesactivadaOBloqueadaSeTrataComoSiNoExistiera(int $activo, int $bloqueado): void {
        [, $mail] = $this->crearUsuario($activo, $bloqueado);
        $real = $this->solicitar($this->email, $this->ipNueva());
        $r = $this->solicitar($mail, $this->ipNueva());
        $this->assertSame($real["sesion"], $r["sesion"]);
        $this->assertSame([], $r["correos"]);
        $f = $this->filasDe($mail);
        $this->assertCount(1, $f);
        $this->assertNull($f[0]["token_hash"]);
    }

    public function testLasMayusculasDelCorreoNoImportan(): void {
        $r = $this->solicitar(strtoupper($this->email));
        $this->assertCount(1, $r["correos"]);
        $this->assertSame($this->email, $r["correos"][0]["params"]["to_email"], "se escribe a la dirección guardada");
    }

    public function testElTerceroCorreoPasaYElCuartoNoEnviaNiAnotaNada(): void {
        for ($i = 1; $i <= 3; $i++) $this->assertCount(1, $this->solicitar($this->email, $this->ipNueva())["correos"], "solicitud $i");
        $cuarta = $this->solicitar($this->email, $this->ipNueva());
        $this->assertSame([], $cuarta["correos"]);
        $this->assertSame(\PasswordResetController::MSG_SOLICITUD, $cuarta["sesion"]["reset_ok"], "la misma respuesta de siempre");
        $this->assertCount(3, $this->filasDe($this->email), "la solicitud de más no alarga la espera");
    }

    public function testElLimitePorCorreoEsIgualParaUnCorreoQueNoExiste(): void {
        $fantasma = "fantasma." . bin2hex(random_bytes(4)) . "@example.invalid";
        $this->correos[] = $fantasma;
        $respuestas = [];
        for ($i = 1; $i <= 4; $i++) $respuestas[] = $this->solicitar($fantasma, $this->ipNueva())["sesion"];
        $this->assertCount(1, array_unique(array_map("serialize", $respuestas)), "las 4 respuestas son idénticas");
        $this->assertCount(3, $this->filasDe($fantasma));
    }

    public function testElLimitePorIpTieneSuPropioMensajeYOtraIpSigueFuncionando(): void {
        $ipMala = $this->ipNueva();
        for ($i = 0; $i < 10; $i++) {
            $c = "volumen$i." . bin2hex(random_bytes(3)) . "@example.invalid";
            $this->correos[] = $c;
            $this->solicitar($c, $ipMala);
        }
        $r = $this->solicitar($this->email, $ipMala);
        $this->assertSame(\PasswordResetController::MSG_LIMITE_IP, $r["sesion"]["reset_error"]);
        $this->assertArrayNotHasKey("reset_ok", $r["sesion"]);
        $this->assertSame([], $r["correos"]);
        $this->assertSame([], $this->filasDe($this->email), "ni siquiera se anota");
        $this->assertCount(1, $this->solicitar($this->email, $this->ipNueva())["correos"], "desde otra IP sí");
    }

    public static function correosMalos(): array {
        return [
            "vacío" => [""], "sin arroba" => ["ana.example.invalid"], "sin dominio" => ["ana@"],
            "más de 150 caracteres" => [str_repeat("a", 145) . "@example.invalid"],
        ];
    }

    #[DataProvider("correosMalos")]
    public function testUnCorreoMalFormadoDaUnErrorDeFormatoYNoCuenta(string $malo): void {
        $r = $this->solicitar($malo);
        $this->assertSame("Escribe un correo electrónico válido.", $r["sesion"]["reset_error"]);
        $this->assertArrayNotHasKey("reset_ok", $r["sesion"]);
        $this->assertSame([], $r["correos"]);
        $n = $this->db->prepare("SELECT COUNT(*) FROM password_resets WHERE ip = ?");
        $n->execute([$this->ip]);
        $this->assertSame(0, (int)$n->fetchColumn());
    }

    public function testUnCorreoEnFormaDeArregloNoRompeNada(): void {
        $r = Proceso::accion(["clase" => "PasswordResetController", "accion" => "procesarOlvide", "csrf" => true, "ip" => $this->ip,
            "post" => ["email" => [$this->email]], "correo" => "ok", "dir_logs" => $this->logs]);
        $this->assertSame("Escribe un correo electrónico válido.", $r["sesion"]["reset_error"]);
        $this->assertSame([], $r["correos"]);
    }

    public function testSinTokenCsrfNoSeProcesaNada(): void {
        $r = $this->solicitar($this->email, null, "ok", [], false);
        $this->assertSame(403, $r["codigo"]);
        $this->assertSame([], $r["correos"]);
        $this->assertSame([], $this->filasDe($this->email));
    }

    public function testUnHostHostilNoCambiaElEnlace(): void {
        $r = $this->solicitar($this->email, null, "ok", ["servidor" => [
            "HTTP_HOST" => "evil.test", "SERVER_NAME" => "evil.test", "HTTP_X_FORWARDED_HOST" => "evil.test",
            "HTTP_ORIGIN" => "https://evil.test", "HTTP_REFERER" => "https://evil.test/"]]);
        $url = $r["correos"][0]["params"]["reset_url"];
        $this->assertStringStartsWith(APP_URL . "/restablecer.php?token=", $url);
        $this->assertStringNotContainsString("evil", $url);
    }

    public function testSiElEnvioFallaEnLocalElEnlaceQuedaEnElRegistroDeDesarrollo(): void {
        $r = $this->solicitar($this->email, null, "falla");
        $token = $this->tokenDelCorreo($r);
        $this->assertSame(\PasswordResetController::MSG_SOLICITUD, $r["sesion"]["reset_ok"], "la persona no se entera del fallo");
        $log = file_get_contents($this->logs . "/reset_dev.log");
        $this->assertStringContainsString($this->email . " " . APP_URL . "/restablecer.php?token=$token", $log);
        $this->assertSame(hash("sha256", $token), $this->filasDe($this->email)[0]["token_hash"], "es el token de la fila");
    }

    public function testNadaDeLaSolicitudVaAlRegistroDeEmailJsNiAlDeErrores(): void {
        $token = $this->tokenDelCorreo($this->solicitar($this->email, null, "falla"));
        foreach ([$this->logs . "/emailjs.log", RASTRO_TEST_TMP . "/error.log"] as $archivo) {
            if (is_file($archivo)) $this->assertStringNotContainsString($token, file_get_contents($archivo), $archivo);
        }
    }

    public function testUnaPersonaConSesionIniciadaVaALaTienda(): void {
        $r = Proceso::accion(["clase" => "PasswordResetController", "accion" => "olvide", "metodo_http" => "GET",
            "sesion" => ["user" => ["id" => $this->uid, "nombre" => "x", "email" => $this->email]]]);
        if (Proceso::veCabeceras()) $this->assertStringEndsWith("/rastroinventario/", (string)Proceso::ubicacion($r));
        $this->assertStringNotContainsString("<form", $r["salida"]);
    }

    // ── Usar el enlace ───────────────────────────────────────────────
    public function testAbrirElEnlacePasaElHashALaSesionYLimpiaLaUrl(): void {
        $t = $this->tokenDelCorreo($this->solicitar($this->email));
        $r = $this->abrir($t);
        $this->assertSame(hash("sha256", $t), $r["sesion"]["reset_hash"]);
        $this->assertStringNotContainsString($t, json_encode($r["sesion"]), "el token en claro no queda en la sesión");
        $this->assertNotSame($r["sid"]["inicial"], $r["sid"]["final"], "el identificador de sesión se renueva al abrir el enlace");
        $this->assertArrayNotHasKey("reset_error", $r["sesion"]);
        if (Proceso::veCabeceras()) {
            $this->assertSame(APP_URL . "/restablecer.php", Proceso::ubicacion($r), "sin token en la URL");
            $this->assertContains("Referrer-Policy: no-referrer", $r["cabeceras"]);
            $this->assertContains("Cache-Control: no-store", $r["cabeceras"]);
        }
        $this->assertNull($this->filasDe($this->email)[0]["usado_en"], "mirar el enlace no lo gasta");
        $otra = $this->abrir($t);
        $this->assertArrayHasKey("reset_hash", $otra["sesion"], "un escáner de enlaces no lo estropea");
    }

    public static function tokensMalos(): array {
        return [
            "inventado"        => [str_repeat("a", 43)],
            "otro de forma ok" => ["Zm9vYmFyIGJhcmJheiBmb29iYXIgYmFyYmF6IGZvbw"],
            "42 caracteres"    => [str_repeat("a", 42)],
            "44 caracteres"    => [str_repeat("a", 44)],
            "con símbolos"     => ["' OR 1=1 -- " . str_repeat("a", 31)],
            "byte nulo"        => [str_repeat("a", 42) . "\0"],
            "arreglo"          => [[str_repeat("a", 43)]],
            "vacío"            => [""],
        ];
    }

    #[DataProvider("tokensMalos")]
    public function testUnTokenMaloDaElMensajeDeSiempreYNoDejaNadaEnLaSesion(mixed $malo): void {
        $r = $this->abrir($malo, ["reset_hash" => hash("sha256", "viejo")]);
        $this->assertArrayNotHasKey("reset_hash", $r["sesion"], "incluso borra un hash anterior");
        $this->assertSame(\PasswordResetController::MSG_ENLACE, $r["sesion"]["reset_error"]);
    }

    public function testUnEnlaceYaUsadoCaducadoOEnviadoAUnaCuentaQueSeDesactivoDaElMismoMensaje(): void {
        // usado
        [$t, $sesion] = $this->enlaceAbierto();
        $this->assertSame("Contraseña actualizada. Ya puedes iniciar sesión.", $this->cambiar($sesion, self::CLAVE_NUEVA, self::CLAVE_NUEVA)["sesion"]["login_ok"]);
        $usado = $this->abrir($t);
        // caducado
        $t2 = $this->tokenDelCorreo($this->solicitar($this->email, $this->ipNueva()));
        $this->db->prepare("UPDATE password_resets SET expira_en = NOW() - INTERVAL 1 SECOND WHERE token_hash = ?")->execute([hash("sha256", $t2)]);
        $caducado = $this->abrir($t2);
        // cuenta desactivada después de emitir el enlace
        $t3 = $this->tokenDelCorreo($this->solicitar($this->email, $this->ipNueva()));
        $this->db->prepare("UPDATE usuarios SET activo = 0 WHERE id = ?")->execute([$this->uid]);
        $desactivada = $this->abrir($t3);
        $inventado = $this->abrir(str_repeat("b", 43));

        foreach (["usado" => $usado, "caducado" => $caducado, "desactivada" => $desactivada, "inventado" => $inventado] as $caso => $r) {
            $this->assertSame(\PasswordResetController::MSG_ENLACE, $r["sesion"]["reset_error"], $caso);
            $this->assertArrayNotHasKey("reset_hash", $r["sesion"], $caso);
        }
        $this->assertSame($usado["sesion"], $inventado["sesion"], "indistinguibles desde afuera");
    }

    public function testElFormularioSoloApareceConUnEnlaceVigente(): void {
        [, $sesion] = $this->enlaceAbierto();
        $con = $this->verFormulario($sesion);
        $this->assertStringContainsString('name="password2"', $con["salida"]);
        $this->assertArrayHasKey("reset_hash", $con["sesion"]);

        $this->db->prepare("UPDATE password_resets SET expira_en = NOW() - INTERVAL 1 SECOND WHERE usuario_id = ?")->execute([$this->uid]);
        $caducado = $this->verFormulario($sesion);
        $this->assertStringNotContainsString('name="password2"', $caducado["salida"]);
        $this->assertStringContainsString("no es válido o ya caducó", $caducado["salida"]);
        $this->assertArrayNotHasKey("reset_hash", $caducado["sesion"]);

        $sin = $this->verFormulario([]);
        $this->assertStringNotContainsString('name="password2"', $sin["salida"]);
    }

    // ── Cambiar la clave ─────────────────────────────────────────────
    public function testElCambioCompletoFuncionaYSoloUnaVez(): void {
        [$t, $sesion] = $this->enlaceAbierto();
        $r = $this->cambiar($sesion, self::CLAVE_NUEVA, self::CLAVE_NUEVA);
        $this->assertSame("Contraseña actualizada. Ya puedes iniciar sesión.", $r["sesion"]["login_ok"]);
        $this->assertArrayNotHasKey("reset_hash", $r["sesion"]);
        $this->assertArrayNotHasKey("user", $r["sesion"], "no inicia sesión sola");
        $this->assertNotSame($r["sid"]["inicial"], $r["sid"]["final"], "el identificador de sesión se renueva al cambiar la clave");
        if (Proceso::veCabeceras()) $this->assertStringEndsWith("/login.php", (string)Proceso::ubicacion($r));
        $this->assertNotNull($this->filasDe($this->email)[0]["usado_en"]);

        $this->assertSame($this->uid, (int)$this->entrar($this->email, self::CLAVE_NUEVA)["sesion"]["user"]["id"], "entra con la clave nueva");
        $vieja = $this->entrar($this->email, self::CLAVE_VIEJA);
        $this->assertArrayNotHasKey("user", $vieja["sesion"], "la vieja ya no sirve");

        // Reutilizar el mismo enlace, o la misma sesión, no vale
        $this->assertSame(\PasswordResetController::MSG_ENLACE, $this->abrir($t)["sesion"]["reset_error"]);
        $otra = $this->cambiar($sesion, "Otra frase distinta 2026!", "Otra frase distinta 2026!");
        $this->assertSame(\PasswordResetController::MSG_ENLACE, $otra["sesion"]["reset_error"]);
        $this->assertArrayNotHasKey("login_ok", $otra["sesion"]);
        $this->assertTrue(password_verify(self::CLAVE_NUEVA, $this->clave($this->uid)), "la clave sigue siendo la del primer canje");
    }

    public function testUnaClaveFlojaNoGastaElEnlaceYSePuedeReintentar(): void {
        [, $sesion] = $this->enlaceAbierto();
        $casos = [
            ["corta", "abc", "abc", "al menos"],
            ["común", "password123", "password123", "demasiado común"],
            ["no coinciden", self::CLAVE_NUEVA, self::CLAVE_NUEVA . "x", "no coinciden"],
            ["igual al correo", $this->email, $this->email, "igual a tu correo"],
            ["larga", str_repeat("a1", 40), str_repeat("a1", 40), "demasiado larga"],
        ];
        foreach ($casos as [$nombre, $p1, $p2, $texto]) {
            $r = $this->cambiar($sesion, $p1, $p2);
            $this->assertStringContainsString($texto, $r["sesion"]["reset_error"], $nombre);
            $this->assertSame($sesion["reset_hash"], $r["sesion"]["reset_hash"], "$nombre: el enlace sigue vivo");
        }
        $this->assertNull($this->filasDe($this->email)[0]["usado_en"]);
        $this->assertTrue(password_verify(self::CLAVE_VIEJA, $this->clave($this->uid)), "la clave no cambió");
        $ok = $this->cambiar($sesion, self::CLAVE_NUEVA, self::CLAVE_NUEVA);
        $this->assertArrayHasKey("login_ok", $ok["sesion"]);
    }

    public function testLaClaveNuevaNoPuedeSerIgualAlNombre(): void {
        [, $mail] = $this->crearUsuario(1, 0, "Maria Fernandez Gomez");
        $t = $this->tokenDelCorreo($this->solicitar($mail, $this->ipNueva()));
        $sesion = $this->abrir($t)["sesion"];
        $r = $this->cambiar($sesion, "maria fernandez gomez", "maria fernandez gomez");
        $this->assertStringContainsString("igual a tu correo ni a tu nombre", $r["sesion"]["reset_error"]);
    }

    public function testClavesEnFormaDeArregloNoRompenNada(): void {
        [, $sesion] = $this->enlaceAbierto();
        foreach ([[[self::CLAVE_NUEVA], [self::CLAVE_NUEVA]], [self::CLAVE_NUEVA, [self::CLAVE_NUEVA]], [[self::CLAVE_NUEVA], self::CLAVE_NUEVA]] as [$a, $b]) {
            $r = $this->cambiar($sesion, $a, $b);
            $this->assertArrayHasKey("reset_error", $r["sesion"]);
            $this->assertArrayNotHasKey("login_ok", $r["sesion"]);
        }
        $this->assertTrue(password_verify(self::CLAVE_VIEJA, $this->clave($this->uid)));
    }

    public function testSinTokenCsrfNoSeCambiaNada(): void {
        [, $sesion] = $this->enlaceAbierto();
        $r = $this->cambiar($sesion, self::CLAVE_NUEVA, self::CLAVE_NUEVA, false);
        $this->assertSame(403, $r["codigo"]);
        $this->assertTrue(password_verify(self::CLAVE_VIEJA, $this->clave($this->uid)));
    }

    public function testSinEnlaceEnLaSesionOConUnHashInventadoNoSeCambiaNada(): void {
        foreach ([[], ["reset_hash" => hash("sha256", "inventado")], ["reset_hash" => "no-es-un-hash"], ["reset_hash" => [hash("sha256", "x")]]] as $sesion) {
            $r = $this->cambiar($sesion, self::CLAVE_NUEVA, self::CLAVE_NUEVA);
            $this->assertSame(\PasswordResetController::MSG_ENLACE, $r["sesion"]["reset_error"]);
            $this->assertArrayNotHasKey("login_ok", $r["sesion"]);
        }
        $this->assertTrue(password_verify(self::CLAVE_VIEJA, $this->clave($this->uid)));
    }

    public function testUnEnlaceCaducadoEnElFormularioTampocoCambiaLaClave(): void {
        [, $sesion] = $this->enlaceAbierto();
        $this->db->prepare("UPDATE password_resets SET expira_en = NOW() - INTERVAL 1 SECOND WHERE usuario_id = ?")->execute([$this->uid]);
        $r = $this->cambiar($sesion, self::CLAVE_NUEVA, self::CLAVE_NUEVA);
        $this->assertSame(\PasswordResetController::MSG_ENLACE, $r["sesion"]["reset_error"]);
        $this->assertTrue(password_verify(self::CLAVE_VIEJA, $this->clave($this->uid)));
    }

    public function testUnaCuentaDesactivadaDespuesDeAbrirElEnlaceNoCambia(): void {
        [, $sesion] = $this->enlaceAbierto();
        $this->db->prepare("UPDATE usuarios SET activo = 0 WHERE id = ?")->execute([$this->uid]);
        $r = $this->cambiar($sesion, self::CLAVE_NUEVA, self::CLAVE_NUEVA);
        $this->assertSame(\PasswordResetController::MSG_ENLACE, $r["sesion"]["reset_error"]);
        $this->assertTrue(password_verify(self::CLAVE_VIEJA, $this->clave($this->uid)));
    }

    public function testElEnlaceDeUnaCuentaNoCambiaLaClaveDeOtra(): void {
        [$otroId, $otroMail] = $this->crearUsuario();
        $otroHash = $this->clave($otroId);
        [, $sesion] = $this->enlaceAbierto();
        $this->cambiar($sesion, self::CLAVE_NUEVA, self::CLAVE_NUEVA);
        $this->assertSame($otroHash, $this->clave($otroId));
        $this->assertArrayNotHasKey("user", $this->entrar($otroMail, self::CLAVE_NUEVA)["sesion"]);
    }

    public function testUnEnlaceNuevoMataAlAnteriorAunqueNoSeHayaUsado(): void {
        $t1 = $this->tokenDelCorreo($this->solicitar($this->email, $this->ipNueva()));
        $t2 = $this->tokenDelCorreo($this->solicitar($this->email, $this->ipNueva()));
        $this->assertSame(\PasswordResetController::MSG_ENLACE, $this->abrir($t1)["sesion"]["reset_error"]);
        $this->assertArrayHasKey("reset_hash", $this->abrir($t2)["sesion"]);
    }

    public function testElNuevoHashDeLaClaveEsBcrypt(): void {
        [, $sesion] = $this->enlaceAbierto();
        $this->cambiar($sesion, self::CLAVE_NUEVA, self::CLAVE_NUEVA);
        $this->assertStringStartsWith('$2y$', $this->clave($this->uid));
    }
}
