<?php
namespace Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BaseDatos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/**
 * S5.4: formularios del panel (columna de 720 px, barra de acciones, volver como enlace), avisos de error con el componente
 * de alerta, y el enlace de volver del acceso con su flecha.
 */
final class FormulariosPanelTest extends BaseDatos {
    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
    }

    /** @return array<string,array{0:string,1:bool}> pantalla => [pantalla, lleva enlace de volver] */
    public static function formularios(): array {
        return [
            "partes crear" => ["admin_partes_crear", true],
            "partes editar" => ["admin_partes_editar", true],
            "autos crear" => ["admin_autos_crear", true],
            "secciones crear" => ["admin_secciones_crear", true],
            "usuarios crear" => ["admin_usuarios_crear", true],
            "usuarios editar" => ["admin_usuarios_editar", true],
            "roles crear" => ["admin_roles_crear", true],
            "roles editar" => ["admin_roles_editar", true],
            "ventas crear" => ["admin_ventas_crear", true],
            "mi contraseña" => ["admin_usuarios_clave", false],
        ];
    }

    #[DataProvider("formularios")]
    public function testElFormularioUsaLaColumnaLaBarraDeAccionesYElEnlaceDeVolver(string $pantalla, bool $volver): void {
        $x = Html::xpath(Pantallas::render($this->db, $pantalla));
        $this->assertSame(1, Html::contar($x, "//main/div[contains(@class,'form-panel')]//div[contains(@class,'form-card')]//form"), "$pantalla: formulario dentro de su tarjeta de 720 px");
        $this->assertSame(1, Html::contar($x, "//h1[contains(@class,'panel-titulo')]"), "$pantalla: un h1");
        $this->assertSame(0, Html::contar($x, "//h1//i"), "$pantalla: sin icono en el título");
        $this->assertSame(1, Html::contar($x, "//form//div[contains(@class,'form-acciones')]//button[@type='submit']"), "$pantalla: una barra de acciones con un solo botón de enviar");
        $this->assertSame(1, Html::contar($x, "//main//form//input[@name='csrf_token']"), "$pantalla: con token");
        $this->assertSame(0, Html::contar($x, "//*[contains(@class,'btn-back') or contains(@class,'dark-card') or contains(@class,'alert-dark-danger')]"), "$pantalla: sin los componentes viejos");
        $this->assertSame(0, Html::contar($x, "//main//*[contains(@style,'max-width')]"), "$pantalla: el ancho sale de la clase, no de un estilo en línea");
        $v = $x->query("//main//a[contains(@class,'enlace-volver')]");
        $this->assertSame($volver ? 1 : 0, $v->length, $pantalla);
        if ($volver) {
            $this->assertSame(1, (new \DOMXPath($v->item(0)->ownerDocument))->query("./i[@aria-hidden='true' and contains(@class,'bi-arrow-left')]", $v->item(0))->length, "$pantalla: flecha decorativa a la izquierda");
            $this->assertStringStartsWith("Volver a ", trim($v->item(0)->textContent), "$pantalla: el enlace dice a dónde vuelve");
            $this->assertSame(0, Html::contar($x, "//main//a[@aria-label='Volver']"), "$pantalla: ya no hay un enlace que solo dice «Volver»");
        }
    }

    #[DataProvider("formularios")]
    public function testLasEtiquetasSiguenVisiblesYSeEscribenSinMayusculasEscritas(string $pantalla): void {
        $x = Html::xpath(Pantallas::render($this->db, $pantalla));
        foreach ($x->query("//main//label") as $l) {
            $this->assertSame(trim($l->textContent), $l->textContent === mb_strtoupper($l->textContent) && mb_strlen(trim($l->textContent)) > 3 ? "__MAYUSCULAS__" : trim($l->textContent), "$pantalla: etiqueta escrita en mayúsculas: " . trim($l->textContent));
        }
    }

    private function render(string $vista, array $v): \DOMXPath {
        extract($v);
        $title = "";
        ob_start();
        try { include BASE_PATH . "/app/Views/admin/$vista.php"; } finally { $html = (string)ob_get_clean(); }
        return Html::xpath($html);
    }

    public function testUnErrorSeMuestraConElComponenteDeAlertaConRoleYSinColorSuelto(): void {
        $_SESSION["user"] = ["id" => 1, "nombre" => "Admin", "email" => "a@example.invalid"];
        try {
            foreach (["autos/form" => [], "secciones/form" => [], "ventas/form" => ["partes" => []]] as $vista => $extra) {
                $x = $this->render($vista, ["error" => "No se pudo guardar <b>esto</b>"] + $extra);
                $a = $x->query("//main//div[@role='alert' and contains(@class,'alert-danger')]");
                $this->assertSame(1, $a->length, "$vista: aviso de error");
                $this->assertStringContainsString("alert", $a->item(0)->getAttribute("class"));
                $this->assertSame(1, (new \DOMXPath($a->item(0)->ownerDocument))->query("./i[@aria-hidden='true']", $a->item(0))->length, "$vista: icono decorativo");
                $this->assertSame("No se pudo guardar <b>esto</b>", trim($a->item(0)->textContent), "$vista: el mensaje se escapa");
                $this->assertSame(0, $x->query("//main//*[contains(@class,'text-danger')]")->length, "$vista: el color lo da el componente");
            }
        } finally { unset($_SESSION["user"]); }
    }

    public function testSinErrorNoHayAviso(): void {
        $x = Html::xpath(Pantallas::render($this->db, "admin_autos_crear"));
        $this->assertSame(0, Html::contar($x, "//main//div[@role='alert']"));
    }

    // ── Acceso: «Volver…» como enlace ──

    /** @return array<string,array{0:string,1:string,2:string}> */
    public static function accesos(): array {
        return [
            "login" => ["login", "/", "Volver a la tienda"],
            "registro" => ["registro", "/", "Volver a la tienda"],
            "olvide" => ["olvide", "/login.php", "Volver a iniciar sesión"],
            "restablecer inválido" => ["restablecer_invalido", "/login.php", "Volver a iniciar sesión"],
            "restablecer válido" => ["restablecer_valido", "/login.php", "Volver a iniciar sesión"],
        ];
    }

    #[DataProvider("accesos")]
    public function testElEnlaceDeVolverDelAccesoLlevaFlechaYEsUnEnlace(string $pantalla, string $ruta, string $texto): void {
        $x = Html::xpath(Pantallas::render($this->db, $pantalla));
        $a = $x->query("//main//a[contains(@class,'enlace-volver')]");
        $this->assertSame(1, $a->length, $pantalla);
        $this->assertSame(APP_URL . $ruta, $a->item(0)->getAttribute("href"));
        $this->assertSame($texto, trim($a->item(0)->textContent));
        $hijos = [];
        foreach ($a->item(0)->childNodes as $n) $hijos[] = $n->nodeName;
        $this->assertSame(["i", "#text"], $hijos, "$pantalla: la flecha va a la izquierda del texto");
        $i = $x->query("./i", $a->item(0))->item(0);
        $this->assertSame("true", $i->getAttribute("aria-hidden"));
        $this->assertStringContainsString("bi-arrow-left", $i->getAttribute("class"));
        $this->assertStringNotContainsString("text-secondary", $a->item(0)->getAttribute("class"), "no es texto gris: es un enlace");
        $this->assertStringContainsString("acceso-enlace", $a->item(0)->getAttribute("class"));
    }

    // ── Hojas de estilo ──

    private static function regla(string $archivo, string $selector): string {
        $css = (string)file_get_contents(BASE_PATH . "/assets/css/$archivo");
        preg_match('/(?:^|[}\s{])' . preg_quote($selector, "/") . '\s*\{([^}]*)\}/m', $css, $m);
        return $m[1] ?? "";
    }

    public function testElEnlaceDeVolverSigueLaEspecificacion(): void {
        $r = self::regla("base.css", ".enlace-volver");
        $this->assertMatchesRegularExpression('/display:\s*inline-flex/', $r);
        $this->assertMatchesRegularExpression('/min-height:\s*44px/', $r, "zona táctil de 44 px");
        $this->assertMatchesRegularExpression('/font-weight:\s*600/', $r);
        $this->assertMatchesRegularExpression('/color:\s*var\(--link\)/', $r, "color de enlace de DESIGN.md");
        $this->assertStringNotContainsString("underline", $r, "sin subrayado en reposo");
        $h = self::regla("base.css", ".enlace-volver:hover, .enlace-volver:focus-visible");
        $this->assertMatchesRegularExpression('/text-decoration:\s*underline/', $h, "con subrayado al pasar el cursor");
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,8}\b/', $r . $h);
    }

    public function testLaColumnaDelFormularioEsDe720YLaBarraDeAccionesSePegaAbajoEnMovil(): void {
        $css = (string)file_get_contents(BASE_PATH . "/assets/css/panel.css");
        $this->assertMatchesRegularExpression('/max-width:\s*720px/', self::regla("panel.css", ".form-panel"));
        $this->assertMatchesRegularExpression('/margin-inline:\s*auto/', self::regla("panel.css", ".form-panel"));
        $this->assertMatchesRegularExpression('/max-width:\s*480px/', self::regla("panel.css", ".form-panel-estrecho"), "mi contraseña, más estrecho");
        $this->assertMatchesRegularExpression('/border-radius:\s*var\(--rounded-xl\)/', self::regla("panel.css", ".form-card"));
        $this->assertMatchesRegularExpression('/@media \(max-width: 767\.98px\)\s*\{\s*\.form-acciones\s*\{[^}]*position:\s*sticky;\s*bottom:\s*0/', $css, "barra fija abajo en móvil");
        $this->assertMatchesRegularExpression('/\.form-acciones \.btn\s*\{\s*flex:\s*1;\s*min-height:\s*44px/', $css, "botones de 44 px en móvil");
        $e = self::regla("panel.css", ".form-label-dark");
        $this->assertMatchesRegularExpression('/text-transform:\s*none/', $e, "etiquetas sin mayúsculas forzadas");
        $this->assertMatchesRegularExpression('/display:\s*block/', $e);
    }

    public function testNingunaVistaDelPanelUsaYaElBotonDeVolverCuadrado(): void {
        foreach (glob(BASE_PATH . "/app/Views/admin/*/*.php") as $f) {
            if (str_ends_with($f, "pedidos/detalle.php") || str_ends_with($f, "pedidos\detalle.php")) continue; // el detalle del pedido es de S6
            $src = (string)file_get_contents($f);
            $this->assertStringNotContainsString("btn-back", $src, basename(dirname($f)) . "/" . basename($f));
            $this->assertStringNotContainsString("alert-dark-danger", $src, basename(dirname($f)) . "/" . basename($f));
        }
    }
}
