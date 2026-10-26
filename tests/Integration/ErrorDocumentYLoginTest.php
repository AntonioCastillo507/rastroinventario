<?php
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatos;
use Tests\Support\Proceso;

/**
 * Lo que sale del servidor y lo que sale del login. (1) .htaccess manda los 403 y 404 que genera Apache a error.php: se revisa el
 * texto y, si el Apache de Wamp responde, se piden por GET (solo lectura) una ruta inexistente y carpetas bloqueadas. (2) Quien ya tiene
 * sesión y abre login.php va a donde le toca: el personal al panel, un cliente a la tienda (antes todos a /admin/).
 */
final class ErrorDocumentYLoginTest extends TestCase {
    private PDO $db;
    private array $uids = [];

    protected function setUp(): void {
        parent::setUp();
        $this->db = \Database::get();
        BaseDatos::verificarBase($this->db);
    }

    protected function tearDown(): void {
        if ($this->uids) $this->db->exec("DELETE FROM usuarios WHERE id IN (" . implode(",", array_map("intval", $this->uids)) . ")");
        parent::tearDown();
    }

    // ── .htaccess ──

    private function htaccess(): string {
        return (string)file_get_contents(BASE_PATH . "/.htaccess");
    }

    public function testErrorDocumentMandaElTresCuatroYCuatroCeroTresAErrorPhp(): void {
        $h = $this->htaccess();
        $carpeta = rtrim((string)parse_url(APP_URL, PHP_URL_PATH), "/");
        $this->assertMatchesRegularExpression('~^ErrorDocument 403 ' . preg_quote($carpeta, "~") . '/error\.php\?codigo=403$~m', $h);
        $this->assertMatchesRegularExpression('~^ErrorDocument 404 ' . preg_quote($carpeta, "~") . '/error\.php\?codigo=404$~m', $h);
        $this->assertFileExists(BASE_PATH . "/error.php");
    }

    public function testElDestinoEsUnaRutaLocalNuncaUnaDireccionConDominio(): void {
        preg_match_all('~^ErrorDocument\s+\d+\s+(\S+)~m', $this->htaccess(), $m);
        $this->assertNotEmpty($m[1]);
        foreach ($m[1] as $destino) {
            $this->assertStringStartsWith("/", $destino, "una URL completa haría que Apache redirija y pierda el código");
            $this->assertStringNotContainsString("://", $destino);
        }
    }

    public function testLasReglasDeBloqueoSiguenAhi(): void {
        $h = $this->htaccess();
        $this->assertStringContainsString('<FilesMatch "\.(sql|log|key|crt|pem|md|lock|json|bak|ini|env)$">', $h);
        $this->assertStringContainsString("Require all denied", $h);
        $this->assertMatchesRegularExpression('~RedirectMatch 403 \^/rastroinventario/\(vendor\|docs\|bin\|\\\\\.git\|\\\\\.claude\)~', $h);
        $this->assertStringContainsString("Options -Indexes", $h);
    }

    public function testErrorPhpNoEstaBloqueadoPorElPropioHtaccess(): void {
        $this->assertDoesNotMatchRegularExpression('~\\.\(([a-z|]*\|)?php(\|[a-z|]*)?\)\$~', $this->htaccess());
    }

    // ── Apache de Wamp: solo GET ──

    private function apache(string $ruta): ?array {
        $p = parse_url(APP_URL);
        $c = @fsockopen($p["host"], $p["port"] ?? 80, $en, $es, 0.5);
        if (!$c) return null;
        fclose($c);
        $ch = curl_init(APP_URL . $ruta);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 10]);
        $r = (string)curl_exec($ch);
        $cab = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $res = ["codigo" => (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE), "cabeceras" => substr($r, 0, $cab), "cuerpo" => substr($r, $cab)];
        curl_close($ch);
        return $res;
    }

    /** @return array<string,array{0:string,1:int,2:string}> */
    public static function rutasDeApache(): array {
        return ["archivo que no existe" => ["/no-existe-" . "p7", 404, "Página no encontrada"], "carpeta docs bloqueada" => ["/docs/DESIGN.md", 403, "Acceso no permitido"],
            "carpeta vendor bloqueada" => ["/vendor/", 403, "Acceso no permitido"], "extensión bloqueada" => ["/composer.json", 403, "Acceso no permitido"]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("rutasDeApache")]
    public function testApacheRealDevuelveLaPaginaTematicaConElCodigoVerdadero(string $ruta, int $codigo, string $titulo): void {
        $r = $this->apache($ruta);
        if ($r === null) $this->markTestSkipped("Apache de Wamp no responde en " . APP_URL);
        $this->assertSame($codigo, $r["codigo"]);
        $this->assertStringContainsString('class="login-card error-pagina"', $r["cuerpo"]);
        $this->assertStringContainsString($titulo, $r["cuerpo"]);
        $this->assertDoesNotMatchRegularExpression('~Apache/\d|Server at|Not Found</h1>|Forbidden</h1>~i', $r["cuerpo"], "sin la página ni la firma de Apache");
        $this->assertMatchesRegularExpression('/X-Robots-Tag:\s*noindex/i', $r["cabeceras"]);
    }

    public function testLasPaginasQueSiExistenNoSeTocan(): void {
        $r = $this->apache("/?action=contacto");
        if ($r === null) $this->markTestSkipped("Apache de Wamp no responde en " . APP_URL);
        $this->assertSame(200, $r["codigo"]);
        $this->assertStringNotContainsString("error-pagina", $r["cuerpo"]);
    }

    // ── Login con sesión abierta ──

    private function usuario(string $rol): int {
        $email = "login." . bin2hex(random_bytes(4)) . "@example.invalid";
        $this->db->prepare("INSERT INTO usuarios (nombre, email, password) VALUES ('Sesión abierta', ?, '!')")->execute([$email]);
        $id = (int)$this->db->lastInsertId();
        $this->uids[] = $id;
        $this->db->prepare("INSERT INTO usuario_roles (usuario_id, rol_id) SELECT ?, id FROM roles WHERE nombre = ?")->execute([$id, $rol]);
        return $id;
    }

    private function abrirLogin(int $uid, string $rol): array {
        return Proceso::accion(["clase" => "AuthController", "accion" => "showLogin", "metodo_http" => "GET",
            "sesion" => ["user" => ["id" => $uid, "nombre" => "Sesión abierta", "email" => "x@example.invalid"], "user_roles" => [$rol]]]);
    }

    public function testUnClienteConSesionQueAbreElLoginVaALaTiendaNoAlPanel(): void {
        $r = $this->abrirLogin($this->usuario("Cliente"), "Cliente");
        $this->assertSame("", trim($r["salida"]), "no pinta el formulario");
        if (Proceso::veCabeceras()) $this->assertSame(APP_URL . "/", Proceso::ubicacion($r));
        $this->assertArrayNotHasKey("flash", $r["sesion"], "ni el aviso de «sin acceso al panel»");
    }

    public function testElPersonalConSesionQueAbreElLoginVaAlPanel(): void {
        $r = $this->abrirLogin($this->usuario("Administrador"), "Administrador");
        if (Proceso::veCabeceras()) $this->assertSame(APP_URL . "/admin/", Proceso::ubicacion($r));
        $this->assertSame("", trim($r["salida"]));
    }

    public function testSinSesionSeVeElFormulario(): void {
        $r = Proceso::accion(["clase" => "AuthController", "accion" => "showLogin", "metodo_http" => "GET", "sesion" => []]);
        $this->assertStringContainsString("Iniciar sesión", $r["salida"]);
        $this->assertStringContainsString('name="password"', $r["salida"]);
    }
}
