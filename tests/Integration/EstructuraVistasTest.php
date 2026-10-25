<?php
namespace Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BaseDatos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/**
 * Estructura y accesibilidad de TODAS las pantallas (tienda, acceso y panel), con su HTML real:
 * un <main>, enlace de salto, un solo <h1> y encabezados sin saltos, campos con nombre, etiquetas
 * asociadas, botones solo-icono con nombre, iconos decorativos ocultos, avisos con role y autocomplete.
 */
final class EstructuraVistasTest extends BaseDatos {
    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
    }

    /** @return array<string,array{0:string}> */
    public static function pantallas(): array {
        $o = [];
        foreach (Pantallas::nombres() as $n) $o[$n] = [$n];
        return $o;
    }

    /** Nombre accesible de un elemento: aria-label, aria-labelledby, o su texto sin lo oculto a lectores */
    private static function nombre(\DOMXPath $x, \DOMElement $e): string {
        $l = trim($e->getAttribute("aria-label"));
        if ($l !== "") return $l;
        $lb = trim($e->getAttribute("aria-labelledby"));
        if ($lb !== "") {
            $t = "";
            foreach (preg_split('/\s+/', $lb) as $id) {
                $r = $x->query("//*[@id='" . $id . "']");
                if ($r->length) $t .= " " . self::texto($r->item(0));
            }
            if (trim($t) !== "") return trim($t);
        }
        return trim(self::texto($e));
    }

    private static function texto(\DOMNode $n): string {
        $t = "";
        foreach ($n->childNodes as $c) {
            if ($c instanceof \DOMText) $t .= $c->textContent;
            elseif ($c instanceof \DOMElement) {
                if ($c->getAttribute("aria-hidden") === "true") continue;
                if ($c->tagName === "img") { $t .= " " . $c->getAttribute("alt"); continue; }
                $t .= " " . self::texto($c);
            }
        }
        return preg_replace('/\s+/', " ", $t);
    }

    private static function oculto(\DOMElement $e): bool {
        for ($n = $e; $n instanceof \DOMElement; $n = $n->parentNode) {
            if ($n->getAttribute("aria-hidden") === "true") return true;
        }
        return false;
    }

    private function html(string $nombre): \DOMXPath {
        return Html::xpath(Pantallas::render($this->db, $nombre));
    }

    #[DataProvider("pantallas")]
    public function testUnMainYUnEnlaceDeSalto(string $n): void {
        $x = $this->html($n);
        $this->assertSame(1, Html::contar($x, "//main"), "$n: debe haber un solo <main>");
        $this->assertSame(1, Html::contar($x, "//main[@id='contenido']"), "$n: el <main> es el destino del enlace de salto");
        $primero = $x->query("//body//a[1]")->item(0);
        $this->assertNotNull($primero, $n);
        $this->assertSame("#contenido", $primero->getAttribute("href"), "$n: el primer enlace es el de salto al contenido");
        $this->assertStringContainsString("skip-link", $primero->getAttribute("class"), $n);
        $this->assertSame("Saltar al contenido", trim($primero->textContent), $n);
        $this->assertSame(0, Html::contar($x, "//*[contains(@class,'btn-float-back')]"), "$n: ya no hay botón flotante");
    }

    #[DataProvider("pantallas")]
    public function testUnSoloH1YEncabezadosSinSaltos(string $n): void {
        $x = $this->html($n);
        $this->assertSame(1, Html::contar($x, "//h1"), "$n: debe haber un solo <h1>");
        $this->assertNotSame("", trim($x->query("//h1")->item(0)->textContent), "$n: el h1 no puede estar vacío");
        $niveles = [];
        foreach ($x->query("//h1|//h2|//h3|//h4|//h5|//h6") as $h) $niveles[] = (int)substr($h->nodeName, 1);
        $this->assertSame(1, $niveles[0], "$n: el primer encabezado es el h1 (" . implode(",", $niveles) . ")");
        for ($i = 1; $i < count($niveles); $i++) {
            $this->assertLessThanOrEqual($niveles[$i - 1] + 1, $niveles[$i], "$n: salto de h{$niveles[$i - 1]} a h{$niveles[$i]} (" . implode(",", $niveles) . ")");
        }
    }

    #[DataProvider("pantallas")]
    public function testCadaCampoTieneNombreYCadaEtiquetaSuCampo(string $n): void {
        $x = $this->html($n);
        $sinNombre = [];
        foreach ($x->query("//input[not(@type='hidden' or @type='submit' or @type='button' or @type='reset' or @type='image')]|//select|//textarea") as $c) {
            /** @var \DOMElement $c */
            if (self::oculto($c)) continue;
            $ok = self::nombre($x, $c) !== "" && ($c->hasAttribute("aria-label") || $c->hasAttribute("aria-labelledby"));
            if (!$ok && $c->getAttribute("id") !== "") {
                $ok = Html::contar($x, "//label[@for='" . $c->getAttribute("id") . "']") > 0;
            }
            if (!$ok) {
                for ($p = $c->parentNode; $p instanceof \DOMElement; $p = $p->parentNode) if ($p->tagName === "label") { $ok = true; break; }
            }
            if (!$ok) $sinNombre[] = $c->tagName . "[name=" . $c->getAttribute("name") . "]";
        }
        $this->assertSame([], $sinNombre, "$n: campos sin etiqueta ni aria-label: " . implode(", ", $sinNombre));

        $huerfanas = [];
        foreach ($x->query("//label") as $l) {
            /** @var \DOMElement $l */
            $for = $l->getAttribute("for");
            if ($for !== "") {
                if (Html::contar($x, "//*[@id='$for' and (self::input or self::select or self::textarea)]") === 0) $huerfanas[] = "for=$for";
            } elseif ($x->query(".//input|.//select|.//textarea", $l)->length === 0) {
                $huerfanas[] = trim($l->textContent);
            }
        }
        $this->assertSame([], $huerfanas, "$n: etiquetas sin campo: " . implode(", ", $huerfanas));

        $ids = [];
        foreach ($x->query("//*[@id]") as $e) $ids[] = $e->getAttribute("id");
        $repetidos = array_keys(array_filter(array_count_values($ids), fn($c) => $c > 1));
        $this->assertSame([], $repetidos, "$n: ids repetidos: " . implode(", ", $repetidos));
    }

    #[DataProvider("pantallas")]
    public function testBotonesYEnlacesTienenNombreEIconosDecorativosEstanOcultos(string $n): void {
        $x = $this->html($n);
        $sinNombre = [];
        foreach ($x->query("//a[@href]|//button|//*[@role='button']") as $e) {
            /** @var \DOMElement $e */
            if (self::oculto($e)) continue;
            if (self::nombre($x, $e) === "") $sinNombre[] = $e->tagName . "." . $e->getAttribute("class");
        }
        $this->assertSame([], $sinNombre, "$n: botones o enlaces sin nombre accesible: " . implode(" | ", $sinNombre));

        $iconos = [];
        foreach ($x->query("//i[contains(concat(' ', normalize-space(@class), ' '), ' bi ')]") as $i) {
            if ($i->getAttribute("aria-hidden") !== "true") $iconos[] = $i->getAttribute("class");
        }
        $this->assertSame([], $iconos, "$n: iconos sin aria-hidden: " . implode(" | ", array_slice($iconos, 0, 5)));

        $sinAlt = [];
        foreach ($x->query("//img[not(@alt)]") as $i) $sinAlt[] = $i->getAttribute("src");
        $this->assertSame([], $sinAlt, "$n: imágenes sin alt");

        $navs = [];
        foreach ($x->query("//nav") as $nav) $navs[] = $nav->getAttribute("aria-label");
        $this->assertNotContains("", $navs, "$n: cada <nav> lleva aria-label");
        $this->assertSame(count($navs), count(array_unique($navs)), "$n: los aria-label de los <nav> no se repiten");
    }

    #[DataProvider("pantallas")]
    public function testLosAvisosLlevanRoleYLosErroresSonAlert(string $n): void {
        $x = $this->html($n);
        $sinRole = [];
        foreach ($x->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' alert ') or contains(@class,'alert-dark-')]") as $a) {
            /** @var \DOMElement $a */
            $r = $a->getAttribute("role");
            $c = $a->getAttribute("class");
            $esError = (bool)preg_match('/alert-danger|alert-warning|alert-dark-danger/', $c);
            $esperado = $esError ? "alert" : "status";
            if ($r !== $esperado) $sinRole[] = "$c → role='$r' (esperado $esperado)";
        }
        $this->assertSame([], $sinRole, "$n: avisos sin role correcto");
        foreach ($x->query("//*[contains(@class,'btn-close')]") as $b) $this->assertNotSame("", $b->getAttribute("aria-label"), "$n: el cierre del aviso lleva nombre");
    }

    #[DataProvider("pantallas")]
    public function testAutocompleteEnNombreCorreoTelefonoYDireccion(string $n): void {
        $x = $this->html($n);
        $mal = [];
        foreach ($x->query("//input|//textarea") as $c) {
            /** @var \DOMElement $c */
            $nom = $c->getAttribute("name");
            $ac = $c->getAttribute("autocomplete");
            $tipo = null;
            if (preg_match('/(^|_)email$/', $nom)) $tipo = ["email", "username", "off"];
            elseif (preg_match('/(^|_)telefono$/', $nom)) $tipo = ["tel", "off"];
            elseif (preg_match('/(^|_)direccion(_entrega)?$/', $nom)) $tipo = ["street-address", "off"];
            elseif (preg_match('/^(cliente_)?nombre(_visitante)?$/', $nom)) {
                // el nombre de partes, secciones, autos y roles no es un dato personal
                if (str_starts_with($n, "admin_") && !str_starts_with($n, "admin_usuarios")) continue;
                $tipo = ["name", "off"];
            }
            if ($tipo === null) continue;
            if (str_starts_with($n, "admin_") && !in_array($ac, ["off"], true)) { $mal[] = "$nom autocomplete='$ac' (en el panel va off)"; continue; }
            if (!in_array($ac, $tipo, true)) $mal[] = "$nom autocomplete='$ac' (esperado " . implode("/", $tipo) . ")";
        }
        $this->assertSame([], $mal, "$n: " . implode("; ", $mal));
        foreach ($x->query("//input[@type='password']") as $p) {
            $this->assertContains($p->getAttribute("autocomplete"), ["current-password", "new-password"], "$n: contraseña sin autocomplete");
        }
    }

    public function testLaMatrizDePermisosTiene32CasillasConNombre(): void {
        $x = $this->html("admin_roles_editar");
        $cajas = $x->query("//input[@type='checkbox' and starts-with(@name,'permisos[')]");
        $this->assertSame(32, $cajas->length);
        $nombres = [];
        foreach ($cajas as $c) {
            $nombres[] = $c->getAttribute("aria-label");
            $this->assertMatchesRegularExpression('/^(Ver|Crear|Editar|Borrar) en .+/', $c->getAttribute("aria-label"));
        }
        $this->assertSame(32, count(array_unique($nombres)), "Cada casilla tiene un nombre distinto");
    }

    public function testElAvisoDeErrorNoSeCierraSoloYElDeExitoSi(): void {
        $js = (string)file_get_contents(BASE_PATH . "/assets/js/main.js");
        $this->assertStringContainsString('.alert-success,.alert-info', $js, "Solo éxito e información se cierran solos");
        $this->assertStringNotContainsString("alert-danger", $js);
        $this->assertStringNotContainsString("alert-warning", $js);
        $this->assertStringNotContainsString("btn-float-back", $js);
        $this->assertStringNotContainsString("history.back", $js);
        foreach (["style.css", "extra.css", "base.css"] as $css) $this->assertStringNotContainsString("btn-float-back", (string)file_get_contents(BASE_PATH . "/assets/css/$css"));
        $x = $this->html("con_aviso_error");
        $a = $x->query("//*[contains(@class,'alert-danger')]")->item(0);
        $this->assertNotNull($a);
        $this->assertSame("alert", $a->getAttribute("role"));
        $e = $this->html("con_aviso_exito")->query("//*[contains(@class,'alert-success')]")->item(0);
        $this->assertSame("status", $e->getAttribute("role"));
    }

    public function testLasEstrellasDeCalificacionSePuedenUsarConElTeclado(): void {
        $x = $this->html("detalle_cliente");
        $this->assertSame(5, Html::contar($x, "//div[contains(@class,'star-rate')]//input[@type='radio']"));
        foreach ($x->query("//div[contains(@class,'star-rate')]//label") as $l) {
            $this->assertMatchesRegularExpression('/^\s*\d estrellas?: /', trim($l->textContent), "Cada estrella tiene su texto");
        }
        $css = (string)file_get_contents(BASE_PATH . "/assets/css/extra.css");
        $this->assertDoesNotMatchRegularExpression('/\.star-rate input\s*\{\s*display:\s*none/', $css, "display:none saca los radios del teclado");
        $calificacion = $x->query("//*[@role='img' and starts-with(@aria-label,'Calificación')]");
        $this->assertGreaterThan(0, $calificacion->length, "Las estrellas mostradas llevan su texto");
    }
    #[DataProvider("pantallas")]
    public function testElPieDeLaTiendaTieneEnlacesConNombreYSoloIconosDecorativosConFuncion(string $n): void {
        if (str_starts_with($n, "admin_") || in_array($n, Pantallas::deAcceso(), true)) { $this->addToAssertionCount(1); return; }
        $x = Html::xpath(Pantallas::render($this->db, $n));
        $this->assertSame(1, $x->query("//footer")->length, $n);
        // los únicos iconos del pie: ubicación, teléfono, correo y horario (en su pastilla) y los de los botones de redes, todos aria-hidden
        foreach ($x->query("//footer//*[contains(@class,'bi-')]") as $i) {
            $this->assertSame("true", $i->getAttribute("aria-hidden"), "$n: icono del pie sin aria-hidden");
            $this->assertMatchesRegularExpression('/(^|\s)(pie-icono|pie-red)(\s|$)/', $i->parentNode->getAttribute("class"), "$n: icono del pie fuera de su pastilla o botón");
        }
        foreach ($x->query("//footer//a") as $a) $this->assertNotSame("", trim($a->textContent), "$n: enlace del pie sin texto");
        $this->assertSame(["Comprar", "Empresa", "Contacto y horario"], array_map(fn($e) => trim($e->textContent), iterator_to_array($x->query("//footer//h2"))), $n);
    }
}
