<?php
namespace Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BaseDatos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/** S6c.4: «Nueva parte» / «Editar parte» como página de producto: tarjetas en dos columnas y barra de acciones fija. */
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
        $this->assertSame(["Información", "Fotos", "Compatibilidad"], $titulos("producto-principal"));
        $this->assertSame(["Precio e inventario", "Organización"], $titulos("producto-lateral"));
        $this->assertSame(1, Html::contar($x, "//section[@aria-labelledby='pf-info']//input[@name='nombre']"));
        $this->assertSame(1, Html::contar($x, "//section[@aria-labelledby='pf-info']//textarea[@name='descripcion']"));
        $this->assertSame(1, Html::contar($x, "//section[@aria-labelledby='pf-fotos']//input[@name='imagen'][@type='file']"));
        $this->assertSame(1, Html::contar($x, "//section[@aria-labelledby='pf-fotos']//*[@data-foto-previa]"), "vista previa dentro de Fotos");
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
        foreach (["nombre" => "required", "precio" => "required", "cantidad" => "required"] as $campo => $atr) {
            $this->assertTrue($x->query("//input[@name='$campo']")->item(0)->hasAttribute($atr), "$campo sigue siendo obligatorio");
        }
        $this->assertSame("150", $x->query("//input[@name='nombre']")->item(0)->getAttribute("maxlength"));
        $this->assertSame("0", $x->query("//input[@name='precio']")->item(0)->getAttribute("min"));
    }

    #[DataProvider("pantallas")]
    public function testLaBarraDeAccionesFijaTraeGuardarYCancelar(string $pantalla): void {
        $x = Html::xpath(Pantallas::render($this->db, $pantalla));
        $this->assertSame(1, Html::contar($x, "//form/div[contains(@class,'form-acciones-fija')]/button[@type='submit']"));
        $this->assertSame(1, Html::contar($x, "//form/div[contains(@class,'form-acciones-fija')]/a[normalize-space(.)='Cancelar']"));
    }

    public function testLaCuadriculaEsDeDosColumnasDesde992PxYTopeDe1100(): void {
        $css = (string)file_get_contents(BASE_PATH . "/assets/css/panel.css");
        $this->assertMatchesRegularExpression('/\.form-panel-producto\s*\{\s*max-width:\s*1100px/', $css);
        $this->assertMatchesRegularExpression('/\.producto-grid\s*\{[^}]*grid-template-columns:\s*minmax\(0, 1fr\);/', $css, "apiladas por defecto (móvil)");
        $this->assertMatchesRegularExpression('/@media \(min-width: 992px\)\s*\{\s*\.producto-grid\s*\{[^}]*grid-template-columns:\s*minmax\(0, 1fr\) 340px/', $css);
        $this->assertMatchesRegularExpression('/\.form-acciones-fija\s*\{[^}]*position:\s*sticky[^}]*bottom:\s*0/', $css);
        $this->assertMatchesRegularExpression('/\.form-acciones-fija \.btn\s*\{\s*min-height:\s*44px/', $css);
    }
}
