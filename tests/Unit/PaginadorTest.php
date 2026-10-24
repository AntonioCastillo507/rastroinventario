<?php
namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Paginador: límites de página, valores raros de ?page= y enlaces con filtros */
final class PaginadorTest extends TestCase {
    public static function totales(): array {
        return [
            "sin filas"         => [0, 1],
            "una fila"          => [1, 1],
            "página justa"      => [12, 1],
            "una más"           => [13, 2],
            "dos justas"        => [24, 2],
            "dos y una"         => [25, 3],
            "49 piezas"         => [49, 5],
        ];
    }

    #[DataProvider("totales")]
    public function testCuentaLasPaginas(int $total, int $paginas): void {
        $p = \Paginador::crear(1, $total, 12);
        $this->assertSame($paginas, $p->paginas);
        $this->assertSame($total, $p->total);
        $this->assertSame($paginas > 1, $p->hayVarias());
    }

    public static function pedidas(): array {
        // [valor de ?page=, total, tamaño, página esperada]
        return [
            "ausente"                 => [null, 49, 12, 1],
            "vacío"                   => ["", 49, 12, 1],
            "uno"                     => ["1", 49, 12, 1],
            "tres"                    => ["3", 49, 12, 3],
            "última justa"            => ["5", 49, 12, 5],
            "una pasada"              => ["6", 49, 12, 5],
            "enorme"                  => ["9999", 49, 12, 5],
            "25 dígitos"              => [str_repeat("9", 25), 49, 12, 5],
            "16 dígitos"              => ["1000000000000000", 49, 12, 5],
            "cero"                    => ["0", 49, 12, 1],
            "ceros a la izquierda"    => ["007", 100, 12, 7],
            "solo ceros"              => ["000", 49, 12, 1],
            "negativo"                => ["-1", 49, 12, 1],
            "decimal"                 => ["3.5", 49, 12, 1],
            "científica"              => ["1e3", 49, 12, 1],
            "letras"                  => ["abc", 49, 12, 1],
            "número y letras"         => ["2abc", 49, 12, 1],
            "más"                     => ["+2", 49, 12, 1],
            "espacios"                => [" 2", 49, 12, 1],
            "dígitos no ASCII"        => ["٣", 49, 12, 1],
            "inyección"               => ["1; DROP TABLE partes", 49, 12, 1],
            "arreglo"                 => [["2"], 49, 12, 1],
            "decimal como número"     => [2.5, 49, 12, 1],
            "entero negativo"         => [-3, 49, 12, 1],
            "entero válido"           => [2, 49, 12, 2],
            "booleano"                => [true, 49, 12, 1],
            "con total 0"             => ["5", 0, 12, 1],
            "con total negativo"      => ["5", -10, 12, 1],
        ];
    }

    #[DataProvider("pedidas")]
    public function testLaPaginaPedidaSiempreQuedaEnRango(mixed $pedida, int $total, int $tam, int $esperada): void {
        $p = \Paginador::crear($pedida, $total, $tam);
        $this->assertSame($esperada, $p->pagina);
        $this->assertGreaterThanOrEqual(1, $p->pagina);
        $this->assertLessThanOrEqual($p->paginas, $p->pagina);
        $this->assertGreaterThanOrEqual(0, $p->offset());
    }

    public function testOffsetYSql(): void {
        $this->assertSame(" LIMIT 12 OFFSET 0", \Paginador::crear("1", 49, 12)->sql());
        $this->assertSame(" LIMIT 12 OFFSET 24", \Paginador::crear("3", 49, 12)->sql());
        $this->assertSame(" LIMIT 12 OFFSET 48", \Paginador::crear("5", 49, 12)->sql());
        $this->assertSame(" LIMIT 20 OFFSET 0", \Paginador::crear("2abc", 0, 20)->sql());
        $this->assertSame(" LIMIT 20 OFFSET 40", \Paginador::crear("999", 49, 20)->sql(), "página enorme = última");
    }

    public function testElSqlSoloLlevaEnterosAunqueLaEntradaSeaHostil(): void {
        foreach (["1; DROP TABLE x", "-1 UNION SELECT 1", "' OR 1=1 --", "1e9"] as $malo) {
            $this->assertMatchesRegularExpression('/^ LIMIT \d+ OFFSET \d+$/', \Paginador::crear($malo, 49, 12)->sql(), $malo);
        }
    }

    public function testFilasMostradas(): void {
        $p = \Paginador::crear("2", 49, 12);
        $this->assertSame([13, 24], [$p->desdeFila(), $p->hastaFila()]);
        $u = \Paginador::crear("5", 49, 12);
        $this->assertSame([49, 49], [$u->desdeFila(), $u->hastaFila()], "última página con una sola fila");
        $v = \Paginador::crear("1", 0, 12);
        $this->assertSame([0, 0], [$v->desdeFila(), $v->hastaFila()], "sin filas");
        $w = \Paginador::crear("1", 7, 12);
        $this->assertSame([1, 7], [$w->desdeFila(), $w->hastaFila()]);
    }

    public function testElTamanoDePaginaTieneQueSerPositivo(): void {
        $this->expectException(\InvalidArgumentException::class);
        \Paginador::crear("1", 10, 0);
    }

    public function testLosTamanosDeLaAplicacion(): void {
        $this->assertSame(12, \Paginador::CATALOGO);
        $this->assertSame(20, \Paginador::PANEL);
    }

    // ── ventana de páginas ──────────────────────────────────────────

    public static function ventanas(): array {
        return [
            "una página"                 => ["1", 10, 10, [1]],
            "pocas, todas"               => ["2", 49, 12, [1, 2, 3, 4, 5]],
            "en el medio con elipsis"    => ["6", 400, 20, [1, null, 4, 5, 6, 7, 8, null, 20]],
            "al principio"               => ["1", 400, 20, [1, 2, 3, null, 20]],
            "al final"                   => ["20", 400, 20, [1, null, 18, 19, 20]],
            "un solo hueco se rellena"   => ["3", 140, 20, [1, 2, 3, 4, 5, 6, 7]],
            "nueve sin elipsis"          => ["5", 180, 20, [1, 2, 3, 4, 5, 6, 7, 8, 9]],
        ];
    }

    #[DataProvider("ventanas")]
    public function testVentana(string $pedida, int $total, int $tam, array $esperada): void {
        $this->assertSame($esperada, \Paginador::crear($pedida, $total, $tam)->ventana());
    }

    // ── enlaces ─────────────────────────────────────────────────────

    public function testLaPrimeraPaginaNoLlevaPageYSiConservaLosFiltros(): void {
        $p = \Paginador::crear("3", 49, 12);
        $this->assertSame("/c/?ver=catalogo&q=freno", $p->url(1, ["ver" => "catalogo", "q" => "freno"], "/c/"));
        $this->assertSame("/c/?ver=catalogo&q=freno&page=2", $p->url(2, ["ver" => "catalogo", "q" => "freno"], "/c/"));
    }

    public function testCodificaLosFiltros(): void {
        $p = \Paginador::crear("1", 49, 12);
        $u = $p->url(2, ["q" => 'a&b "x" <script>', "marca" => "Ñandú", "sec" => "ñ y &"]);
        $this->assertSame("?q=a%26b%20%22x%22%20%3Cscript%3E&marca=%C3%91and%C3%BA&sec=%C3%B1%20y%20%26&page=2", $u);
        $this->assertStringNotContainsString("<", $u);
        $this->assertStringNotContainsString('"', $u);
    }

    public function testOmiteLosValoresVaciosYLosArreglos(): void {
        $p = \Paginador::crear("1", 49, 12);
        $this->assertSame("?mod=partes&page=2", $p->url(2, ["mod" => "partes", "q" => "", "x" => null, "y" => false, "z" => ["a"], "w" => new \stdClass()]));
        $this->assertSame("/admin/", $p->url(1, ["q" => "", "x" => null], "/admin/"), "sin filtros ni página queda solo la ruta");
    }

    public function testElCeroSiEsUnValor(): void {
        $this->assertSame("?seccion=0&page=2", \Paginador::crear("1", 49, 12)->url(2, ["seccion" => 0]));
    }

    public function testUnPageEnLosFiltrosSeIgnora(): void {
        $p = \Paginador::crear("1", 49, 12);
        $this->assertSame("?q=a&page=3", $p->url(3, ["page" => "99", "q" => "a"]));
        $this->assertSame("?q=a", $p->url(1, ["page" => "99", "q" => "a"]));
    }

    public function testElEnlaceNuncaSaleDelRango(): void {
        $p = \Paginador::crear("1", 49, 12);
        $this->assertSame("?page=5", $p->url(99, []));
        $this->assertSame("", $p->url(-4, []));
    }
}
