<?php
namespace Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BaseDatos;
use Tests\Support\Html;
use Tests\Support\Volumen;

/**
 * Las pantallas paginadas, ejecutadas en proceso con $_GET: filas por página, barra de páginas,
 * filtros conservados en los enlaces y páginas raras en la URL.
 */
final class PaginacionPantallasTest extends BaseDatos {
    private array $ids;

    protected function setUp(): void {
        parent::setUp();
        $this->ids = Volumen::crear($this->db);
        // Un administrador de prueba con sesión, para pasar requirePermission
        $uid = (int)$this->db->query("SELECT id FROM usuarios WHERE email = 'volumen.1@example.invalid'")->fetchColumn();
        $this->db->prepare("INSERT INTO usuario_roles (usuario_id, rol_id) SELECT ?, id FROM roles WHERE nombre = 'Administrador'")->execute([$uid]);
        $_SESSION["user"] = ["id" => $uid, "nombre" => "Admin de prueba", "email" => "volumen.1@example.invalid"];
        $_SESSION["user_roles"] = ["Administrador"];
        $_GET = [];
    }

    protected function tearDown(): void {
        $_GET = [];
        unset($_SESSION["user"], $_SESSION["user_roles"]);
        parent::tearDown();
    }

    private function pantalla(callable $accion, array $get = []): \DOMXPath {
        $_GET = $get;
        ob_start();
        try { $accion(); } finally { $html = (string)ob_get_clean(); }
        $this->assertStringNotContainsString("Warning:", $html);
        $this->assertStringNotContainsString("Fatal error", $html);
        return Html::xpath($html);
    }

    private function filas(\DOMXPath $x): int { return Html::contar($x, "//table//tbody/tr"); }

    // ── Panel ───────────────────────────────────────────────────────────

    public static function tablasDelPanel(): array {
        // [módulo, clase del controlador, filas totales esperadas]
        return [
            "ventas"      => ["ventas", \VentaController::class, Volumen::OTROS],
            "pedidos"     => ["pedidos", \PedidoAdminController::class, Volumen::OTROS],
            "usuarios"    => ["usuarios", \UserController::class, Volumen::OTROS],
            "comentarios" => ["comentarios", \ComentarioController::class, Volumen::OTROS],
        ];
    }

    #[DataProvider("tablasDelPanel")]
    public function testTablasDelPanelDe20EnPagina(string $mod, string $clase, int $total): void {
        $c = new $clase();
        $this->assertSame([20, 20, 5], array_map(fn($p) => $this->filas($this->pantalla(fn() => $c->index(), ["page" => (string)$p])), [1, 2, 3]));
        $x = $this->pantalla(fn() => $c->index(), []);
        $this->assertSame("Mostrando 1–20 de $total", Html::resumen($x));
        $this->assertContains(APP_URL . "/admin/?mod=$mod&page=2", Html::enlacesDePaginacion($x));
        $this->assertNotContains(APP_URL . "/admin/?mod=$mod&page=1", Html::enlacesDePaginacion($x), "la página 1 es la URL normal");
    }

    #[DataProvider("tablasDelPanel")]
    public function testPaginasRarasNoRompenElPanel(string $mod, string $clase, int $total): void {
        $c = new $clase();
        $primera = $this->filas($this->pantalla(fn() => $c->index(), []));
        foreach (["-1", "0", "abc", "2abc", "1e3", "3.5", ""] as $raro) {
            $this->assertSame($primera, $this->filas($this->pantalla(fn() => $c->index(), ["page" => $raro])), "page=$raro");
        }
        foreach (["9999", str_repeat("9", 30)] as $enorme) {
            $x = $this->pantalla(fn() => $c->index(), ["page" => $enorme]);
            $this->assertSame(5, $this->filas($x), "page=$enorme muestra la última");
            $this->assertStringContainsString("Mostrando 41–45 de $total", Html::resumen($x));
        }
        $x = $this->pantalla(fn() => $c->index(), ["page" => ["1"]]);   // ?page[]=1
        $this->assertSame($primera, $this->filas($x));
    }

    public function testPartesDelPanelConSusFiltrosEnLosEnlaces(): void {
        $c = new \ParteController();
        $get = ["q" => "Pieza Volumen", "filtro" => "con_stock", "orden" => "precio_desc"];
        $x = $this->pantalla(fn() => $c->index(), $get + ["page" => "2"]);

        $pm = new \ParteModel();
        $total = $pm->contarAdmin("Pieza Volumen", "con_stock", 0);
        $pag = \Paginador::crear("2", $total, \Paginador::PANEL);
        $esperadas = array_column($pm->findAllWithDetails("Pieza Volumen", "con_stock", "precio_desc", 0, $pag), "id");
        $this->assertSame(count($esperadas), $this->filas($x));
        $this->assertSame("Mostrando " . $pag->desdeFila() . "–" . $pag->hastaFila() . " de $total", Html::resumen($x));

        foreach (Html::enlacesDePaginacion($x) as $href) {
            parse_str((string)parse_url($href, PHP_URL_QUERY), $q);
            $this->assertSame("partes", $q["mod"], $href);
            $this->assertSame("Pieza Volumen", $q["q"], $href);
            $this->assertSame("con_stock", $q["filtro"], $href);
            $this->assertSame("precio_desc", $q["orden"], $href);
        }
    }

    public function testPartesDelPanelSinFiltrosNoLlevanParametrosDeMas(): void {
        $x = $this->pantalla(fn() => (new \ParteController())->index(), []);
        $this->assertContains(APP_URL . "/admin/?mod=partes&page=2", Html::enlacesDePaginacion($x));
    }

    public function testSiCabeEnUnaPaginaNoHayBarra(): void {
        $x = $this->pantalla(fn() => (new \ParteController())->index(), ["filtro" => "agotado"]);
        $this->assertSame([], Html::enlacesDePaginacion($x));
        $this->assertSame("", Html::resumen($x));
        $this->assertGreaterThan(0, $this->filas($x));
    }

    // ── Catálogo y portada ──────────────────────────────────────────────────

    private function tarjetas(\DOMXPath $x): int { return Html::contar($x, "//div[@class='pub-card']"); }

    private function catalogo(array $get = []): \DOMXPath {
        return $this->pantalla(fn() => (new \PublicController())->home(), ["ver" => "catalogo"] + $get);
    }

    public function testCatalogoDe12EnPagina(): void {
        $total = (new \ParteModel())->contarPublic();
        $ultima = (int)ceil($total / 12);
        $this->assertSame(12, $this->tarjetas($this->catalogo()));
        $this->assertSame(12, $this->tarjetas($this->catalogo(["page" => "2"])));
        $x = $this->catalogo(["page" => (string)$ultima]);
        $this->assertSame($total - 12 * ($ultima - 1), $this->tarjetas($x));
        $this->assertSame("Mostrando " . (12 * ($ultima - 1) + 1) . "–$total de $total", Html::resumen($x));
    }

    public function testCatalogoConPaginasRaras(): void {
        $primera = $this->tarjetas($this->catalogo());
        foreach (["-1", "0", "abc", "2abc", "1e3", ["1"]] as $raro) {
            $this->assertSame($primera, $this->tarjetas($this->catalogo(["page" => $raro])), json_encode($raro));
        }
        $total = (new \ParteModel())->contarPublic();
        $ultima = (int)ceil($total / 12);
        $this->assertSame($total - 12 * ($ultima - 1), $this->tarjetas($this->catalogo(["page" => "99999"])));
    }

    public function testCatalogoConservaLosFiltrosEnLosEnlaces(): void {
        $get = ["q" => "Pieza Volumen", "seccion" => (string)$this->ids["secciones"][2], "marca" => "Volumen Gamma",
                "precio_min" => "10", "precio_max" => "50", "orden" => "precio_desc"];
        $pm = new \ParteModel();
        $total = $pm->contarPublic("Pieza Volumen", $this->ids["secciones"][2], "Volumen Gamma", 10, 50);
        $this->assertGreaterThan(12, $total, "para que haya segunda página");
        $x = $this->catalogo($get);
        $this->assertSame(min(12, $total), $this->tarjetas($x));
        $enlaces = Html::enlacesDePaginacion($x);
        if ($total > 12) {
            $this->assertNotSame([], $enlaces);
            foreach ($enlaces as $href) {
                parse_str((string)parse_url($href, PHP_URL_QUERY), $q);
                $this->assertSame("catalogo", $q["ver"], $href);
                $this->assertSame("Pieza Volumen", $q["q"], $href);
                $this->assertSame("Volumen Gamma", $q["marca"], $href);
                $this->assertSame("10", $q["precio_min"], $href);
                $this->assertSame("50", $q["precio_max"], $href);
                $this->assertSame("precio_desc", $q["orden"], $href);
            }
        } else {
            $this->assertSame([], $enlaces, "una sola página, sin barra");
        }
    }

    public function testCatalogoSinResultadosNoPintaBarra(): void {
        $x = $this->catalogo(["q" => "esto no existe en el catálogo"]);
        $this->assertSame(0, $this->tarjetas($x));
        $this->assertSame([], Html::enlacesDePaginacion($x));
    }

    public function testElTextoDeBusquedaPeligrosoNoSeInyectaEnLosEnlaces(): void {
        $x = $this->catalogo(["q" => 'Pieza"><script>alert(1)</script>']);
        $this->assertSame(0, $this->tarjetas($x));
        $this->assertSame(0, Html::contar($x, "//script[contains(., 'alert(1)')]"));
    }

    public function testLaPortadaSigueConSusOchoDestacadosSinBarra(): void {
        $x = $this->pantalla(fn() => (new \PublicController())->home(), []);
        $this->assertSame(8, $this->tarjetas($x));
        $this->assertSame([], Html::enlacesDePaginacion($x));
        $x2 = $this->pantalla(fn() => (new \PublicController())->home(), ["page" => "3"]);
        $this->assertSame(8, $this->tarjetas($x2), "?page= sin ver=catalogo sigue siendo la portada");
    }
}
