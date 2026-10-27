<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/**
 * S6b.2: Reportes, matriz de permisos de Roles y aviso de Ventas con los componentes del panel (S5).
 */
final class RematesPanelTest extends BaseDatos {
    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
    }

    public function testReportesUsaCabeceraYTarjetasDelPanel(): void {
        $x = Html::xpath(Pantallas::render($this->db, "admin_reportes"));
        $this->assertSame(1, Html::contar($x, "//div[contains(@class,'panel-cabeza')]//h1[contains(@class,'panel-titulo')]"));
        $this->assertSame(0, Html::contar($x, "//*[contains(@class,'page-header') or contains(@class,'dark-card') or contains(@class,'text-warning')]"));
        $this->assertSame(4, Html::contar($x, "//div[contains(@class,'reporte-grid')]/section[contains(@class,'lista-card')]"));
        $this->assertSame(4, Html::contar($x, "//section[contains(@class,'reporte-card')]/h2"));
        $this->assertSame(1, Html::contar($x, "//section//form[@method='GET']//select[@name='seccion']"), "el filtro por sección sigue");
        $this->assertSame(4, Html::contar($x, "//section[contains(@class,'reporte-card')]//*[(self::a or self::button)][contains(@class,'btn-gold')]"), "una descarga por tarjeta");
    }

    public function testLaMatrizDeRolesEsLegibleYTraeLaFilaSeguridad(): void {
        $x = Html::xpath(Pantallas::render($this->db, "admin_roles_editar"));
        $this->assertSame(1, Html::contar($x, "//div[contains(@class,'matriz-permisos')]/table[contains(@class,'matriz-tabla')]"));
        $this->assertSame(0, Html::contar($x, "//*[contains(@class,'dark-table')]"));
        $this->assertSame(5, Html::contar($x, "//table[contains(@class,'matriz-tabla')]/thead//th[@scope='col']"));
        $this->assertGreaterThanOrEqual(1, Html::contar($x, "//table[contains(@class,'matriz-tabla')]//th[@scope='row'][starts-with(normalize-space(.),'Seguridad')]"));
        $this->assertSame(1, Html::contar($x, "//input[@name='permisos[seguridad][ver]'][contains(@aria-label,'Seguridad')]"));
        $this->assertSame(
            Html::contar($x, "//table[contains(@class,'matriz-tabla')]/tbody/tr") * 4,
            Html::contar($x, "//table[contains(@class,'matriz-tabla')]//input[@type='checkbox'][@aria-label]"),
            "cada casilla con su nombre accesible"
        );
    }

    public function testElCssDeLasTarjetasYDeLaMatrizUsaTokens(): void {
        $css = (string)file_get_contents(BASE_PATH . "/assets/css/panel.css");
        $this->assertMatchesRegularExpression('/\.matriz-tabla tbody td \{[^}]*height: 52px/', $css, "zona de toque");
        $this->assertMatchesRegularExpression('/\.reporte-card form \.btn \{[^}]*min-height: 44px/', $css);
        $ini = (int)strpos($css, "Remates S6b.2");
        $bloque = substr($css, $ini, (int)strpos($css, "Detalle del pedido (S6b.1)") - $ini);
        $this->assertSame(0, preg_match('/#[0-9a-fA-F]{3,6}\b/', $bloque), "sin colores sueltos");
    }

    public function testElAvisoDeStockDeVentasEsUnAlertDeInformacion(): void {
        $src = (string)file_get_contents(BASE_PATH . "/app/Views/admin/ventas/form.php");
        $this->assertStringContainsString('id="stockAlert" role="status" class="alert alert-info', $src);
        $this->assertStringNotContainsString("alert-dark-info", $src);
    }
}
