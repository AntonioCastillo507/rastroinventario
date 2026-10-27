<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/** S6c.5: filtros de la lista de Partes (buscador, sección, estado de stock, orden), fichas y paginación. */
final class PartesFiltrosTest extends BaseDatos {
    private int $secA;
    private int $secB;

    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
        Pantallas::render($this->db, "admin_partes"); // crea el administrador y sus datos antes de ajustar las piezas
        $this->db->exec("UPDATE partes SET activo=0");
        $this->db->exec("INSERT INTO secciones (nombre) VALUES ('Filtro A S6c5')"); $this->secA = (int)$this->db->lastInsertId();
        $this->db->exec("INSERT INTO secciones (nombre) VALUES ('Filtro B S6c5')"); $this->secB = (int)$this->db->lastInsertId();
        foreach ([["Pieza A-disp", $this->secA, 20], ["Pieza A-bajo", $this->secA, 3], ["Pieza A-agot", $this->secA, 0], ["Pieza B-disp", $this->secB, 9], ["Pieza B-bajo", $this->secB, 5]] as [$n, $s, $c]) {
            $this->db->prepare("INSERT INTO partes (nombre, seccion_id, precio, cantidad, activo) VALUES (?,?,10,?,1)")->execute([$n, $s, $c]);
        }
    }

    private function nombres(string $q = "", string $filtro = "", string $orden = "recientes", int $sec = 0): array {
        $m = new \ParteModel();
        return array_map(fn($f) => $f["nombre"], $m->findAllWithDetails($q, $filtro, $orden, $sec));
    }

    public function testElFiltroPorSeccionDevuelveSoloLasPiezasDeEsaSeccion(): void {
        $this->assertEqualsCanonicalizing(["Pieza A-disp", "Pieza A-bajo", "Pieza A-agot"], $this->nombres("", "", "recientes", $this->secA));
        $this->assertEqualsCanonicalizing(["Pieza B-disp", "Pieza B-bajo"], $this->nombres("", "", "recientes", $this->secB));
        $this->assertSame(2, (new \ParteModel())->contarAdmin("", "", $this->secB), "el conteo usa el mismo filtro");
    }

    public function testElFiltroDeStockSeparaDisponibleBajoYAgotado(): void {
        $this->assertEqualsCanonicalizing(["Pieza A-disp", "Pieza B-disp"], $this->nombres("", "disponible"));
        $this->assertEqualsCanonicalizing(["Pieza A-bajo", "Pieza B-bajo"], $this->nombres("", "stock_bajo"), "1 a 5, el 5 cuenta como bajo");
        $this->assertSame(["Pieza A-agot"], $this->nombres("", "agotado"));
        $this->assertCount(5, $this->nombres("", "all"));
    }

    public function testSeccionStockYBusquedaSeCombinan(): void {
        $this->assertSame(["Pieza B-bajo"], $this->nombres("Pieza", "stock_bajo", "recientes", $this->secB));
        $this->assertSame([], $this->nombres("A-", "agotado", "recientes", $this->secB));
    }

    public function testLosParametrosNoSeInterpolanEnLaConsulta(): void {
        $this->assertSame([], $this->nombres("' OR 1=1 -- "), "una comilla en la búsqueda es solo texto");
        $src = (string)file_get_contents(BASE_PATH . "/app/Models/ParteModel.php");
        $this->assertStringContainsString('p.seccion_id=?', $src);
    }

    public function testLaFichaQuitaSoloSuFiltroYConservaLosDemas(): void {
        $secs = [["id" => $this->secA, "nombre" => "Filtro A S6c5"]];
        $f = \ParteController::fichas("faro", "agotado", "precio_asc", $this->secA, $secs, "/admin/");
        $this->assertCount(4, $f);
        $this->assertSame(["Búsqueda: «faro»", "Sección: Filtro A S6c5", "Stock: Agotado", "Orden: Precio ↑"], array_column($f, 0));
        parse_str((string)parse_url($f[1][1], PHP_URL_QUERY), $sinSeccion);
        $this->assertSame(["mod" => "partes", "q" => "faro", "filtro" => "agotado", "orden" => "precio_asc"], $sinSeccion);
        $this->assertSame([], \ParteController::fichas("", "all", "recientes", 0, [], "/admin/"), "sin filtros no hay fichas");
    }

    public function testLaListaMuestraSelectDeSeccionFichasYLimpiarSoloConFiltros(): void {
        $x = Html::xpath($this->renderIndex(["mod" => "partes", "seccion" => (string)$this->secA, "filtro" => "stock_bajo"]));
        $this->assertSame(3, Html::contar($x, "//form[@id='fAdm']/select[@aria-label]"));
        $this->assertSame(1, Html::contar($x, "//form[@id='fAdm']/select[@name='seccion']/option[@selected][@value='{$this->secA}']"));
        $this->assertSame(1, Html::contar($x, "//form[@id='fAdm']/select[@name='filtro']/option[@selected][@value='stock_bajo']"));
        $this->assertSame(2, Html::contar($x, "//ul[contains(@class,'lista-fichas')]//a[contains(@class,'lista-ficha')]"));
        $this->assertSame(1, Html::contar($x, "//ul[contains(@class,'lista-fichas')]//a[contains(@class,'lista-filtros-limpiar')]"));
        $this->assertSame(1, Html::contar($x, "//tbody/tr/td//span[contains(@class,'celda-titulo')][.='Pieza A-bajo']"));
        $this->assertSame(0, Html::contar($x, "//tbody/tr/td//span[contains(@class,'celda-titulo')][.='Pieza A-disp']"));

        $x = Html::xpath($this->renderIndex(["mod" => "partes"]));
        $this->assertSame(0, Html::contar($x, "//ul[contains(@class,'lista-fichas')]"));
        $this->assertSame(0, Html::contar($x, "//a[contains(@class,'lista-filtros-limpiar')]"));
    }

    public function testUnValorDeStockDesconocidoSeIgnora(): void {
        $x = Html::xpath($this->renderIndex(["mod" => "partes", "filtro" => "inventado", "orden" => "inventado"]));
        $this->assertSame(0, Html::contar($x, "//ul[contains(@class,'lista-fichas')]"));
        $this->assertSame(5, Html::contar($x, "//tbody/tr/td//span[contains(@class,'celda-titulo')]"));
    }

    public function testLosFiltrosSeMantienenAlPaginar(): void {
        for ($i = 1; $i <= 25; $i++) $this->db->prepare("INSERT INTO partes (nombre, seccion_id, precio, cantidad, activo) VALUES (?,?,10,2,1)")->execute(["Masiva $i", $this->secA]);
        $x = Html::xpath($this->renderIndex(["mod" => "partes", "seccion" => (string)$this->secA, "filtro" => "stock_bajo"]));
        $href = $x->query("//nav[contains(@class,'paginacion')]//a[contains(@class,'page-link')][@aria-label='Página 2']")->item(0);
        $this->assertNotNull($href, "hay segunda página");
        parse_str((string)parse_url($href->getAttribute("href"), PHP_URL_QUERY), $q);
        $this->assertSame((string)$this->secA, $q["seccion"]);
        $this->assertSame("stock_bajo", $q["filtro"]);
        $this->assertSame("2", $q["page"]);
    }

    private function renderIndex(array $get): string { return Pantallas::render($this->db, "admin_partes", $get); }
}
