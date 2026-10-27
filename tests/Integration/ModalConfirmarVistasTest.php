<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/**
 * S5.2: el modal de confirmación propio reemplaza a todos los confirm(). Las acciones siguen siendo POST con CsrfToken:
 * el modal solo pregunta antes de enviar. La lógica del script la prueba ModalConfirmarJsTest (Node); el <dialog> real,
 * el navegador integrado.
 */
final class ModalConfirmarVistasTest extends BaseDatos {
    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
    }

    private function datos(\DOMElement $form): array {
        return json_decode($form->getAttribute("data-confirmar"), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testNingunaVistaNiScriptUsaConfirmNiOnsubmit(): void {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(BASE_PATH . "/app/Views", \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->getExtension() !== "php") continue;
            $src = (string)file_get_contents($f->getPathname());
            $this->assertDoesNotMatchRegularExpression('/\bconfirm\s*\(/', $src, $f->getFilename() . ": sin confirm()");
            $this->assertDoesNotMatchRegularExpression('/onsubmit\s*=/i', $src, $f->getFilename() . ": sin onsubmit en línea");
        }
        foreach (glob(BASE_PATH . "/assets/js/*.js") as $js) {
            $src = (string)file_get_contents($js);
            if (basename($js) === "confirmar.js") {
                $this->assertSame(1, substr_count($src, "window.confirm("), "solo el aviso de reserva para un navegador sin <dialog>");
                continue;
            }
            $this->assertDoesNotMatchRegularExpression('/(?<![.\w])confirm\s*\(/', $src, basename($js));
            $this->assertStringNotContainsString("data-confirm\"", $src, basename($js));
        }
    }

    public function testCadaPantallaDelPanelTieneElDialogoYCargaElScriptDespuesDeBootstrap(): void {
        foreach (Pantallas::delPanel() as $n) {
            $h = Pantallas::render($this->db, $n);
            $x = Html::xpath($h);
            $d = $x->query("//dialog[@id='modalConfirmar']");
            $this->assertSame(1, $d->length, $n);
            $this->assertSame("alertdialog", $d->item(0)->getAttribute("role"), $n);
            $this->assertSame("mcTitulo", $d->item(0)->getAttribute("aria-labelledby"));
            $this->assertSame("mcTexto", $d->item(0)->getAttribute("aria-describedby"));
            $this->assertSame(1, Html::contar($x, "//dialog//h2[@id='mcTitulo']"), "$n: título del diálogo");
            $this->assertSame(1, Html::contar($x, "//dialog//p[@id='mcTexto']"), "$n: texto del diálogo");
            $this->assertSame(2, Html::contar($x, "//dialog//button[@type='button']"), "$n: dos botones, ninguno envía por sí mismo");
            $this->assertSame(0, Html::contar($x, "//dialog//form"), "$n: el diálogo no es un formulario; reenvía el de la fila");
            $this->assertMatchesRegularExpression('~bootstrap\.bundle\.min\.js.*js/confirmar\.js\?v=[^"]+"~s', $h, $n);
        }
    }

    public function testPartesPideConfirmacionAlDesactivarYSigueSiendoPostConToken(): void {
        $x = Html::xpath(Pantallas::render($this->db, "admin_partes"));
        $filas = $x->query("//tr[.//form[contains(@action,'action=eliminar')]]");
        $this->assertGreaterThan(0, $filas->length);
        foreach ($filas as $tr) {
            $q = new \DOMXPath($tr->ownerDocument);
            $f = $q->query(".//form[contains(@action,'action=eliminar')]", $tr)->item(0);
            $this->assertSame("POST", strtoupper($f->getAttribute("method")));
            $this->assertSame(1, $q->query(".//input[@name='csrf_token']", $f)->length, "con token");
            $d = $this->datos($f);
            $nombre = trim($q->query("./td[3]", $tr)->item(0)->textContent);
            $this->assertSame("Desactivar «{$nombre}»", $d["titulo"], "el título lleva el nombre de la pieza de esa fila");
            $this->assertSame(["Desactivar", "Conservar", true], [$d["boton"], $d["cancelar"], $d["peligro"]]);
        }
    }

    public function testUsuariosPideConfirmacionSoloAlDesactivarNoAlActivar(): void {
        $x = Html::xpath(Pantallas::render($this->db, "admin_usuarios"));
        $vistos = ["Desactivar" => 0, "Activar" => 0];
        foreach ($x->query("//form[contains(@action,'action=toggle')]") as $f) {
            $verbo = $x->query(".//button", $f)->item(0)->getAttribute("title");
            $vistos[$verbo]++;
            if ($verbo === "Desactivar") {
                $d = $this->datos($f);
                $this->assertStringStartsWith("Desactivar a «", $d["titulo"]);
                $this->assertStringContainsString("iniciar sesión", $d["texto"]);
                $this->assertTrue($d["peligro"]);
            } else {
                $this->assertFalse($f->hasAttribute("data-confirmar"), "activar es reversible: no pregunta");
            }
            $this->assertSame(1, $x->query(".//input[@name='csrf_token']", $f)->length);
        }
        $this->assertGreaterThan(0, $vistos["Desactivar"]);
    }

    public function testElPedidoPreguntaAlEntregarYAlCancelarYNoAlProcesar(): void {
        $x = Html::xpath(Pantallas::render($this->db, "admin_pedido_detalle"));
        $f = $x->query("//form[contains(@action,'action=cambiarEstado')]");
        $this->assertSame(1, $f->length);
        $f = $f->item(0);
        $this->assertSame("POST", strtoupper($f->getAttribute("method")));
        $this->assertSame(1, $x->query(".//input[@name='csrf_token']", $f)->length);
        $this->assertSame("estado", $f->getAttribute("data-confirmar-campo"));
        $this->assertSame(1, $x->query(".//select[@name='estado']", $f)->length, "el campo que decide existe");
        $m = $this->datos($f);
        $this->assertEqualsCanonicalizing(["entregado", "cancelado"], array_keys($m));
        $this->assertStringContainsString("definitivo", $m["entregado"]["texto"]);
        $this->assertArrayNotHasKey("peligro", $m["entregado"], "entregar no destruye: botón principal");
        $this->assertStringContainsString("stock", $m["cancelado"]["texto"]);
        $this->assertTrue($m["cancelado"]["peligro"]);
        $this->assertStringContainsString("#", $m["cancelado"]["titulo"]);
    }

    public function testLasListasSinFilasEnLosDatosDePruebaTienenSuModalEnElCodigo(): void {
        $esperado = ["autos/index.php" => "Desactivar «", "secciones/index.php" => "Desactivar «", "comentarios/index.php" => "Eliminar el comentario de «"];
        foreach ($esperado as $vista => $inicio) {
            $src = (string)file_get_contents(BASE_PATH . "/app/Views/admin/$vista");
            $this->assertStringContainsString('Confirmar::attrs(["titulo"=>"' . $inicio, $src, $vista);
            $this->assertStringContainsString('"peligro"=>true', $src, $vista);
        }
        $this->assertStringContainsString("para siempre", (string)file_get_contents(BASE_PATH . "/app/Views/admin/comentarios/index.php"), "eliminar un comentario es definitivo y lo dice");
    }

    // ── Hoja de estilos del componente ──

    private static function css(): string { return (string)file_get_contents(BASE_PATH . "/assets/css/panel.css"); }

    public function testElModalSigueAModalConfirmDeDesignMd(): void {
        $c = self::css();
        preg_match('/\.modal-confirmar\s*\{([^}]*)\}/', $c, $m);
        $r = $m[1] ?? "";
        $this->assertMatchesRegularExpression('/background:\s*var\(--surface-elevated\)/', $r);
        $this->assertMatchesRegularExpression('/border-radius:\s*var\(--rounded-xl\)/', $r);
        $this->assertMatchesRegularExpression('/padding:\s*var\(--spacing-lg\)/', $r, "24 px");
        $this->assertMatchesRegularExpression('/font-size:\s*var\(--type-body-md-size\)/', $r);
        $this->assertMatchesRegularExpression('/min\(440px, calc\(100vw - 2 \* var\(--spacing-md\)\)\)/', $r, "cabe en 390 px con 16 px a cada lado");
        $this->assertMatchesRegularExpression('/\.modal-confirmar::backdrop\s*\{[^}]*var\(--canvas\)/', $c);
        $this->assertMatchesRegularExpression('/\.modal-confirmar-acciones \.btn\s*\{\s*min-height:\s*44px/', $c, "44 px en móvil");
        $this->assertMatchesRegularExpression('/@media \(min-width: 576px\)\s*\{[^@]*\.modal-confirmar-acciones \.btn\s*\{\s*min-height:\s*40px/', $c);
    }

    public function testElBotonDePeligroYElDeCancelarUsanLosBotonesDelSistema(): void {
        $f = (string)file_get_contents(BASE_PATH . "/app/Views/layouts/admin_footer.php");
        $this->assertStringContainsString('class="btn btn-outline-secondary" data-mc-cancelar', $f);
        $this->assertStringContainsString('class="btn btn-danger" data-mc-confirmar', $f);
        $this->assertStringContainsString('"btn-danger"', (string)file_get_contents(BASE_PATH . "/assets/js/confirmar.js"));
    }
}
