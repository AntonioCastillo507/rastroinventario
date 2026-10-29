<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/**
 * S4.5.3: iconos de categoría (marca.php), mosaico de marcas, movimiento sutil (DESIGN.md «Movimiento») y textos de la portada.
 * El CSS se prueba por estructura y valores; la aparición de secciones con Node sobre un DOM falso.
 */
final class PortadaMovimientoTest extends BaseDatos {
    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
    }

    protected function tearDown(): void {
        \Marca::usarParaPruebas(null);
        parent::tearDown();
    }

    private static function css(): string {
        return (string)file_get_contents(BASE_PATH . "/assets/css/base.css");
    }

    private static function regla(string $selector): string {
        preg_match('/(?:^|[}\s])' . preg_quote($selector, "/") . '\s*\{([^}]*)\}/m', self::css(), $m);
        return $m[1] ?? "";
    }

    private function home(array $portada = []): \DOMXPath {
        $m = \Marca::todo();
        $m["portada"] = array_merge($m["portada"], $portada);
        \Marca::usarParaPruebas($m);
        return Html::xpath(Pantallas::render($this->db, "home"));
    }

    // ── iconos de categoría ──
    public function testCadaCategoriaLlevaUnIconoDecorativoDeSuPropiaEntrada(): void {
        $x = $this->home(["categoria_iconos" => [], "categoria_icono_defecto" => "tag"]);
        $baldosas = $x->query("//ul[contains(@class,'cat-lista')]//a");
        $this->assertGreaterThan(0, $baldosas->length);
        foreach ($baldosas as $a) {
            $i = $x->query(".//span[contains(@class,'cat-icono')]/i[contains(@class,'bi-tag')]", $a);
            $this->assertSame(1, $i->length, "una baldosa sin icono");
            $this->assertSame("true", $i->item(0)->getAttribute("aria-hidden"));
            $this->assertSame("true", $i->item(0)->parentNode->getAttribute("aria-hidden"));
            $this->assertNotSame("", trim($x->query(".//*[contains(@class,'cat-nombre')]", $a)->item(0)->textContent), "el nombre va escrito al lado");
        }
    }

    public function testElIconoSeElijeDesdeMarcaPorIdDeSeccion(): void {
        $ids = array_map(fn($a) => (int)preg_replace('/\D+/', "", explode("seccion=", $a->getAttribute("href"))[1]), iterator_to_array($this->home()->query("//ul[contains(@class,'cat-lista')]//a")));
        $this->assertGreaterThanOrEqual(2, count($ids), "la base de pruebas con volumen trae varias secciones");
        $x = $this->home(["categoria_iconos" => [$ids[0] => "gear", $ids[1] => "wrench"], "categoria_icono_defecto" => "box"]);
        $icono = fn(int $id) => $x->query("//ul[contains(@class,'cat-lista')]//a[contains(@href,'seccion=$id')]//i[contains(@class,'bi-')]")->item(0)->getAttribute("class");
        $this->assertSame("bi bi-gear", $icono($ids[0]));
        $this->assertSame("bi bi-wrench", $icono($ids[1]));
        foreach (array_slice($ids, 2) as $id) $this->assertSame("bi bi-box", $icono($id), "las demás usan el de por defecto");
    }

    public function testIconoCategoriaSaneaYTieneRespaldo(): void {
        $this->assertSame("car-front", \Marca::iconoCategoria(1));
        $this->assertSame("box-seam", \Marca::iconoCategoria(999), "una sección sin icono usa el de por defecto");
        foreach ([["x\"><script>", "xscript"], ["  GEAR-Wide  ", "gear-wide"], ["", "box-seam"], ["!!!", "box-seam"]] as [$sucio, $limpio]) {
            $m = \Marca::todo();
            $m["portada"]["categoria_iconos"] = [7 => $sucio];
            $m["portada"]["categoria_icono_defecto"] = "";
            \Marca::usarParaPruebas($m);
            $this->assertSame($limpio, \Marca::iconoCategoria(7), $sucio);
        }
    }

    public function testLosIconosDeMarcaPhpExistenEnBootstrapIcons(): void {
        $css = (string)file_get_contents(BASE_PATH . "/assets/vendor/bootstrap-icons/bootstrap-icons.min.css");
        $p = \Marca::todo()["portada"];
        foreach (array_merge(array_values($p["categoria_iconos"]), [$p["categoria_icono_defecto"], "arrow-right"]) as $icono) {
            $this->assertStringContainsString(".bi-$icono::before", $css, "el icono bi-$icono no está en la fuente autoalojada");
        }
    }

    public function testNombresDeSeccionVienenDeLaBaseNoDeMarcaPhp(): void {
        $this->assertStringNotContainsString("Carrocería", (string)file_get_contents(BASE_PATH . "/app/Config/marca.php"), "los nombres de las categorías son datos de la base");
    }

    public function testCategoriaHoverSubeYPasaAlAmarillo(): void {
        $this->assertMatchesRegularExpression('/border-color:\s*var\(--btn-primary-bg\)/', self::regla(".cat-baldosa:hover"));
        $this->assertMatchesRegularExpression('/transform:\s*translateY\(-2px\)/', self::regla(".cat-baldosa:hover"));
        $this->assertMatchesRegularExpression('/width:\s*40px/', self::regla(".cat-icono"));
        $this->assertMatchesRegularExpression('/height:\s*40px/', self::regla(".cat-icono"));
    }

    // ── carrusel de marcas (S7) ──
    public function testLasMarcasSonLogosOMonogramasConEnlaceYNombreAccesible(): void {
        $x = $this->home();
        $t = $x->query("//ul[@data-marcas-pista]//a[contains(@class,'marca-enlace')]");
        $this->assertGreaterThan(0, $t->length);
        foreach ($t as $a) {
            $this->assertSame(1, $x->query(".//span[contains(@class,'marca-casilla') and @aria-hidden='true']", $a)->length, "casilla decorativa");
            $this->assertMatchesRegularExpression('/^.+, \d+ piezas?$/u', trim($x->query(".//span[contains(@class,'visually-hidden')]", $a)->item(0)->textContent), "el nombre y el conteo van como texto accesible");
            $this->assertSame(0, $x->query(".//img | .//svg", $a)->length, "el logo es una máscara, no una imagen en línea");
        }
        $this->assertSame(0, $x->query("//a[contains(@class,'marca-chip') or contains(@class,'marca-tarjeta')]")->length, "ya no hay chips ni tarjetas de texto");
    }

    public function testElCssDelCarruselTieneCasillasHoverYMascaraConPrefijo(): void {
        $css = self::css();
        $this->assertMatchesRegularExpression('/\.marca-casilla \{[^}]*width:\s*96px;\s*height:\s*44px/', $css);
        $this->assertMatchesRegularExpression('/@media \(min-width: 768px\) \{ \.marca-casilla \{ width: 128px; height: 56px; \} \}/', $css);
        $this->assertMatchesRegularExpression('/-webkit-mask-image:\s*var\(--logo-url\);\s*mask-image:\s*var\(--logo-url\)/', self::regla(".marca-mascara"), "máscara con y sin prefijo");
        $this->assertMatchesRegularExpression('/-webkit-mask-size:\s*contain;\s*mask-size:\s*contain/', self::regla(".marca-mascara"));
        $this->assertMatchesRegularExpression('/background-color:\s*currentColor/', self::regla(".marca-mascara"), "una sola tinta");
        $hover = self::regla(".marca-enlace:focus-visible .marca-casilla");
        $this->assertMatchesRegularExpression('/color:\s*var\(--link\)/', $hover, "acento al pasar el cursor y al enfocar");
        $this->assertMatchesRegularExpression('/border-color:\s*var\(--btn-primary-bg\)/', $hover);
        $this->assertMatchesRegularExpression('/transform:\s*translateY\(-2px\)/', $hover);
        $this->assertMatchesRegularExpression('/color:\s*var\(--text-muted\)/', self::regla(".marca-casilla"), "gris secundario en reposo");
        $this->assertMatchesRegularExpression('/width:\s*44px;\s*height:\s*44px/', self::regla(".marcas-nav"), "flechas de 44 px");
        $this->assertMatchesRegularExpression('/border-radius:\s*var\(--rounded-pill\)/', self::regla(".marcas-nav"));
        $this->assertMatchesRegularExpression('/overflow-x:\s*auto/', self::regla(".marcas-ventana"), "sin JavaScript es una fila con scroll");
    }

    public function testElCarruselNoUsaAnimacionesCssYLasTransicionesNoMuevenTamano(): void {
        $css = self::css();
        $ini = strpos($css, "/* Carrusel de marcas de la portada");
        $this->assertNotFalse($ini);
        $bloque = substr($css, $ini, strpos($css, "/* ── Movimiento (DESIGN.md") - $ini);
        $this->assertStringNotContainsString("@keyframes", $bloque, "el movimiento continuo lo hace marcas_carrusel.js, no una animación CSS que prefers-reduced-motion cortaría a medias");
        $this->assertDoesNotMatchRegularExpression('/transition:[^;}]*\b(width|height|padding|margin|all)\b/', $bloque);
    }
    // ── movimiento ──
    public function testLasTransicionesSonDe150A250MsYSoloDeTransformOpacidadYColorDeBorde(): void {
        $css = self::css();
        // El fundido de 500 ms de las diapositivas del hero está aprobado en DESIGN.md y lo vigila HeroCarruselTest
        $css = preg_replace('~\.hero-diapo \{[^}]*\}|\.hero-carrusel:not\(\.hero-js\)[^{]*\{[^}]*\}~', "", $css);
        preg_match_all('/transition:\s*([^;}]+)[;}]/', substr($css, strpos($css, ".cat-baldosa")), $m);
        $this->assertNotEmpty($m[1]);
        foreach ($m[1] as $t) {
            foreach (explode(",", $t) as $parte) {
                $parte = trim($parte);
                if (!preg_match('/^(transform|opacity|border-color|background-color|color)\s+\.(\d+)s\b/', $parte, $q)) $this->fail("transición fuera de la regla: $parte");
                $ms = (int)$q[2] * (strlen($q[2]) === 1 ? 100 : 10);
                $this->assertGreaterThanOrEqual(150, $ms, $parte);
                $this->assertLessThanOrEqual(250, $ms, $parte);
            }
        }
        $this->assertDoesNotMatchRegularExpression('/transition:[^;}]*\b(width|height|padding|margin|box-shadow|all)\b/', $css, "no se anima tamaño ni sombras");
    }

    public function testLaAparicionSoloSeDeclaraSinPreferenciaDeMovimientoReducido(): void {
        $css = self::css();
        $this->assertMatchesRegularExpression('/@media \(prefers-reduced-motion: no-preference\) \{[^@]*@keyframes hero-entra[^@]*\.hero-texto \{ animation: hero-entra \.25s ease-out both; \}[^@]*\.portada-seccion\.rv \{ opacity: 0; transform: translateY\(12px\);[^@]*\.portada-seccion\.rv\.rv-visto \{ opacity: 1; transform: none; \}/s', $css);
        $this->assertMatchesRegularExpression('/@keyframes hero-entra \{ from \{ opacity: 0; transform: translateY\(8px\); \} to \{ opacity: 1; transform: none; \} \}/', $css, "el hero solo anima transform y opacity");
        $this->assertMatchesRegularExpression('/\.cat-baldosa:hover, \.marca-enlace:hover \.marca-casilla, \.marca-enlace:focus-visible \.marca-casilla,[^}]*transform:\s*none\s*!important/s', $css, "con movimiento reducido el hover no mueve");
        $this->assertSame(1, substr_count($css, ".portada-seccion.rv {"), "la clase que oculta se declara una sola vez, dentro de la media query");
    }

    public function testDesignMdDocumentaElMovimiento(): void {
        $d = (string)file_get_contents(BASE_PATH . "/docs/DESIGN.md");
        $this->assertStringContainsString("## Movimiento", $d);
        $this->assertStringContainsString("de 150 a 250 ms", $d);
        $this->assertStringContainsString("prefers-reduced-motion", substr($d, strpos($d, "## Movimiento")));
        $this->assertStringContainsString("categoria_iconos", $d, "la excepción de iconos en las categorías está documentada");
    }

    public function testDesignMdDocumentaElCarruselDeMarcasYLaCasilla(): void {
        $d = (string)file_get_contents(BASE_PATH . "/docs/DESIGN.md");
        $mov = substr($d, strpos($d, "## Movimiento"), strpos($d, "## Qué hacer y qué no") - strpos($d, "## Movimiento"));
        foreach (["Carrusel de marcas (S7)", "30 px por segundo", "marcas_carrusel.js", "prefers-reduced-motion: reduce", "WCAG 2.2.2"] as $t) $this->assertStringContainsString($t, $mov, $t);
        foreach (["96×44", "128×56", "una sola tinta", "monograma", "44 px"] as $t) $this->assertStringContainsString($t, $d, $t);
        $this->assertMatchesRegularExpression("/^\| 35 \| S7: /m", $d, "fila 35 de Adaptaciones");
        $this->assertStringNotContainsString("flecha de la tarjeta de marca", $d, "la tarjeta de texto de marca ya no existe");
    }

    public function testElObservadorMarcaYRevelaLasSeccionesSoloConMovimiento(): void {
        $p = proc_open(["node", BASE_PATH . "/tests/Support/revelar_js_prueba.js"], [1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes);
        if (!is_resource($p)) $this->markTestSkipped("No hay Node.");
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($p), $err);
        $r = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
        foreach ($r as $k => $v) if ($k !== "umbral") $this->assertTrue($v, $k);
        $this->assertEqualsWithDelta(0.1, $r["umbral"], 0.0001);
    }

    // ── textos ──
    public function testLemaYSubtituloNoDicenAutosUsados(): void {
        $m = \Marca::todo();
        $this->assertSame("Repuestos usados", $m["eslogan"]);
        $this->assertSame("Repuestos usados para tu carro en Panamá. Busca por nombre o por marca.", $m["portada"]["subtitulo"]);
        $x = $this->home();
        $this->assertStringContainsString("Repuestos usados para tu carro en Panamá", $x->query("//p[contains(@class,'hero-sub')]")->item(0)->textContent);
    }

    public function testNingunTextoVisibleRepiteLaConfusionDeAutosUsados(): void {
        foreach (["app/Config/marca.php", "README.md", "CLAUDE.md"] as $f) {
            $this->assertDoesNotMatchRegularExpression('/(autos|carros|veh[ií]culos) usados/iu', (string)file_get_contents(BASE_PATH . "/$f"), $f);
        }
        $vistas = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(BASE_PATH . "/app/Views", \FilesystemIterator::SKIP_DOTS));
        foreach ($vistas as $f) {
            if ($f->getExtension() === "php") $this->assertDoesNotMatchRegularExpression('/(autos|carros|veh[ií]culos) usados/iu', (string)file_get_contents($f->getPathname()), $f->getFilename());
        }
        $this->assertStringNotContainsString("Rastro & Partes", (string)file_get_contents(BASE_PATH . "/app/Config/marca.php"));
    }
}
