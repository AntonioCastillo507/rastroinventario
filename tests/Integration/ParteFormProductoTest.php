<?php
namespace Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BaseDatos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/** Nueva / Editar parte como página de producto: tarjetas en dos columnas, encabezado con acciones fijas y foto con su vista previa. */
final class ParteFormProductoTest extends BaseDatos {
    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
    }

    public static function pantallas(): array { return ["crear" => ["admin_partes_crear"], "editar" => ["admin_partes_editar"]]; }

    #[DataProvider("pantallas")]
    public function testLasTarjetasEstanEnSuColumna(string $pantalla): void {
        $x = Html::xpath(Pantallas::render($this->db, $pantalla));
        $titulos = fn(string $col) => array_map(fn($n) => trim($n->textContent), iterator_to_array($x->query("//div[contains(@class,'$col')]/section[contains(@class,'form-card')]/h2[contains(@class,'form-subtitulo')]")));
        $this->assertSame(["Información", "Fotos"], $titulos("producto-principal"));
        $this->assertSame(["Precio e inventario", "Organización", "Compatibilidad"], $titulos("producto-lateral"));
        $this->assertSame(1, Html::contar($x, "//section[@aria-labelledby='pf-info']//input[@name='nombre']"));
        $this->assertSame(1, Html::contar($x, "//section[@aria-labelledby='pf-info']//textarea[@name='descripcion']"));
        $this->assertSame(1, Html::contar($x, "//section[@aria-labelledby='pf-fotos']//input[@name='imagen'][@type='file']"));
        $this->assertSame(1, Html::contar($x, "//section[@aria-labelledby='pf-compat']//select[@name='auto_id']"));
        $this->assertSame(1, Html::contar($x, "//section[@aria-labelledby='pf-precio']//input[@name='precio']"));
        $this->assertSame(1, Html::contar($x, "//section[@aria-labelledby='pf-precio']//input[@name='cantidad']"));
        $this->assertSame(1, Html::contar($x, "//section[@aria-labelledby='pf-org']//select[@name='seccion_id']"));
    }

    #[DataProvider("pantallas")]
    public function testLosNombresDeCampoYLaValidacionSiguenIgual(string $pantalla): void {
        $x = Html::xpath(Pantallas::render($this->db, $pantalla));
        $nombres = [];
        foreach ($x->query("//main//form//*[self::input or self::select or self::textarea][@name]") as $n) $nombres[] = $n->getAttribute("name");
        sort($nombres);
        $this->assertSame(["auto_id", "cantidad", "csrf_token", "descripcion", "imagen", "nombre", "precio", "seccion_id"], $nombres);
        foreach (["nombre", "precio", "cantidad"] as $campo) $this->assertTrue($x->query("//input[@name='$campo']")->item(0)->hasAttribute("required"), "$campo sigue siendo obligatorio");
        $this->assertSame("150", $x->query("//input[@name='nombre']")->item(0)->getAttribute("maxlength"));
        $this->assertSame("0", $x->query("//input[@name='precio']")->item(0)->getAttribute("min"));
    }

    #[DataProvider("pantallas")]
    public function testElEncabezadoTraeVolverTituloYAccionesYNoHayTarjetaFinal(string $pantalla): void {
        $x = Html::xpath(Pantallas::render($this->db, $pantalla));
        $edit = $pantalla === "admin_partes_editar";
        $this->assertSame($edit ? "Editar parte" : "Agregar parte al inventario", trim($x->query("//div[contains(@class,'pagina-cabeza')]//h1")->item(0)->textContent));
        $this->assertSame(1, Html::contar($x, "//div[contains(@class,'pagina-cabeza-titulo')]/a[contains(@class,'enlace-volver')][normalize-space(.)='Volver a partes']/following-sibling::h1"), "volver encima del título, mismo borde");
        $this->assertSame(1, Html::contar($x, "//div[contains(@class,'pagina-acciones')]/a[normalize-space(.)='Cancelar']"));
        $g = $x->query("//div[contains(@class,'pagina-acciones')]/button[@type='submit'][@form='form-parte']");
        $this->assertSame(1, $g->length);
        $this->assertSame($edit ? "Guardar cambios" : "Guardar parte", trim($g->item(0)->textContent));
        $this->assertSame(1, Html::contar($x, "//form[@id='form-parte']"));
        $this->assertSame(0, Html::contar($x, "//form[@id='form-parte']//button[@type='submit']"), "el único botón de guardar está fuera del form");
        $this->assertSame(0, Html::contar($x, "//*[contains(@class,'form-acciones')]"), "sin tarjeta de acciones al final");
    }

    #[DataProvider("pantallas")]
    public function testLaFotoLlevaCampoYVistaPreviaJuntos(string $pantalla): void {
        $x = Html::xpath(Pantallas::render($this->db, $pantalla));
        $this->assertSame(1, Html::contar($x, "//div[contains(@class,'foto-campo')][@data-foto-previa]/div[contains(@class,'foto-campo-datos')]/input[@type='file'][@name='imagen']"));
        $this->assertSame(1, Html::contar($x, "//div[@data-foto-previa]/div[contains(@class,'foto-campo-previa')]//*[@data-foto-titulo]"));
    }

    public function testElCssUsaElContenedorDelPanelYUnLateralDe380(): void {
        $css = (string)file_get_contents(BASE_PATH . "/assets/css/panel.css");
        $this->assertDoesNotMatchRegularExpression('/\.form-panel-producto\s*\{[^}]*max-width/', $css, "mismo contenedor que el encabezado, sin franjas");
        $this->assertMatchesRegularExpression('/\.producto-grid\s*\{[^}]*grid-template-columns:\s*minmax\(0, 1fr\);/', $css, "apiladas en móvil");
        $this->assertMatchesRegularExpression('/@media \(min-width: 992px\)\s*\{\s*\.producto-grid\s*\{[^}]*minmax\(0, 1fr\) 380px/', $css);
        $this->assertMatchesRegularExpression('/\.pagina-cabeza\s*\{[^}]*position:\s*sticky[^}]*top:\s*var\(--panel-barra-alto\)/', $css);
        $this->assertMatchesRegularExpression('/\.pagina-acciones\s*\{\s*position:\s*fixed[^}]*bottom:\s*0/', $css, "móvil: barra fija abajo");
        $this->assertMatchesRegularExpression('/\.pagina-acciones \.btn\s*\{\s*flex:\s*1;\s*min-height:\s*44px/', $css);
        $this->assertMatchesRegularExpression('/input\[type="file"\]::file-selector-button\s*\{[^}]*height:\s*38px[^}]*margin:\s*0 var\(--spacing-sm\) 0 0[^}]*border-radius:\s*0[^}]*background:\s*var\(--surface-elevated\)/', $css, "a ras del recuadro, con tokens de tema");
    }
}
