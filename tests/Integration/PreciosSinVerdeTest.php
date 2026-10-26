<?php
namespace Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BaseDatos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/**
 * Ningún precio ni total sale en verde en ninguna pantalla (el verde es para éxito y stock disponible):
 * se recorre el HTML real de todas las pantallas y, para cada elemento que es solo una cantidad de dinero,
 * se mira su clase, su estilo en línea y los de sus ascendientes cercanos.
 */
final class PreciosSinVerdeTest extends BaseDatos {
    private const VERDES = ["text-success", "green", "chip-success", "badge-stock-ok", "text-bg-success", "bg-success"];

    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
    }

    /** @return array<string,array{0:string}> */
    public static function pantallas(): array {
        $out = [];
        foreach (Pantallas::nombres() as $n) $out[$n] = [$n];
        return $out;
    }

    /** @return list<\DOMElement> elementos cuyo contenido es solo una cantidad de dinero ($12.50) */
    private function precios(\DOMXPath $x): array {
        $out = [];
        foreach ($x->query("//body//*[not(self::script) and not(self::style) and not(*)]") as $el) {
            if (preg_match('/^\s*\$\s?\d[\d,]*\.\d{2}\s*$/u', $el->textContent)) $out[] = $el;
        }
        return $out;
    }

    private function esVerde(\DOMElement $el): bool {
        $n = $el;
        for ($i = 0; $i < 4 && $n instanceof \DOMElement; $i++, $n = $n->parentNode) {
            $clases = preg_split('/\s+/', trim($n->getAttribute("class"))) ?: [];
            if (array_intersect(self::VERDES, $clases)) return true;
            if (stripos($n->getAttribute("style"), "success") !== false) return true;
            if ($n->tagName === "body") break;
        }
        return false;
    }

    #[DataProvider("pantallas")]
    public function testNingunPrecioDeNingunaPantallaVaEnVerde(string $nombre): void {
        $x = Html::xpath(Pantallas::render($this->db, $nombre));
        $verdes = [];
        foreach ($this->precios($x) as $el) {
            if ($this->esVerde($el)) $verdes[] = trim($el->textContent) . " <" . $el->tagName . " class=\"" . $el->getAttribute("class") . "\">";
        }
        $this->assertSame([], $verdes, "$nombre: precios en verde");
    }

    public function testElDetectorEncuentraPreciosEnLasPantallasConDinero(): void {
        foreach (["catalogo" => 3, "carrito_invitado" => 3, "admin_partes" => 3, "admin_pedido_detalle" => 2, "compra_exitosa" => 2, "mis_pedidos" => 1] as $pantalla => $minimo) {
            $this->assertGreaterThanOrEqual($minimo, count($this->precios(Html::xpath(Pantallas::render($this->db, $pantalla)))), "$pantalla: debe haber precios que revisar");
        }
    }

    /** @return array<string,array{0:string,1:string}> pantalla → clase que debe llevar el precio principal */
    public static function conClasePrecio(): array {
        return [
            "tarjetas de la portada" => ["home", "precio"],
            "tarjetas del catálogo" => ["catalogo", "precio"],
            "detalle" => ["detalle_invitado", "detail-price"],
            "carrito (total)" => ["carrito_invitado", "text-precio"],
            "carrito del cliente (total)" => ["carrito_cliente", "text-precio"],
            "mis pedidos" => ["mis_pedidos", "text-precio"],
            "compra exitosa" => ["compra_exitosa", "text-precio"],
            "confirmar compra" => ["comprar_cliente", "text-precio"],
            "partes del panel" => ["admin_partes", "text-precio"],
            "pedidos del panel" => ["admin_pedidos", "text-precio"],
            "detalle de pedido" => ["admin_pedido_detalle", "text-precio"],
            "ventas del panel" => ["admin_ventas", "text-precio"],
        ];
    }

    #[DataProvider("conClasePrecio")]
    public function testElPrecioPrincipalUsaElTokenDePrecio(string $pantalla, string $clase): void {
        $x = Html::xpath(Pantallas::render($this->db, $pantalla));
        $n = $x->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $clase ')]")->length;
        $this->assertGreaterThan(0, $n, "$pantalla: ningún elemento con la clase $clase");
    }

    public function testLosTotalesDelPanelUsanElTokenDePrecio(): void {
        $x = Html::xpath(Pantallas::render($this->db, "admin_dashboard"));
        $this->assertGreaterThan(0, $x->query("//*[contains(@class,'stat-val') and contains(@class,'precio')]")->length, "Total vendido");
        $this->assertSame(0, $x->query("//*[contains(@class,'stat-val') and contains(@class,'green')]")->length);
    }

    public function testElVerdeSiSeQuedaDondeDebe(): void {
        // el estado "entregado", los avisos de éxito y la firma verificada siguen usando el verde de éxito
        $x = Html::xpath(Pantallas::render($this->db, "con_aviso_exito"));
        $this->assertGreaterThan(0, $x->query("//*[contains(@class,'alert-success')]")->length);
        $css = (string)file_get_contents(BASE_PATH . "/assets/css/style.css");
        $this->assertMatchesRegularExpression('/\.estado-entregado\s*\{\s*color:\s*var\(--success\)/', $css);
        $this->assertMatchesRegularExpression('/\.stock-ok\s*\{\s*color:\s*var\(--success-text\)/', (string)file_get_contents(BASE_PATH . "/assets/css/base.css"));
    }
}
