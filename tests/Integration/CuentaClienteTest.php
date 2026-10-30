<?php
namespace Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BaseDatos;
use Tests\Support\Datos;

/** Mi cuenta (S8.3): perfil, dirección de entrega, menú de la cuenta y el selector geográfico, con los controladores reales en el proceso */
final class CuentaClienteTest extends BaseDatos {
    private int $uid;

    protected function setUp(): void {
        parent::setUp();
        $this->uid = Datos::usuario($this->db);
        $_SESSION["user"] = ["id" => $this->uid, "nombre" => "Usuario de prueba", "email" => "x@example.invalid"];
        $_SESSION["user_roles"] = ["Cliente"];
        $_GET = []; $_POST = [];
    }

    protected function tearDown(): void {
        $_SESSION = []; $_GET = []; $_POST = []; $_SERVER["REQUEST_METHOD"] = "GET";
        parent::tearDown();
    }

    /** Corre una acción de PedidoController; devuelve [html, destino de la redirección o null] */
    private function accion(string $metodo, array $post = []): array {
        $_SERVER["REQUEST_METHOD"] = $post ? "POST" : "GET";
        $_POST = $post ? $post + ["csrf_token" => \CsrfToken::generate()] : [];
        $destino = null;
        ob_start();
        try { (new \PedidoController())->$metodo(); }
        catch (\RuntimeException $e) { if (str_starts_with($e->getMessage(), "redirect a ")) $destino = substr($e->getMessage(), 11); else { ob_end_clean(); throw $e; } }
        $html = (string)ob_get_clean();
        return [$html, $destino];
    }

    private function fila(): array {
        return (new \UserModel())->findById($this->uid);
    }

    private function dirValida(array $c = []): array {
        return $c + ["provincia" => "08", "distrito" => "0808", "corregimiento" => (string)$this->db->query("SELECT codigo FROM corregimientos WHERE nombre = 'Bella Vista' AND distrito_codigo = '0808'")->fetchColumn(),
            "calle" => "Calle 50", "casa" => "Casa 5-B", "barriada" => "Bella Vista", "referencia" => ""];
    }

    private function dom(string $html): \DOMXPath {
        $d = new \DOMDocument();
        @$d->loadHTML('<?xml encoding="utf-8"?>' . $html);
        return new \DOMXPath($d);
    }

    // ── Perfil ───────────────────────────────────────────────────────

    public function testPerfilGuardaElNombreYNormalizaElTelefono(): void {
        [, $destino] = $this->accion("miPerfil", ["nombre" => "Ana Pérez", "telefono" => "+507 6123 4567"]);
        $this->assertSame(APP_URL . "/?action=mi_perfil", $destino);
        $f = $this->fila();
        $this->assertSame("Ana Pérez", $f["nombre"]);
        $this->assertSame("6123-4567", $f["telefono"]);
    }

    public function testPerfilConTelefonoInvalidoNoGuardaYConservaLoEscrito(): void {
        [$html, $destino] = $this->accion("miPerfil", ["nombre" => "Ana Pérez", "telefono" => "12345"]);
        $this->assertNull($destino);
        $this->assertNotSame("Ana Pérez", $this->fila()["nombre"], "no se guarda nada si algo falla");
        $this->assertStringContainsString('value="12345"', $html);
        $this->assertStringContainsString("Escribe un teléfono de Panamá", $html);
        $this->assertStringContainsString('value="Ana Pérez"', $html);
    }

    public function testPerfilExigeElNombre(): void {
        [$html, $destino] = $this->accion("miPerfil", ["nombre" => "   ", "telefono" => ""]);
        $this->assertNull($destino);
        $this->assertStringContainsString("El nombre es requerido", $html);
    }

    // ── Dirección ────────────────────────────────────────────────────

    public function testDireccionValidaSeGuardaPorCodigos(): void {
        [, $destino] = $this->accion("miDireccion", $this->dirValida(["referencia" => "Portón blanco"]));
        $this->assertSame(APP_URL . "/?action=mi_direccion", $destino);
        $f = $this->fila();
        $this->assertSame("08", $f["dir_provincia"]);
        $this->assertSame("0808", $f["dir_distrito"]);
        $this->assertSame("Calle 50", $f["dir_calle"]);
        $this->assertSame("Portón blanco", $f["dir_referencia"]);
    }

    public function testDireccionConCombinacionInvalidaNoSeGuardaYMuestraElError(): void {
        [$html, $destino] = $this->accion("miDireccion", $this->dirValida(["distrito" => "0301"]));
        $this->assertNull($destino);
        $this->assertNull($this->fila()["dir_calle"]);
        $this->assertStringContainsString("Esa combinación", $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('value="Calle 50"', $html, "conserva lo escrito");
    }

    public function testDireccionConBasuraNoSeGuarda(): void {
        [$html, $destino] = $this->accion("miDireccion", $this->dirValida(["calle" => "asdfgh", "barriada" => "!!!!"]));
        $this->assertNull($destino);
        $this->assertNull($this->fila()["dir_calle"]);
        $this->assertStringContainsString("Revisa los campos marcados", $html);
    }

    public function testElFormularioPintaLaDireccionGuardada(): void {
        $this->accion("miDireccion", $this->dirValida());
        [$html] = $this->accion("miDireccion");
        $x = $this->dom($html);
        $this->assertSame("08", $x->query("//select[@name='provincia']/option[@selected]/@value")->item(0)->nodeValue);
        $this->assertSame("0808", $x->query("//select[@name='distrito']/option[@selected]/@value")->item(0)->nodeValue);
        $this->assertSame("Calle 50", $x->query("//input[@name='calle']/@value")->item(0)->nodeValue);
    }

    public function testGunaYalaNoMuestraDistritoPeroSiSusCorregimientos(): void {
        $this->accion("miDireccion", ["provincia" => "10", "distrito" => "", "corregimiento" => "100101", "calle" => "Calle Principal", "casa" => "Casa 3", "barriada" => "Narganá", "referencia" => ""]);
        $f = $this->fila();
        $this->assertSame("1001", $f["dir_distrito"], "el servidor completó el distrito interno");
        [$html] = $this->accion("miDireccion");
        $x = $this->dom($html);
        $this->assertNotNull($x->query("//*[@id='dir-grupo-distrito'][@hidden]")->item(0), "el distrito ficticio no se muestra");
        $this->assertNull($x->query("//*[@id='dir-grupo-corregimiento'][@hidden]")->item(0));
        $this->assertSame(5, $x->query("//select[@name='corregimiento']/option")->length, "elige + 4 corregimientos");
        $this->assertStringNotContainsString("1001", implode(",", array_map(fn($n) => $n->nodeValue, iterator_to_array($x->query("//select[@name='distrito']/option/@value")))));
    }

    public function testNasoTjerDiMuestraSuDistritoYOcultaElCorregimiento(): void {
        $this->accion("miDireccion", ["provincia" => "01", "distrito" => "0105", "corregimiento" => "", "calle" => "Camino Principal", "casa" => "Casa 2", "barriada" => "Siey", "referencia" => ""]);
        $this->assertSame("010501", $this->fila()["dir_corregimiento"]);
        [$html] = $this->accion("miDireccion");
        $x = $this->dom($html);
        $this->assertNull($x->query("//*[@id='dir-grupo-distrito'][@hidden]")->item(0));
        $this->assertNotNull($x->query("//*[@id='dir-grupo-corregimiento'][@hidden]")->item(0), "no se muestra una lista de un corregimiento inventado");
        $this->assertNotNull($x->query("//select[@name='corregimiento'][@disabled]")->item(0), "oculto y sin enviarse");
    }

    public function testLoQueEscribeLaPersonaSaleEscapadoEnLosFormularios(): void {
        $malo = '"><script>alert(1)</script>';
        [$html] = $this->accion("miDireccion", $this->dirValida(["calle" => $malo, "barriada" => $malo, "casa" => $malo, "referencia" => $malo]));
        $this->assertStringNotContainsString("<script>alert(1)", $html);
        $this->assertStringContainsString("&lt;script&gt;alert(1)&lt;/script&gt;", $html, "el valor vuelve al campo, escapado");
        $this->assertStringContainsString('class="campo-error"', $html);
        [$html] = $this->accion("miPerfil", ["nombre" => $malo, "telefono" => $malo]);
        $this->assertStringNotContainsString("<script>alert(1)", $html);
    }

    public function testSinSesionVaAlAcceso(): void {
        $_SESSION = [];
        foreach (["miPerfil", "miDireccion", "miClave"] as $m) {
            [, $destino] = $this->accion($m);
            $this->assertSame(APP_URL . "/login.php", $destino, $m);
        }
    }

    // ── Menú de la cuenta ────────────────────────────────────────────

    public function testElMenuMarcaLaSeccionActual(): void {
        foreach (["miPerfil" => "mi_perfil", "miDireccion" => "mi_direccion", "miClave" => "mi_clave"] as $metodo => $accion) {
            [$html] = $this->accion($metodo);
            $x = $this->dom($html);
            $actuales = $x->query("//nav[@aria-label='Mi cuenta']//a[@aria-current='page']");
            $this->assertSame(1, $actuales->length, $metodo);
            $this->assertSame(APP_URL . "/?action=$accion", $actuales->item(0)->attributes->getNamedItem("href")->nodeValue);
            $this->assertSame(4, $x->query("//nav[@aria-label='Mi cuenta']//li/a")->length, "perfil, pedidos, dirección y contraseña");
            $this->assertSame(1, $x->query("//nav[@aria-label='Mi cuenta']//form[@method='post'][@action='" . APP_URL . "/logout.php']")->length, "salir es POST");
            $this->assertSame(1, $x->query("//h1")->length);
        }
    }

    // ── Contraseña ───────────────────────────────────────────────────

    public function testMiClaveRechazaLaActualIncorrecta(): void {
        [$html, $destino] = $this->accion("miClave", ["actual" => "no es", "nueva" => "Otra-frase-larga-2026", "confirmar" => "Otra-frase-larga-2026"]);
        $this->assertNull($destino);
        $this->assertStringContainsString("La contraseña actual no es correcta.", $html);
    }

    // ── Selector geográfico ──────────────────────────────────────────

    public function testGeoDevuelveDistritosYCorregimientos(): void {
        [$c, $b] = \PedidoController::respuestaGeo(["nivel" => "distritos", "padre" => "08"], "GET", new \GeoPanamaModel());
        $this->assertSame(200, $c);
        $this->assertContains("San Miguelito", array_column($b["items"], "nombre"));
        $this->assertNull($b["implicito"]);
        [$c, $b] = \PedidoController::respuestaGeo(["nivel" => "corregimientos", "padre" => "0808"], "GET", new \GeoPanamaModel());
        $this->assertSame(200, $c);
        $this->assertContains("Bella Vista", array_column($b["items"], "nombre"));
        $this->assertSame(["codigo", "nombre"], array_keys($b["items"][0]), "solo código y nombre");
    }

    public function testGeoGunaYalaYNasoNoInventanNiOmiten(): void {
        [, $b] = \PedidoController::respuestaGeo(["nivel" => "distritos", "padre" => "10"], "GET", new \GeoPanamaModel());
        $this->assertSame([], $b["items"]);
        $this->assertSame("1001", $b["implicito"]);
        [, $b] = \PedidoController::respuestaGeo(["nivel" => "corregimientos", "padre" => "1001"], "GET", new \GeoPanamaModel());
        $this->assertCount(4, $b["items"]);
        [, $b] = \PedidoController::respuestaGeo(["nivel" => "corregimientos", "padre" => "0105"], "GET", new \GeoPanamaModel());
        $this->assertSame([], $b["items"], "su corregimiento es implícito");
    }

    public static function padresInvalidos(): array {
        return array_map(fn($v) => [$v], ["", "8", "088", "0a", "08 ", "08\n", "' OR 1=1", "../", "٠٨", "0808", "-1", ["08"]]);
    }

    #[DataProvider("padresInvalidos")]
    public function testGeoRechazaPadresQueNoSonDigitosDelLargoExacto(mixed $padre): void {
        [$c, $b] = \PedidoController::respuestaGeo(["nivel" => "distritos", "padre" => $padre], "GET", new \GeoPanamaModel());
        $this->assertSame(400, $c, json_encode($padre));
        $this->assertArrayHasKey("error", $b);
    }

    public function testGeoRechazaNivelesInvalidosYMetodosQueNoSonGet(): void {
        $g = new \GeoPanamaModel();
        $this->assertSame(400, \PedidoController::respuestaGeo(["nivel" => "provincias", "padre" => "08"], "GET", $g)[0]);
        $this->assertSame(400, \PedidoController::respuestaGeo(["padre" => "08"], "GET", $g)[0]);
        $this->assertSame(400, \PedidoController::respuestaGeo(["nivel" => "corregimientos", "padre" => "08"], "GET", $g)[0], "para corregimientos el padre tiene 4 dígitos");
        $this->assertSame(405, \PedidoController::respuestaGeo(["nivel" => "distritos", "padre" => "08"], "POST", $g)[0]);
    }
}
