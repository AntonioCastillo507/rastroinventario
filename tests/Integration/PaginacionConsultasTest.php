<?php
namespace Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BaseDatos;
use Tests\Support\Volumen;

/**
 * Consultas paginadas con datos de volumen (valores repetidos a propósito): al recorrer todas las
 * páginas salen las mismas filas, en el mismo orden que sin paginar, sin repetir ni saltar ninguna.
 */
final class PaginacionConsultasTest extends BaseDatos {
    private array $ids;

    protected function setUp(): void {
        parent::setUp();
        $this->ids = Volumen::crear($this->db);
    }

    /**
     * @param callable(\Paginador|null):list<array> $filas trae las filas (todas con null, una página con un Paginador)
     * @param callable():int $total
     */
    private function recorrer(string $que, callable $filas, callable $total, int $tam): void {
        $todo   = array_column($filas(null), "id");
        $this->assertCount(count(array_unique($todo)), $todo, "$que: la consulta completa ya repite ids");
        $this->assertSame(count($todo), $total(), "$que: el total no coincide con las filas");
        $paginas = max(1, (int)ceil(count($todo) / $tam));

        $juntas = [];
        for ($n = 1; $n <= $paginas; $n++) {
            $pag = \Paginador::crear((string)$n, $total(), $tam);
            $this->assertSame($n, $pag->pagina);
            $pagina = array_column($filas($pag), "id");
            $esperadas = $n < $paginas ? $tam : count($todo) - $tam * ($paginas - 1);
            $this->assertCount($esperadas, $pagina, "$que: filas de la página $n");
            array_push($juntas, ...$pagina);
        }
        $this->assertSame($todo, $juntas, "$que (de a $tam): las páginas juntas no son la consulta completa en el mismo orden");

        // Página fuera de rango = la última
        $ultima = array_column($filas(\Paginador::crear("9999", $total(), $tam)), "id");
        $this->assertSame(array_slice($todo, $tam * ($paginas - 1)), $ultima, "$que: página enorme");
    }

    public static function tamanos(): array {
        return ["12 (catálogo)" => [12], "20 (panel)" => [20], "7 (más fronteras)" => [7]];
    }

    // ── Panel: partes ───────────────────────────────────────────────────────

    public static function ordenesYFiltros(): array {
        $c = [];
        foreach (["recientes", "precio_asc", "precio_desc", "stock_asc", "stock_desc", "nombre", "mas_vendidas"] as $o) {
            foreach (["all", "con_stock", "stock_bajo", "agotado"] as $f) $c["$o / $f"] = [$o, $f];
        }
        return $c;
    }

    #[DataProvider("ordenesYFiltros")]
    public function testPartesDelPanelCadaOrdenYFiltro(string $orden, string $filtro): void {
        $pm = new \ParteModel();
        foreach ([7, 20] as $tam) {
            $this->recorrer("partes $orden/$filtro", fn($pag) => $pm->findAllWithDetails("", $filtro, $orden, 0, $pag),
                fn() => $pm->contarAdmin("", $filtro, 0), $tam);
        }
    }

    public function testPartesDelPanelConBusquedaYSeccion(): void {
        $pm = new \ParteModel();
        $sec = $this->ids["secciones"][0];
        foreach (["Pieza Volumen 3", "Volumen Beta", "Descripción"] as $q) {
            $this->recorrer("partes q=$q", fn($pag) => $pm->findAllWithDetails($q, "all", "precio_asc", 0, $pag), fn() => $pm->contarAdmin($q, "all", 0), 7);
        }
        $this->recorrer("partes sección", fn($pag) => $pm->findAllWithDetails("", "all", "nombre", $sec, $pag), fn() => $pm->contarAdmin("", "all", $sec), 7);
        $this->recorrer("partes q+sección+filtro", fn($pag) => $pm->findAllWithDetails("Pieza", "con_stock", "stock_desc", $sec, $pag), fn() => $pm->contarAdmin("Pieza", "con_stock", $sec), 7);
    }

    public function testElPanelSoloListaLasPiezasActivas(): void {
        $pm = new \ParteModel();
        $inactivas = (int)$this->db->query("SELECT COUNT(*) FROM partes WHERE activo = 0")->fetchColumn();
        $this->assertGreaterThan(0, $inactivas, "los datos de volumen incluyen piezas inactivas");
        $this->assertSame(Volumen::PARTES - $inactivas, $pm->contarAdmin());
    }

    // ── Catálogo público ────────────────────────────────────────────────────

    public static function ordenesPublicos(): array {
        $c = [];
        foreach (["recientes", "precio_asc", "precio_desc", "stock", "nombre"] as $o) $c[$o] = [$o];
        return $c;
    }

    #[DataProvider("ordenesPublicos")]
    public function testCatalogoCadaOrden(string $orden): void {
        $pm = new \ParteModel();
        foreach ([7, 12] as $tam) {
            $this->recorrer("catálogo $orden", fn($pag) => $pm->findPublic("", 0, 0, "", 0, 0, $orden, $pag), fn() => $pm->contarPublic(), $tam);
        }
    }

    public function testCatalogoConFiltros(): void {
        $pm = new \ParteModel();
        $sec = $this->ids["secciones"][1];
        $casos = [
            "búsqueda"        => ["Pieza Volumen 2", 0, "", 0, 0],
            "búsqueda marca"  => ["Volumen Gamma", 0, "", 0, 0],
            "sección"         => ["", $sec, "", 0, 0],
            "marca"           => ["", 0, "Volumen Beta", 0, 0],
            "precio mínimo"   => ["", 0, "", 30, 0],
            "precio máximo"   => ["", 0, "", 0, 30],
            "rango"           => ["", 0, "", 20, 40],
            "todo junto"      => ["Pieza", $sec, "Volumen Alfa", 10, 50],
        ];
        foreach ($casos as $nombre => [$q, $s, $m, $min, $max]) {
            foreach (["precio_desc", "nombre"] as $orden) {
                $this->recorrer("catálogo $nombre/$orden", fn($pag) => $pm->findPublic($q, $s, 0, $m, (float)$min, (float)$max, $orden, $pag),
                    fn() => $pm->contarPublic($q, $s, $m, (float)$min, (float)$max), 7);
            }
        }
    }

    public function testElCatalogoSoloMuestraPiezasActivasYConStock(): void {
        $pm = new \ParteModel();
        foreach ($pm->findPublic() as $p) {
            $this->assertSame(1, (int)$p["activo"]);
            $this->assertGreaterThan(0, (int)$p["cantidad"]);
        }
    }

    public function testLaPortadaSigueConSusDestacadosSinPaginar(): void {
        $pm = new \ParteModel();
        $this->assertCount(8, $pm->findFeatured(8));
        $this->assertCount(3, $pm->findPublic("", 0, 3), "el límite clásico sigue funcionando");
        $this->assertGreaterThan(8, count($pm->findPublic()), "sin Paginador ni límite devuelve todo, como antes");
    }

    // ── Panel: ventas, pedidos, usuarios y comentarios ───────────────────────

    #[DataProvider("tamanos")]
    public function testVentas(int $tam): void {
        $m = new \VentaModel();
        $this->recorrer("ventas", fn($pag) => $m->findAllWithDetails($pag), fn() => $m->contar(), $tam);
    }

    #[DataProvider("tamanos")]
    public function testPedidos(int $tam): void {
        $m = new \PedidoModel();
        $this->recorrer("pedidos", fn($pag) => $m->findAllWithItems($pag), fn() => $m->contar(), $tam);
        foreach ($m->findAllWithItems(\Paginador::crear("1", $m->contar(), $tam)) as $p) {
            $this->assertSame(1, (int)$p["total_items"], "el conteo de ítems por pedido no se duplica con la paginación");
        }
    }

    #[DataProvider("tamanos")]
    public function testUsuarios(int $tam): void {
        $m = new \UserModel();
        $this->recorrer("usuarios", fn($pag) => $m->findAllWithRoles($pag), fn() => $m->contar(), $tam);
    }

    #[DataProvider("tamanos")]
    public function testComentarios(int $tam): void {
        $m = new \ComentarioModel();
        $this->recorrer("comentarios", fn($pag) => $m->findAllWithParte($pag), fn() => $m->contar(), $tam);
    }

    public function testUnUsuarioConVariosRolesCuentaUnaSolaVez(): void {
        $m = new \UserModel();
        $uid = (int)$this->db->query("SELECT id FROM usuarios WHERE email = 'volumen.1@example.invalid'")->fetchColumn();
        $this->db->exec("INSERT INTO usuario_roles (usuario_id, rol_id) SELECT $uid, id FROM roles WHERE nombre IN ('Vendedor','Consultor')");
        $this->recorrer("usuarios con roles", fn($pag) => $m->findAllWithRoles($pag), fn() => $m->contar(), 7);
        $fila = array_values(array_filter($m->findAllWithRoles(), fn($u) => (int)$u["id"] === $uid))[0];
        $this->assertStringContainsString("Vendedor", $fila["roles"]);
        $this->assertStringContainsString("Consultor", $fila["roles"]);
    }

    public function testSinFilasHayUnaPaginaVacia(): void {
        $pm = new \ParteModel();
        $this->assertSame(0, $pm->contarAdmin("no existe esta pieza"));
        $pag = \Paginador::crear("7", 0, 20);
        $this->assertSame([], $pm->findAllWithDetails("no existe esta pieza", "all", "recientes", 0, $pag));
        $this->assertSame(1, $pag->pagina);
    }

    public function testTodosLosOrdenesTerminanEnUnDesempateUnico(): void {
        // Defensa extra: si alguien quita el desempate de una consulta, se ve en el SQL aunque los datos no lo delaten
        $fuente = file_get_contents(BASE_PATH . "/app/Models/ParteModel.php");
        preg_match_all('/ORDER BY (?:p\.|\()[^"]*"/', $fuente, $m);
        $this->assertGreaterThanOrEqual(12, count($m[0]), "se esperan los 7 órdenes del panel y los 5 del catálogo");
        foreach ($m[0] as $orden) $this->assertStringContainsString("p.id DESC", $orden, $orden);
        foreach (["VentaModel" => "v.id DESC", "PedidoModel" => "p.id DESC", "ComentarioModel" => "c.id DESC", "UserModel" => "u.id DESC"] as $clase => $desempate) {
            $this->assertStringContainsString($desempate, file_get_contents(BASE_PATH . "/app/Models/$clase.php"), $clase);
        }
    }
}
