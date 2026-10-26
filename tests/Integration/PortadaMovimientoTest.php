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

    // ── mosaico de marcas ──
    public function testLasMarcasSonTarjetasConNombreConteoYFlechaSinLogos(): void {
        $x = $this->home();
        $t = $x->query("//ul[contains(@class,'marca-lista')]//a[contains(@class,'marca-tarjeta')]");
        $this->assertGreaterThan(0, $t->length);
        foreach ($t as $a) {
            $this->assertSame(1, $x->query(".//*[contains(@class,'marca-tarjeta-nombre')]", $a)->length);
            $this->assertMatchesRegularExpression('/^\d+ piezas?$/u', trim($x->query(".//*[contains(concat(' ',@class,' '),' marca-tarjeta-n ')]", $a)->item(0)->textContent));
            $f = $x->query(".//i[contains(@class,'bi-arrow-right')]", $a);
            $this->assertSame(1, $f->length);
            $this->assertSame("true", $f->item(0)->getAttribute("aria-hidden"));
            $this->assertSame(0, $x->query(".//img | .//svg", $a)->length, "sin logos de marcas");
        }
        $this->assertSame(0, $x->query("//a[contains(@class,'marca-chip')]")->length, "ya no hay chips");
    }

    public function testElMosaicoEsUnaRejillaDe2_3Y4ColumnasConHover(): void {
        $this->assertMatchesRegularExpression('/grid-template-columns:\s*repeat\(2,/', self::regla(".marca-lista"));
        $css = self::css();
        $this->assertMatchesRegularExpression('/@media \(min-width: 768px\) \{ \.marca-lista \{ grid-template-columns: repeat\(3,/', $css);
        $this->assertMatchesRegularExpression('/@media \(min-width: 1200px\) \{ \.marca-lista \{ grid-template-columns: repeat\(4,/', $css);
        $this->assertMatchesRegularExpression('/border-color:\s*var\(--btn-primary-bg\)/', self::regla(".marca-tarjeta:hover"));
        $this->assertMatchesRegularExpression('/translateX\(4px\)/', self::regla(".marca-tarjeta:hover .marca-flecha"));
        $this->assertMatchesRegularExpression('/min-height:\s*96px/', self::regla(".marca-tarjeta"), "más grande que el chip de 44 px");
    }

    // ── movimiento ──
    public function testLasTransicionesSonDe150A250MsYSoloDeTransformOpacidadYColorDeBorde(): void {
        $css = self::css();
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
        $this->assertMatchesRegularExpression('/\.cat-baldosa:hover, \.marca-tarjeta:hover, \.marca-tarjeta:hover \.marca-flecha,[^}]*transform:\s*none\s*!important/s', $css, "con movimiento reducido el hover no mueve");
        $this->assertSame(1, substr_count($css, ".portada-seccion.rv {"), "la clase que oculta se declara una sola vez, dentro de la media query");
    }

    public function testDesignMdDocumentaElMovimiento(): void {
        $d = (string)file_get_contents(BASE_PATH . "/docs/DESIGN.md");
        $this->assertStringContainsString("## Movimiento", $d);
        $this->assertStringContainsString("de 150 a 250 ms", $d);
        $this->assertStringContainsString("prefers-reduced-motion", substr($d, strpos($d, "## Movimiento")));
        $this->assertStringContainsString("categoria_iconos", $d, "la excepción de iconos en las categorías está documentada");
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
