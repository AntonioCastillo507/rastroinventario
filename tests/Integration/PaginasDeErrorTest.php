<?php
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatos;
use Tests\Support\ClienteHttp;
use Tests\Support\Datos;
use Tests\Support\Html;
use Tests\Support\Proceso;
use Tests\Support\ServidorTemporal;

/**
 * P7, errores: 404, 403, 500 y 503 con la marca y los estilos del sitio, el código HTTP verdadero y nada interno a la vista.
 * Por HTTP contra un php -S real (rutas, error.php, token caducado), con controladores reales en proceso aparte (factura) y con un
 * proceso que llama a paginaError() de bootstrap.php, tanto en su versión temática como en la neutra de último recurso.
 */
final class PaginasDeErrorTest extends TestCase {
    private PDO $db;
    private static ?ServidorTemporal $srv = null; // un solo php -S para toda la clase
    private ClienteHttp $web;
    private array $partes = [];

    public static function setUpBeforeClass(): void {
        self::$srv = new ServidorTemporal();
        self::$srv->iniciar();
    }

    public static function tearDownAfterClass(): void {
        self::$srv?->detener();
        self::$srv = null;
    }

    protected function setUp(): void {
        parent::setUp();
        $this->db = \Database::get();
        BaseDatos::verificarBase($this->db);
        $this->assertFalse($this->db->inTransaction());
        $this->web = new ClienteHttp(self::$srv->base());
    }

    protected function tearDown(): void {
        if ($this->partes) $this->db->exec("DELETE FROM partes WHERE id IN (" . implode(",", array_map("intval", $this->partes)) . ")");
        parent::tearDown();
    }

    private function titulo(string $html): string {
        return trim(Html::xpath($html)->query("//h1")->item(0)->textContent);
    }

    private function assertTematica(array $r, int $codigo, string $titulo): void {
        $this->assertSame($codigo, $r["codigo"]);
        $this->assertStringContainsString('class="login-card error-pagina"', $r["cuerpo"], "página temática");
        $this->assertSame($titulo, $this->titulo($r["cuerpo"]));
        $this->assertStringContainsString("Error $codigo", $r["cuerpo"]);
        $this->assertStringContainsString(\Marca::get("nombre"), $r["cuerpo"], "con la marca");
        $this->assertStringContainsString("tema.css", $r["cuerpo"], "con el tema");
        $this->assertMatchesRegularExpression('/X-Robots-Tag:\s*noindex/i', $r["cabeceras"]);
        $this->assertMatchesRegularExpression('/Cache-Control:\s*no-store/i', $r["cabeceras"]);
        $this->assertSame(1, preg_match_all('/<h1[ >]/', $r["cuerpo"]), "un solo h1");
        $this->assertSame(1, substr_count($r["cuerpo"], 'class="btn btn-gold'), "una sola acción principal");
        foreach (["Exception", "Stack trace", "PDOException", ".php:", "C:\\", "SELECT ", "Warning:", "Fatal error"] as $interno) $this->assertStringNotContainsString($interno, $r["cuerpo"], "sin $interno");
    }

    // ── 404 de la aplicación ──

    public function testUnaAccionQueNoExisteDa404ConLaPaginaTematica(): void {
        $r = $this->web->get("/index.php?action=esto-no-existe");
        $this->assertTematica($r, 404, "Página no encontrada");
        $this->assertSame(1, substr_count($r["cuerpo"], 'role="search"'), "la 404 ofrece buscar una pieza");
        $this->assertStringContainsString('href="' . APP_URL . '/"', $r["cuerpo"]);
    }

    /** @return array<string,array{0:string}> */
    public static function rutasQueSiExisten(): array {
        return ["sin acción" => ["/index.php"], "home" => ["/index.php?action=home"], "catálogo" => ["/index.php?ver=catalogo"], "carrito" => ["/index.php?action=carrito"],
            "nosotros" => ["/index.php?action=nosotros"], "contacto" => ["/index.php?action=contacto"], "búsqueda" => ["/index.php?q=faro"], "acción vacía" => ["/index.php?action="]];
    }

    #[DataProvider("rutasQueSiExisten")]
    public function testLasRutasDeSiempreSiguenDandoElCodigoDeSiempre(string $ruta): void {
        $r = $this->web->get($ruta);
        $this->assertSame(200, $r["codigo"], $ruta);
        $this->assertStringNotContainsString("error-pagina", $r["cuerpo"]);
    }

    public function testUnaAccionRaraNoSeReflejaEnLaPagina(): void {
        $r = $this->web->get("/index.php?action=" . rawurlencode("<script>alert(7)</script>"));
        $this->assertSame(404, $r["codigo"]);
        $this->assertStringNotContainsString("alert(7)", $r["cuerpo"]);
        $this->assertSame(200, $this->web->get("/index.php?action[]=x")["codigo"], "un arreglo cuenta como sin acción y no rompe nada");
    }

    public function testUnaPiezaQueNoExisteOEstaInactivaDa404YUnaActivaNo(): void {
        $viva = Datos::parte($this->db, 10.0, 3, "Pieza viva del 404"); $muerta = Datos::parte($this->db, 10.0, 3, "Pieza inactiva del 404");
        $this->partes = [$viva, $muerta];
        $this->db->exec("UPDATE partes SET activo = 0 WHERE id = $muerta");
        $this->assertSame(200, $this->web->get("/index.php?action=detalle&id=$viva")["codigo"]);
        $r = $this->web->get("/index.php?action=detalle&id=$muerta");
        $this->assertTematica($r, 404, "Pieza no encontrada");
        $this->assertStringContainsString("ya no está disponible", $r["cuerpo"]);
        $this->assertTematica($this->web->get("/index.php?action=detalle&id=99999999"), 404, "Pieza no encontrada");
        $this->assertTematica($this->web->get("/index.php?action=detalle&id=abc"), 404, "Pieza no encontrada");
    }

    // ── 403 ──

    public function testUnTokenCaducadoFueraDeLaCompraDa403ConPaginaTematicaSinLaPalabraCsrf(): void {
        $r = $this->web->post("/login.php", ["email" => "a@example.invalid", "password" => "x", "csrf_token" => "falso"]);
        $this->assertTematica($r, 403, "La página caducó");
        $this->assertStringNotContainsStringIgnoringCase("csrf", $r["cuerpo"]);
        $this->assertStringContainsString("inténtalo de nuevo", $r["cuerpo"]);
    }

    // ── error.php (destino de ErrorDocument) ──

    /** @return array<string,array{0:int,1:string}> */
    public static function codigos(): array {
        return ["403" => [403, "Acceso no permitido"], "404" => [404, "Página no encontrada"], "500" => [500, "Algo salió mal"], "503" => [503, "Servicio no disponible"]];
    }

    #[DataProvider("codigos")]
    public function testErrorPhpResponde(int $codigo, string $titulo): void {
        $this->assertTematica($this->web->get("/error.php?codigo=$codigo"), $codigo, $titulo);
    }

    /** @return array<string,array{0:string}> */
    public static function codigosRaros(): array {
        return ["sin código" => [""], "texto" => ["?codigo=abc"], "fuera de la lista" => ["?codigo=418"], "cero" => ["?codigo=0"], "negativo" => ["?codigo=-404"], "arreglo" => ["?codigo[]=500"], "script" => ["?codigo=" . "%3Cscript%3Ealert(7)%3C/script%3E"], "enorme" => ["?codigo=99999999999999999999"]];
    }

    #[DataProvider("codigosRaros")]
    public function testUnCodigoRaroEsUn404YNoSeReflejaNada(string $consulta): void {
        $r = $this->web->get("/error.php" . $consulta);
        $this->assertTematica($r, 404, "Página no encontrada");
        $this->assertStringNotContainsString("alert(7)", $r["cuerpo"]);
    }

    public function testElTemaClaroYElOscuroLosAplicaElMismoScriptQueElSitio(): void {
        $r = $this->web->get("/error.php?codigo=404");
        $this->assertStringContainsString("js/tema.js", $r["cuerpo"]);
        $this->assertStringContainsString('data-theme="dark"', $r["cuerpo"]);
    }

    // ── Factura que no existe ──

    public function testUnaFacturaQueNoExisteDa404TematicoSinDecirSiEsDeOtro(): void {
        $r = Proceso::accion(["clase" => "FacturaController", "accion" => "generarPublico", "metodo_http" => "GET", "get" => ["pedido" => "99999999"],
            "sesion" => ["user" => ["id" => 1, "nombre" => "X", "email" => "x@example.invalid"], "user_roles" => ["Cliente"]]]);
        $this->assertSame(404, $r["codigo"]);
        $this->assertStringContainsString("Pedido no encontrado", $r["salida"]);
        $this->assertStringContainsString("error-pagina", $r["salida"]);
        $this->assertStringContainsString("No encontramos ese pedido.", $r["salida"]);
    }

    // ── 500: paginaError() de bootstrap.php ──

    /** Corre un script suelto con bootstrap.php cargado y devuelve [código, salida] */
    private function conBootstrap(string $codigoPhp): array {
        $script = RASTRO_TEST_TMP . DIRECTORY_SEPARATOR . "error_" . uniqid() . ".php";
        file_put_contents($script, "<?php\ndefine('BASE_PATH', " . var_export(BASE_PATH, true) . ");\nrequire BASE_PATH . '/bootstrap.php';\nini_set('error_log', " . var_export(RASTRO_TEST_TMP . "/error_hijo.log", true) . ");\n$codigoPhp\n");
        $p = proc_open([PHP_BINARY, $script], [1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes, null, array_merge(getenv(), ["RASTRO_DB_NAME" => "rastroinventario_test", "XDEBUG_MODE" => "off"]));
        $salida = stream_get_contents($pipes[1]); stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $codigo = proc_close($p);
        @unlink($script);
        return [$codigo, $salida];
    }

    public function testElErrorInternoSaleConLaPaginaTematicaYSoloElCodigoDeReferencia(): void {
        [, $salida] = $this->conBootstrap("paginaError('A1B2C3', 500);");
        $this->assertStringContainsString('class="login-card error-pagina"', $salida);
        $this->assertStringContainsString("Algo salió mal", $salida);
        $this->assertStringContainsString("A1B2C3", $salida);
        $this->assertStringContainsString("código de referencia", $salida);
        $this->assertStringContainsString(\Marca::get("nombre"), $salida);
    }

    public function testElReferenciaSeEscapa(): void {
        [, $salida] = $this->conBootstrap("paginaError('<b>x</b>\"', 503);");
        $this->assertStringNotContainsString("<b>x</b>", str_replace('<b class="num">', "", $salida));
        $this->assertStringContainsString("Servicio no disponible", $salida);
    }

    public function testSiLaPaginaTematicaFallaSaleLaNeutraConElMismoCodigoDeReferencia(): void {
        // una marca imposible de imprimir hace fallar la plantilla: tiene que caer en la página neutra, sin romper ni mostrar el motivo
        [$codigo, $salida] = $this->conBootstrap("Marca::usarParaPruebas(['nombre' => new stdClass()]);\npaginaError('F4E5D6', 500);");
        $this->assertSame(0, $codigo);
        $this->assertStringNotContainsString("error-pagina", $salida);
        $this->assertStringContainsString("Algo salió mal", $salida);
        $this->assertStringContainsString("<b>F4E5D6</b>", $salida);
        foreach (["Error", "stdClass", "Stack trace", "Exception"] as $interno) $this->assertStringNotContainsString($interno, str_replace("Algo salió mal", "", $salida), $interno);
        $this->assertStringNotContainsString("<br", $salida);
    }

    public function testLaPaginaNeutraNoDependeDeNada(): void {
        [, $salida] = $this->conBootstrap("echo paginaErrorNeutra('ABC123');");
        $this->assertStringContainsString("<b>ABC123</b>", $salida);
        $this->assertStringNotContainsString("<link", $salida);
        $this->assertStringNotContainsString("<script", $salida);
    }

    public function testElMismoErrorDeServidorNuncaDejaElMensajeRealEnLaPantalla(): void {
        [, $salida] = $this->conBootstrap("throw new RuntimeException('contraseña de la base: secreto123');");
        $this->assertStringContainsString("Algo salió mal", $salida);
        $this->assertStringNotContainsString("secreto123", $salida);
        $this->assertStringContainsString("código de referencia", $salida);
    }
}
