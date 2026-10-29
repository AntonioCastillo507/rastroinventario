<?php
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatos;
use Tests\Support\ClienteHttp;
use Tests\Support\Html;
use Tests\Support\Imagenes;
use Tests\Support\ServidorTemporal;

/**
 * Panel de marcas contra un servidor temporal (php -S) y rastroinventario_test, con subidas multipart reales.
 * El servidor guarda los logos en uploads/marcas/ del proyecto: la prueba borra al final los archivos de sus marcas.
 * Usa commits (el servidor es otra conexión) y limpia lo suyo por ids y nombres exactos.
 */
final class MarcasPanelTest extends TestCase {
    private const CLAVE = "Frase-segura-de-prueba-2026";
    private PDO $db;
    private ServidorTemporal $srv;
    private ClienteHttp $web;
    private string $s;
    private array $correos = [];
    private array $usuarios = [];
    private array $roles = [];
    private array $nombres = [];

    protected function setUp(): void {
        parent::setUp();
        $this->db = \Database::get();
        BaseDatos::verificarBase($this->db);
        $this->assertFalse($this->db->inTransaction());
        $this->s = bin2hex(random_bytes(3));
        $this->srv = new ServidorTemporal();
        $this->srv->iniciar();
        $this->web = new ClienteHttp($this->srv->base());
    }

    protected function tearDown(): void {
        $this->srv->detener();
        if ($this->nombres) {
            $in = implode(",", array_fill(0, count($this->nombres), "?"));
            $logos = $this->db->prepare("SELECT logo FROM marcas WHERE nombre IN ($in) OR slug LIKE ?");
            $logos->execute([...$this->nombres, "zz-%{$this->s}%"]);
            foreach ($logos->fetchAll(PDO::FETCH_COLUMN) as $l) if (\LogoMarca::nombreSeguro($l)) @unlink($this->dirLogos() . $l);
            $this->db->prepare("DELETE FROM autos WHERE marca IN ($in)")->execute($this->nombres);
            $this->db->prepare("DELETE FROM marcas WHERE nombre IN ($in)")->execute($this->nombres);
        }
        if ($this->correos) {
            $in = implode(",", array_fill(0, count($this->correos), "?"));
            $this->db->prepare("DELETE FROM login_logs WHERE email_intento IN ($in)")->execute($this->correos);
        }
        foreach ($this->usuarios as $u) $this->db->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$u]);
        foreach ($this->roles as $r) $this->db->prepare("DELETE FROM roles WHERE id = ?")->execute([$r]);
        parent::tearDown();
    }

    /** Carpeta de logos del proyecto: donde guarda el servidor temporal (no la UPLOAD_PATH temporal de este proceso) */
    private function dirLogos(): string { return BASE_PATH . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "marcas" . DIRECTORY_SEPARATOR; }

    private function nombre(string $base): string { return $this->nombres[] = "Zz $base $this->s"; }

    /** Usuario con un rol que tiene permisos sobre «autos» y sesión abierta en el servidor temporal */
    private function entrar(bool $completo): ClienteHttp {
        $this->db->prepare("INSERT INTO roles (nombre) VALUES (?)")->execute(["Rol marcas " . ($completo ? "A" : "V") . " $this->s"]);
        $rol = (int)$this->db->lastInsertId(); $this->roles[] = $rol;
        $this->db->prepare("INSERT INTO permisos (rol_id, modulo, puede_ver, puede_crear, puede_editar, puede_borrar) VALUES (?, 'autos', 1, ?, ?, ?)")->execute([$rol, (int)$completo, (int)$completo, (int)$completo]);
        $email = $this->correos[] = "marcas." . ($completo ? "a" : "v") . ".$this->s@example.invalid";
        $this->db->prepare("INSERT INTO usuarios (nombre, email, password) VALUES ('Prueba marcas', ?, ?)")->execute([$email, password_hash(self::CLAVE, PASSWORD_BCRYPT)]);
        $uid = (int)$this->db->lastInsertId(); $this->usuarios[] = $uid;
        $this->db->prepare("INSERT INTO usuario_roles (usuario_id, rol_id) VALUES (?, ?)")->execute([$uid, $rol]);
        $web = new ClienteHttp($this->srv->base());
        $r = $web->postConToken("/login.php", "/login.php", ["email" => $email, "password" => self::CLAVE]);
        $this->assertSame(302, $r["codigo"]);
        $this->assertStringNotContainsString("login.php", (string)$r["ubicacion"], "entró");
        return $web;
    }

    private function token(ClienteHttp $web, string $ruta = "/admin/?mod=marcas&action=crear"): string {
        return ClienteHttp::token($web->get($ruta)["cuerpo"]);
    }

    private function marca(string $nombre): array|false {
        $s = $this->db->prepare("SELECT * FROM marcas WHERE nombre = ?");
        $s->execute([$nombre]);
        return $s->fetch();
    }

    private function pngConAlfa(): string {
        $im = imagecreatetruecolor(160, 80);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        imagefilledellipse($im, 80, 40, 100, 50, imagecolorallocate($im, 30, 30, 30));
        ob_start();
        imagepng($im);
        return Imagenes::guardar((string)ob_get_clean(), "png");
    }

    private function crear(ClienteHttp $web, string $nombre, ?string $archivo = null, string $nombreArchivo = "logo.png", string $tipo = "image/png"): array {
        $campos = ["csrf_token" => $this->token($web), "nombre" => $nombre];
        $ruta = "/admin/?mod=marcas&action=crear";
        return $archivo === null ? $web->post($ruta, $campos) : $web->postArchivo($ruta, $campos, "logo", $archivo, $nombreArchivo, $tipo);
    }

    private function alerta(string $html): string {
        $a = Html::xpath($html)->query("//main//div[@role='alert']");
        return $a->length ? trim($a->item(0)->textContent) : "";
    }

    public function testSinSesionVaAlAcceso(): void {
        $r = $this->web->get("/admin/?mod=marcas");
        $this->assertSame(302, $r["codigo"]);
        $this->assertStringContainsString("login.php", (string)$r["ubicacion"]);
        $r = $this->web->post("/admin/?mod=marcas&action=crear", ["nombre" => $this->nombre("Sin sesion")]);
        $this->assertFalse($this->marca($this->nombres[0]));
    }

    public function testQuienSoloPuedeVerNoCreaNiEdita(): void {
        $lector = $this->entrar(false);
        $this->assertSame(200, $lector->get("/admin/?mod=marcas")["codigo"]);
        $nombre = $this->nombre("Solo ver");
        $r = $lector->post("/admin/?mod=marcas&action=crear", ["csrf_token" => $this->token($lector, "/admin/?mod=marcas"), "nombre" => $nombre]);
        $this->assertSame(302, $r["codigo"]);
        $this->assertFalse($this->marca($nombre), "sin permiso de crear no se crea");
    }

    public function testElMenuYLaListaMuestranLaMarcaConMonogramaYSinLogo(): void {
        $admin = $this->entrar(true);
        $nombre = $this->nombre("Mazda");
        $this->assertSame(302, $this->crear($admin, $nombre)["codigo"]);
        $m = $this->marca($nombre);
        $this->assertNotFalse($m);
        $this->assertNull($m["logo"]);
        $html = $admin->get("/admin/?mod=marcas")["cuerpo"];
        $this->assertGreaterThan(0, Html::contar(Html::xpath($html), "//aside//a[contains(@href,'mod=marcas')] | //nav//a[contains(@href,'mod=marcas')]"), "entrada Marcas en el menú");
        $this->assertStringContainsString(htmlspecialchars($nombre), $html);
        $this->assertStringContainsString('class="marca-monograma"', $html);
        $this->assertStringContainsString(">ZM<", $html, "monograma con las iniciales");
    }

    public function testNombreDuplicadoSeRechazaYElFormularioConservaLoEscrito(): void {
        $admin = $this->entrar(true);
        $nombre = $this->nombre("Honda");
        $this->assertSame(302, $this->crear($admin, $nombre)["codigo"]);
        $r = $this->crear($admin, strtolower($nombre) . "  ");
        $this->assertSame(200, $r["codigo"]);
        $this->assertStringContainsString("Ya existe", $this->alerta($r["cuerpo"]));
        $campo = Html::xpath($r["cuerpo"])->query("//input[@name='nombre']")->item(0);
        $this->assertSame(strtolower($nombre), $campo->getAttribute("value"), "lo escrito vuelve (sin espacios en los extremos)");
        $this->assertSame(1, (int)$this->db->query("SELECT COUNT(*) FROM marcas WHERE nombre = " . $this->db->quote($nombre))->fetchColumn());
    }

    public function testSubirCambiarYQuitarElLogoGuardaYBorraLosArchivos(): void {
        $admin = $this->entrar(true);
        $nombre = $this->nombre("Dodge");
        $r = $this->crear($admin, $nombre, $this->pngConAlfa(), "mi-logo-original.png");
        $this->assertSame(302, $r["codigo"], strip_tags(substr($r["cuerpo"], 0, 300)));
        $m = $this->marca($nombre);
        $this->assertMatchesRegularExpression('/^marca_[0-9a-f]{32}\.png$/', (string)$m["logo"]);
        $this->assertStringNotContainsString("original", (string)$m["logo"], "el nombre subido no se usa");
        $viejo = $this->dirLogos() . $m["logo"];
        $this->assertFileExists($viejo);
        $html = $admin->get("/admin/?mod=marcas")["cuerpo"];
        $this->assertStringContainsString("uploads/marcas/" . $m["logo"], $html);

        // Cambiar: entra uno nuevo y el anterior desaparece del disco
        $ruta = "/admin/?mod=marcas&action=editar&id=" . (int)$m["id"];
        $r = $admin->postArchivo($ruta, ["csrf_token" => $this->token($admin, $ruta), "nombre" => $nombre], "logo", $this->pngConAlfa(), "otro.png", "image/png");
        $this->assertSame(302, $r["codigo"]);
        $nuevo = $this->marca($nombre)["logo"];
        $this->assertNotSame($m["logo"], $nuevo);
        $this->assertFileExists($this->dirLogos() . $nuevo);
        $this->assertFileDoesNotExist($viejo, "el logo anterior se borra");

        // Quitar
        $r = $admin->post("/admin/?mod=marcas&action=quitar_logo&id=" . (int)$m["id"], ["csrf_token" => $this->token($admin, "/admin/?mod=marcas")]);
        $this->assertSame(302, $r["codigo"]);
        $this->assertNull($this->marca($nombre)["logo"]);
        $this->assertFileDoesNotExist($this->dirLogos() . $nuevo);
    }

    public function testSubidaConTipoFalsoSeRechazaYNoGuardaNada(): void {
        $admin = $this->entrar(true);
        @mkdir($this->dirLogos(), 0755, true);
        $antes = array_diff(scandir($this->dirLogos()), [".", ".."]);
        foreach ([["<?php echo 'hola'; system(\$_GET['x']); ?>", "logo.png", "image/png"], ["<?php echo 1;", "logo.php", "image/png"], ["texto cualquiera", "logo.svg", "image/svg+xml"]] as $i => [$contenido, $archivoNombre, $tipo]) {
            $nombre = $this->nombre("Falso $i");
            $r = $this->crear($admin, $nombre, Imagenes::guardar($contenido, "bin"), $archivoNombre, $tipo);
            $this->assertSame(200, $r["codigo"], $archivoNombre);
            $this->assertNotSame("", $this->alerta($r["cuerpo"]), $archivoNombre);
            $this->assertFalse($this->marca($nombre), "la marca no se crea si el logo no sirve: $archivoNombre");
        }
        $this->assertSame($antes, array_diff(scandir($this->dirLogos()), [".", ".."]), "no queda ningún archivo nuevo");
    }

    public function testUnSvgConScriptSeGuardaSaneado(): void {
        $admin = $this->entrar(true);
        $nombre = $this->nombre("Svg");
        $svg = Imagenes::guardar('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10" onload="alert(1)"><script>alert(1)</script><path d="M0 0h10v10z"/></svg>', "svg");
        $r = $this->crear($admin, $nombre, $svg, "logo.svg", "image/svg+xml");
        $this->assertSame(302, $r["codigo"]);
        $logo = $this->marca($nombre)["logo"];
        $this->assertStringEndsWith(".svg", $logo);
        $guardado = (string)file_get_contents($this->dirLogos() . $logo);
        $this->assertStringNotContainsString("script", $guardado);
        $this->assertStringNotContainsString("onload", $guardado);
    }

    public function testRenombrarActualizaElTextoDeLosAutos(): void {
        $admin = $this->entrar(true);
        $viejo = $this->nombre("Dogde");
        $nuevo = $this->nombre("Dodge");
        $this->crear($admin, $viejo);
        $id = (int)$this->marca($viejo)["id"];
        $this->db->prepare("INSERT INTO autos (marca, marca_id, modelo, anio) VALUES (?, ?, 'Charger', 2020)")->execute([$viejo, $id]);
        $ruta = "/admin/?mod=marcas&action=editar&id=$id";
        $this->assertSame(302, $admin->post($ruta, ["csrf_token" => $this->token($admin, $ruta), "nombre" => $nuevo])["codigo"]);
        $this->assertSame($nuevo, $this->db->query("SELECT marca FROM autos WHERE marca_id = $id")->fetchColumn());
    }

    public function testDesactivarYActivarPorPostConToken(): void {
        $admin = $this->entrar(true);
        $nombre = $this->nombre("Kia");
        $this->crear($admin, $nombre);
        $id = (int)$this->marca($nombre)["id"];
        $ruta = "/admin/?mod=marcas&action=activar&id=$id";
        $admin->get($ruta); // por GET no cambia nada
        $this->assertSame(1, (int)$this->marca($nombre)["activo"]);
        $this->assertSame(302, $admin->post($ruta, ["csrf_token" => $this->token($admin, "/admin/?mod=marcas"), "activo" => 0])["codigo"]);
        $this->assertSame(0, (int)$this->marca($nombre)["activo"]);
        $admin->post($ruta, ["csrf_token" => $this->token($admin, "/admin/?mod=marcas"), "activo" => 1]);
        $this->assertSame(1, (int)$this->marca($nombre)["activo"]);
    }

    public function testPorGetNoSeQuitaElLogoYSinTokenTampoco(): void {
        $admin = $this->entrar(true);
        $nombre = $this->nombre("Gmc");
        $this->crear($admin, $nombre, $this->pngConAlfa());
        $m = $this->marca($nombre);
        $ruta = "/admin/?mod=marcas&action=quitar_logo&id=" . (int)$m["id"];
        $admin->get($ruta);
        $this->assertSame($m["logo"], $this->marca($nombre)["logo"], "GET no cambia datos");
        $admin->post($ruta, ["csrf_token" => "falso"]);
        $this->assertSame($m["logo"], $this->marca($nombre)["logo"], "sin token válido no cambia datos");
        $this->assertFileExists($this->dirLogos() . $m["logo"]);
    }

    public function testSinTokenNoSeCreaNada(): void {
        $admin = $this->entrar(true);
        $nombre = $this->nombre("Sin token");
        $admin->post("/admin/?mod=marcas&action=crear", ["nombre" => $nombre]);
        $admin->post("/admin/?mod=marcas&action=crear", ["nombre" => $nombre, "csrf_token" => "falso"]);
        $this->assertFalse($this->marca($nombre));
    }

    public function testElFormularioDeAutosEligeLaMarcaDeLaListaYGuardaIdYNombre(): void {
        $admin = $this->entrar(true);
        $nombre = $this->nombre("Autos");
        $this->crear($admin, $nombre);
        $id = (int)$this->marca($nombre)["id"];
        $html = $admin->get("/admin/?mod=autos&action=crear")["cuerpo"];
        $x = Html::xpath($html);
        $this->assertSame(1, $x->query("//select[@name='marca_id']")->length, "la marca es un desplegable");
        $this->assertSame(0, $x->query("//input[@name='marca']")->length, "ya no hay campo libre");
        $this->assertSame(1, $x->query("//select[@name='marca_id']/option[@value='$id']")->length);
        $r = $admin->post("/admin/?mod=autos&action=crear", ["csrf_token" => ClienteHttp::token($html), "marca_id" => $id, "modelo" => "Modelo $this->s", "anio" => 2021]);
        $this->assertSame(302, $r["codigo"]);
        $a = $this->db->query("SELECT marca, marca_id FROM autos WHERE modelo = 'Modelo $this->s'")->fetch();
        $this->assertSame($nombre, $a["marca"]);
        $this->assertSame($id, (int)$a["marca_id"]);
        $this->db->exec("DELETE FROM autos WHERE modelo = 'Modelo $this->s'");
    }

    public function testUnAutoNoSeGuardaConUnaMarcaInexistenteOInactiva(): void {
        $admin = $this->entrar(true);
        $nombre = $this->nombre("Inactiva");
        $this->crear($admin, $nombre);
        $id = (int)$this->marca($nombre)["id"];
        $this->db->prepare("UPDATE marcas SET activo = 0 WHERE id = ?")->execute([$id]);
        foreach ([$id, 99999999, 0] as $malo) {
            $html = $admin->get("/admin/?mod=autos&action=crear")["cuerpo"];
            $r = $admin->post("/admin/?mod=autos&action=crear", ["csrf_token" => ClienteHttp::token($html), "marca_id" => $malo, "modelo" => "Raro $this->s", "anio" => 2021]);
            $this->assertSame(200, $r["codigo"], "marca_id $malo");
            $this->assertStringContainsString("Elige una marca", $this->alerta($r["cuerpo"]));
            $x = Html::xpath($r["cuerpo"]);
            $this->assertStringEndsWith("action=crear", $x->query("//main//form[@method='POST' or @method='post']")->item(0)->getAttribute("action"), "el formulario con error sigue siendo el de crear");
            $this->assertSame("Raro $this->s", $x->query("//input[@name='modelo']")->item(0)->getAttribute("value"), "conserva lo escrito");
        }
        $this->assertSame(0, (int)$this->db->query("SELECT COUNT(*) FROM autos WHERE modelo = 'Raro $this->s'")->fetchColumn());
    }
}
