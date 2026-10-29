<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/**
 * S5.4: flecha propia en todos los select (regla global de base.css) y movimiento consistente en todo el sitio
 * (sección «Movimiento» de DESIGN.md): 150 a 250 ms, solo transform y opacity, nada con prefers-reduced-motion.
 */
final class SelectYMovimientoSitioTest extends BaseDatos {
    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar(); // los datos de apoyo viven en la transacción de cada prueba
    }

    private static function css(string $f = "base.css"): string { return (string)file_get_contents(BASE_PATH . "/assets/css/$f"); }

    private static function regla(string $selector, string $f = "base.css"): string {
        preg_match('/(?:^|[}\s{])' . preg_quote($selector, "/") . '\s*\{([^}]*)\}/m', self::css($f), $m);
        return $m[1] ?? "";
    }

    /** el bloque @media de nivel superior que empieza por $consulta, con su contenido */
    private static function media(string $consulta, string $f = "base.css"): string {
        $css = self::css($f);
        $ini = strpos($css, "@media " . $consulta);
        if ($ini === false) return "";
        $prof = 0;
        for ($i = strpos($css, "{", $ini), $n = strlen($css); $i < $n; $i++) {
            if ($css[$i] === "{") $prof++;
            elseif ($css[$i] === "}" && --$prof === 0) return substr($css, $ini, $i - $ini + 1);
        }
        return "";
    }

    // ── Flecha de los select ──

    public function testLaReglaGlobalDeSelectPoneFlechaPropiaConRellenoSuficiente(): void {
        $r = self::regla("select, .form-select");
        $this->assertNotSame("", $r, "regla global sobre select y .form-select");
        $this->assertMatchesRegularExpression('/appearance:\s*none/', $r, "se quita la flecha del navegador");
        $this->assertMatchesRegularExpression('/cursor:\s*pointer/', $r);
        $this->assertMatchesRegularExpression('/--flecha:\s*var\(--text-muted\)/', $r, "reposo: texto secundario");
        $this->assertSame(2, preg_match_all('/linear-gradient\(to bottom (?:right|left), transparent calc\(50% - 1px\), var\(--flecha\) calc\(50% - 1px\), var\(--flecha\) calc\(50% \+ 1px\), transparent calc\(50% \+ 1px\)\)/', $r), "chevron de dos trazos de 2 px del color --flecha");
        $this->assertMatchesRegularExpression('/background-repeat:\s*no-repeat/', $r);
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,8}\b|\brgba?\(/', $r, "solo tokens");
        // los trazos: «\\» a la izquierda y «/» a la derecha forman una V (flecha hacia abajo), no una A
        $this->assertMatchesRegularExpression('/background-position:\s*right 16px center,\s*right 23px center/', $r, "el trazo «/» (to bottom right) a la derecha y el «\\» (to bottom left) a la izquierda");
        preg_match('/padding-right:\s*(\d+)px/', $r, $p);
        preg_match('/background-size:\s*(\d+)px/', $r, $t);
        $this->assertGreaterThanOrEqual(23 + (int)$t[1] + 8, (int)$p[1], "el texto no pisa la flecha: relleno derecho ≥ posición + tamaño + 8 px");
        foreach (["background-image", "background-repeat", "background-size", "background-position"] as $prop) {
            $this->assertMatchesRegularExpression('/' . $prop . ':[^;]*!important/', $r, "$prop con !important: .dark-input usa el atajo background con !important y la borraría");
        }
    }

    public function testLaFlechaPasaATitularEnHoverYFocoYElBordeAlAmarilloEnFoco(): void {
        $h = self::regla("select:hover, select:focus, select:focus-visible, .form-select:hover, .form-select:focus, .form-select:focus-visible");
        $this->assertMatchesRegularExpression('/--flecha:\s*var\(--text-strong\)/', $h);
        $f = self::regla("select:focus, select:focus-visible, .form-select:focus, .form-select:focus-visible");
        $this->assertMatchesRegularExpression('/border-color:\s*var\(--btn-primary-bg\)\s*!important/', $f, "borde amarillo de marca en foco");
        $d = self::regla("select:disabled, .form-select:disabled");
        $this->assertMatchesRegularExpression('/cursor:\s*not-allowed/', $d);
    }

    public function testLosTokensDeLaFlechaPasanElContrasteDeComponenteEnLosDosModos(): void {
        $tema = self::css("tema.css");
        foreach (["dark", "light"] as $modo) {
            $v = $this->tokens($tema, $modo);
            foreach (["--text-muted", "--text-strong"] as $t) {
                foreach (["--surface-elevated", "--surface", "--canvas"] as $fondo) {
                    $this->assertGreaterThanOrEqual(3.0, \TemaGenerador::contraste($v[$t], $v[$fondo]), "$modo: $t sobre $fondo (componente: 3:1)");
                }
            }
        }
    }

    /** @return array<string,string> */
    private function tokens(string $tema, string $modo): array {
        $colores = [];
        preg_match_all('/--color-([a-z0-9-]+):\s*(#[0-9a-f]{6})/i', $tema, $m, PREG_SET_ORDER);
        foreach ($m as $x) $colores[$x[1]] = $x[2];
        $bloque = $modo === "dark" ? '/:root,\s*\[data-theme="dark"\][^{]*\{([^}]*)\}/' : '/\[data-theme="light"\][^{]*\{([^}]*)\}/';
        preg_match($bloque, $tema, $b);
        $salida = [];
        preg_match_all('/(--[a-z-]+):\s*var\(--color-([a-z0-9-]+)\)/', $b[1] ?? "", $mm, PREG_SET_ORDER);
        foreach ($mm as $x) $salida[$x[1]] = $colores[$x[2]] ?? "#000000";
        return $salida;
    }

    public function testTodosLosSelectDeTodasLasPantallasSonSelectSimplesSinEstilosPropios(): void {
        $total = 0;
        foreach (Pantallas::nombres() as $n) {
            $x = Html::xpath(Pantallas::render($this->db, $n));
            foreach ($x->query("//select") as $s) {
                $total++;
                $this->assertFalse($s->hasAttribute("multiple") || $s->hasAttribute("size"), "$n: un select múltiple no llevaría la flecha");
                $this->assertStringNotContainsString("background", $s->getAttribute("style"), "$n: sin estilos en línea que quiten la flecha");
                $this->assertStringNotContainsString("appearance", $s->getAttribute("style"));
                $this->assertDoesNotMatchRegularExpression('/form-select-(sm|lg)/', $s->getAttribute("class"), "$n: el alto lo da la regla de los campos (40/44)");
            }
        }
        $this->assertGreaterThan(5, $total, "hay select en las pantallas del sitio (catálogo, compra, panel)");
    }

    public function testLosCamposMantienen40PxEnEscritorioY44EnTactil(): void {
        $css = self::css();
        $this->assertMatchesRegularExpression('/min-height:\s*40px/', self::regla(".form-control, .form-select, .form-control-sm, .form-select-sm, .input-group-text,\n.dark-input, input:not([type=\"checkbox\"]):not([type=\"radio\"]):not([type=\"hidden\"]):not([type=\"file\"]), select") ?: $css, "la altura de los campos no cambió");
        $this->assertSame(0, preg_match('/select[^{}]*\{[^}]*\bheight:\s*\d+px/', self::regla("select, .form-select")), "la flecha no toca la altura");
    }

    // ── Movimiento ──

    public function testElBloqueDeMovimientoVaSoloConMovimientoNoReducido(): void {
        $m = self::media("(prefers-reduced-motion: no-preference)");
        $this->assertNotSame("", $m);
        $this->assertMatchesRegularExpression('/main:not\(:has\(\.hero-section\)\)\s*\{\s*animation:\s*pagina-entra \.25s ease-out backwards/', $m, "el contenido principal aparece al cargar (la portada tiene su hero)");
        $this->assertMatchesRegularExpression('/\.rv-item\s*\{\s*opacity:\s*0;\s*\}/', $m);
        $this->assertMatchesRegularExpression('/\.rv-item\.rv-visto\s*\{\s*opacity:\s*1;\s*animation:\s*item-entra \.25s ease-out var\(--rv-retraso, 0ms\) backwards/', $m, "con backwards, el hover sigue funcionando después");
        $this->assertMatchesRegularExpression('/\.dropdown-menu\.show\s*\{\s*animation:\s*menu-entra \.15s ease-out/', $m, "menús desplegables en 150 ms");
        $this->assertMatchesRegularExpression('/\.btn:hover:not\(:disabled\):not\(\.disabled\)\s*\{\s*transform:\s*translateY\(-1px\)/', $m, "botones: 1 px hacia arriba");
        $this->assertMatchesRegularExpression('/\.btn:active:not\(:disabled\):not\(\.disabled\)\s*\{\s*transform:\s*none/', $m, "y de vuelta al pulsar");
        $this->assertMatchesRegularExpression('/\.input-group \.btn, \.hero-buscar \.btn, \.modal-confirmar-acciones \.btn\s*\{\s*transform:\s*none !important/', $m, "los botones pegados a un campo o a otro botón no se mueven");
    }

    public function testLaGuardaDeMovimientoReducidoApagaTodoLoNuevo(): void {
        $m = self::media("(prefers-reduced-motion: reduce)");
        $this->assertMatchesRegularExpression('/animation-duration:\s*0\.01ms !important/', $m);
        $this->assertMatchesRegularExpression('/transition-duration:\s*0\.01ms !important/', $m);
        $this->assertMatchesRegularExpression('/\.btn:hover, \.btn:active[^{]*\{\s*transform:\s*none !important/s', $m, "sin desplazamiento en el hover de los botones");
        $this->assertMatchesRegularExpression('/\.rv-item\s*\{\s*opacity:\s*1 !important;\s*animation:\s*none !important/', $m, "las filas y tarjetas se ven siempre");
    }

    /** @return list<array{0:string,1:string}> */
    private function keyframes(): array {
        $salida = [];
        foreach (["base.css", "panel.css", "lista.css", "style.css", "extra.css"] as $f) {
            preg_match_all('/@keyframes\s+([\w-]+)\s*\{((?:[^{}]*\{[^}]*\})+)\s*\}/', self::css($f), $m, PREG_SET_ORDER);
            foreach ($m as $k) $salida[] = [$f . ":" . $k[1], $k[2]];
        }
        return $salida;
    }

    public function testTodosLosFotogramasDelSitioSoloMuevenOpacidadYTransform(): void {
        $kf = $this->keyframes();
        $this->assertGreaterThanOrEqual(5, count($kf), "hero-entra, pagina-entra, item-entra, menu-entra y modal-entra");
        foreach ($kf as [$nombre, $cuerpo]) {
            preg_match_all('/([a-z-]+)\s*:/', $cuerpo, $p);
            foreach (array_unique($p[1]) as $prop) $this->assertContains($prop, ["opacity", "transform"], "$nombre: anima $prop");
        }
    }

    public function testNingunaTransicionNiAnimacionPasaDe250MsNiAnimaTamanoOSombras(): void {
        // style.css tiene la tarjeta KPI del dashboard (transform y sombra); se rehace en S6a. extra.css, el truco del autocompletado.
        foreach (["base.css", "panel.css", "lista.css"] as $f) {
            $css = preg_replace('~/\*.*?\*/~s', "", self::css($f));
            // El fundido (500 ms) y el llenado de las barritas (5 s) del hero están aprobados en DESIGN.md y los vigila HeroCarruselTest
            $css = preg_replace('~\.hero-(?:diapo|carrusel)[^{]*\{[^}]*\}~', "", $css);
            preg_match_all('/(?<![-\w])(transition|animation)(?:-duration)?\s*:\s*([^;}]*)/', $css, $m, PREG_SET_ORDER);
            foreach ($m as $x) {
                if (str_contains($x[2], "0.01ms")) continue;
                preg_match_all('/(?<![\w.-])(\d*\.?\d+)(ms|s)\b/', $x[2], $t, PREG_SET_ORDER);
                foreach ($t as $d) {
                    $ms = $d[2] === "s" ? (float)$d[1] * 1000 : (float)$d[1];
                    $this->assertLessThanOrEqual(250, $ms, "$f: «{$x[0]}» pasa de 250 ms");
                    if ($x[1] === "transition" || $x[1] === "animation") $this->assertLessThanOrEqual(250, $ms);
                }
                if ($x[1] === "transition") {
                    $this->assertDoesNotMatchRegularExpression('/\b(width|height|padding|margin|box-shadow|top|left|right|bottom|font-size|all)\b/', $x[2], "$f: transición de tamaño, posición o sombra: {$x[0]}");
                }
            }
            $this->assertDoesNotMatchRegularExpression('/will-change/', $css, "$f: sin will-change");
        }
        $style = self::css("style.css");
        $this->assertSame(1, substr_count($style, "box-shadow .2s"), "la única sombra animada que queda es la de la tarjeta KPI del dashboard (S6a)");
    }

    public function testElModalEntraEn200MsConOpacidadYTransform(): void {
        $this->assertMatchesRegularExpression('/animation:\s*modal-entra 200ms ease-out/', self::css("panel.css"));
    }

    public function testElCajonDelPanelSeDeslizaEn250Ms(): void {
        $this->assertMatchesRegularExpression('/transition:\s*transform 250ms ease-out/', self::css("panel.css"));
        $this->assertDoesNotMatchRegularExpression('/transition[^;]*(width|grid)/', self::css("panel.css"), "la columna de iconos del menú cambia al instante: nada de animar tamaño");
    }

    public function testLasTarjetasConHoverLevantan2PxYPasanElBordeAlAmarillo(): void {
        foreach ([".cat-baldosa:hover", ".marca-enlace:focus-visible .marca-casilla"] as $sel) {
            $r = self::regla($sel);
            $this->assertMatchesRegularExpression('/border-color:\s*var\(--btn-primary-bg\)/', $r, $sel);
            $this->assertMatchesRegularExpression('/transform:\s*translateY\(-2px\)/', $r, $sel);
        }
        $this->assertMatchesRegularExpression('/transform:\s*translateY\(-2px\)/', self::regla(".tarjeta-pieza:hover"));
    }

    public function testElObservadorDeMainJsEscalonaYRespetaElMovimientoReducido(): void {
        $js = (string)file_get_contents(BASE_PATH . "/assets/js/main.js");
        $this->assertMatchesRegularExpression('/if \(!sinMovimiento && "IntersectionObserver" in window && itemsRv\.length\)/', $js, "con la misma guarda que las secciones de la portada");
        $this->assertStringContainsString('.tarjeta-pieza:not(.portada-seccion .tarjeta-pieza), .lista-tabla tbody tr:not(.lista-vacia), .form-card', $js);
        $this->assertStringContainsString("Math.min(n * 50, 300)", $js, "retraso de 50 ms en 50, hasta 300");
        $this->assertSame(1, substr_count($js, "new IntersectionObserver(") - 1, "un solo observador nuevo, además del de la portada");
        $this->assertStringNotContainsString("setTimeout(function(){ e.target", $js, "sin temporizadores: el retraso lo pone el CSS");
    }

    public function testLasPantallasConListasTienenLasFilasQueEntranEscalonadas(): void {
        $x = Html::xpath(Pantallas::render($this->db, "admin_partes"));
        $this->assertGreaterThan(0, Html::contar($x, "//table[contains(@class,'lista-tabla')]/tbody/tr"));
        $x2 = Html::xpath(Pantallas::render($this->db, "catalogo"));
        $this->assertGreaterThan(0, Html::contar($x2, "//*[contains(@class,'tarjeta-pieza')]"));
        $this->assertSame(0, Html::contar($x, "//*[contains(@class,'rv-item')]"), "la clase la pone main.js, no el HTML: sin JavaScript todo se ve");
        $this->assertSame(0, Html::contar($x2, "//*[contains(@class,'rv-item')]"));
    }

    public function testLaTarjetaDeFormularioYElMainTienenElElementoParaAnimar(): void {
        $x = Html::xpath(Pantallas::render($this->db, "admin_partes_crear"));
        $this->assertGreaterThanOrEqual(1, Html::contar($x, "//main//*[contains(@class,'form-card')]"));
        $this->assertSame(1, Html::contar($x, "//main"), "un solo main: es el que aparece al cargar");
    }
}
