<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Fragmento app/Views/layouts/paginacion.php */
final class PaginacionVistaTest extends TestCase {
    private function render(\Paginador $pag, array $params = [], string $base = "/x/"): string {
        $pagParams = $params;
        $pagBase   = $base;
        ob_start();
        include BASE_PATH . "/app/Views/layouts/paginacion.php";
        return (string)ob_get_clean();
    }

    public function testConUnaSolaPaginaNoPintaNada(): void {
        $this->assertSame("", trim($this->render(\Paginador::crear("1", 12, 12))));
        $this->assertSame("", trim($this->render(\Paginador::crear("1", 0, 12))));
    }

    public function testPrimeraPaginaTieneAnteriorDeshabilitado(): void {
        $h = $this->render(\Paginador::crear("1", 49, 12));
        $this->assertStringContainsString("Mostrando 1–12 de 49", $h);
        $this->assertMatchesRegularExpression('#<li class="page-item disabled"><span class="page-link">&laquo; Anterior</span></li>#', $h);
        $this->assertStringNotContainsString('rel="prev"', $h);
        $this->assertStringContainsString('rel="next"', $h);
        $this->assertStringContainsString('href="/x/?page=2"', $h);
        $this->assertSame(1, substr_count($h, 'aria-current="page"'));
    }

    public function testUltimaPaginaTieneSiguienteDeshabilitado(): void {
        $h = $this->render(\Paginador::crear("5", 49, 12));
        $this->assertStringContainsString("Mostrando 49–49 de 49", $h);
        $this->assertMatchesRegularExpression('#<li class="page-item disabled"><span class="page-link">Siguiente &raquo;</span></li>#', $h);
        $this->assertStringNotContainsString('rel="next"', $h);
        $this->assertStringContainsString('rel="prev"', $h);
        $this->assertStringContainsString('href="/x/?page=4"', $h, "anterior de la última");
    }

    public function testPaginaDelMedioMarcaLaActualYPonePuntosSuspensivos(): void {
        $h = $this->render(\Paginador::crear("6", 400, 20));
        $this->assertMatchesRegularExpression('#<li class="page-item active" aria-current="page"><span class="page-link">6</span></li>#', $h);
        $this->assertStringContainsString("…", $h);
        $this->assertStringContainsString("Mostrando 101–120 de 400", $h);
        $this->assertStringContainsString('href="/x/?page=20"', $h);
    }

    public function testLosFiltrosSeConservanYSeEscapan(): void {
        $h = $this->render(\Paginador::crear("2", 49, 12), ["ver" => "catalogo", "q" => '"><script>alert(1)</script>', "marca" => "Ñandú & Cía"], "/rastroinventario/");
        $this->assertStringNotContainsString("<script>", $h);
        $this->assertStringContainsString('href="/rastroinventario/?ver=catalogo&amp;q=%22%3E%3Cscript%3Ealert%281%29%3C%2Fscript%3E&amp;marca=%C3%91and%C3%BA%20%26%20C%C3%ADa&amp;page=3"', $h);
        $this->assertStringContainsString('href="/rastroinventario/?ver=catalogo&amp;q=', $h, "la página 1 vuelve sin page");
    }

    public function testSinVariablesNoFalla(): void {
        ob_start();
        include BASE_PATH . "/app/Views/layouts/paginacion.php";
        $this->assertSame("", trim((string)ob_get_clean()));
    }
}
