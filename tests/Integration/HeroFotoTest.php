<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\ContrasteHero;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/**
 * Hero de la portada con foto: la capa por modo sale de tema.css y da 4,5:1 al título y al subtítulo sobre el píxel
 * más claro (oscuro) o más oscuro (claro) de cualquier foto y de las dos fotos reales; la foto solo se pinta si el archivo
 * existe (la plantilla no la trae y usa el hero tipográfico) y no entra en git.
 */
final class HeroFotoTest extends BaseDatos {
    private const FOTOS = ["escritorio" => "assets/img/portada/hero-escritorio.webp", "movil" => "assets/img/portada/hero-movil.webp"];

    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
    }

    protected function tearDown(): void {
        \Marca::usarParaPruebas(null);
        parent::tearDown();
    }

    private function modo(string $modo): array {
        return \TemaGenerador::leerVariables((string)file_get_contents(BASE_PATH . "/assets/css/tema.css"), $modo);
    }

    private function home(array $portada): \DOMXPath {
        $m = \Marca::todo();
        $m["portada"] = array_replace($m["portada"], $portada);
        \Marca::usarParaPruebas($m);
        return Html::xpath(Pantallas::render($this->db, "home"));
    }

    /** color resultante de la capa sobre un píxel de luminancia dada (gris $g) */
    private static function compuesto(array $v, int $gris): array {
        $capa = ContrasteHero::hex($v["hero-capa"]);
        $a = (float)$v["hero-capa-alfa"];
        return array_map(fn($c) => $c * $a + $gris * (1 - $a), $capa);
    }

    // ── La capa por modo, leída de tema.css ──

    public function testLaCapaYSuOpacidadVienenDeTemaCssPorModo(): void {
        $d = $this->modo("dark");
        $l = $this->modo("light");
        $this->assertSame("#0b0e11", $d["hero-capa"], "oscuro: el lienzo oscuro");
        $this->assertSame("#f2ecde", $l["hero-capa"], "claro: crema cálido (surface-strong-light)");
        $this->assertSame(0.76, (float)$d["hero-capa-alfa"]);
        $this->assertSame(0.9, (float)$l["hero-capa-alfa"]);
        foreach (["#F97316", "#2563EB"] as $principal) {
            $css = \TemaGenerador::generar($principal)["css"] ?? "";
            $this->assertSame(2, preg_match_all('/--hero-capa-alfa:\s*0?\.(?:76|9);/', $css), "$principal: la capa también sale para otros colores principales");
        }
    }

    public function testElTituloYElSubtituloDan4_5ConElPeorPixelPosibleEnLosDosModos(): void {
        // el píxel más claro posible (blanco) en oscuro y el más oscuro (negro) en claro: vale para cualquier foto
        foreach (["dark" => 255, "light" => 0] as $modo => $gris) {
            $v = $this->modo($modo);
            $fondo = self::compuesto($v, $gris);
            $lf = ContrasteHero::luminancia(...$fondo);
            foreach (["text-strong", "text"] as $t) {
                $r = ContrasteHero::razon(ContrasteHero::luminancia(...ContrasteHero::hex($v[$t])), $lf);
                $this->assertGreaterThanOrEqual(4.5, $r, sprintf("%s: %s sobre el peor píxel con la capa al %d %%: %.2f:1", $modo, $t, (float)$v["hero-capa-alfa"] * 100, $r));
            }
        }
    }

    public function testLaCapaNoSeQuedaCortaSiBajaLaOpacidad(): void {
        // con 10 puntos menos de opacidad el peor píxel ya no llega a 4,5:1 en oscuro: el valor elegido no está de sobra
        $v = $this->modo("dark");
        $v["hero-capa-alfa"] = (float)$v["hero-capa-alfa"] - 0.20;
        $fondo = self::compuesto($v, 255);
        $r = ContrasteHero::razon(ContrasteHero::luminancia(...ContrasteHero::hex($v["text"])), ContrasteHero::luminancia(...$fondo));
        $this->assertLessThan(4.5, $r);
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function fotosYModos(): array {
        $o = [];
        foreach (self::FOTOS as $n => $ruta) foreach (["dark", "light"] as $m) $o["$n en $m"] = [$ruta, $m];
        return $o;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("fotosYModos")]
    public function testElTextoDa4_5SobreLaFotoRealEnCualquierPunto(string $ruta, string $modo): void {
        if (!is_file(BASE_PATH . "/" . $ruta)) $this->markTestSkipped("La plantilla no trae la foto ($ruta): usa el hero tipográfico.");
        $v = $this->modo($modo);
        foreach (["text-strong", "text"] as $t) {
            $peor = ContrasteHero::peorContraste(BASE_PATH . "/" . $ruta, ContrasteHero::hex($v[$t]), ContrasteHero::hex($v["hero-capa"]), (float)$v["hero-capa-alfa"]);
            $this->assertGreaterThanOrEqual(4.5, $peor, sprintf("%s, %s sobre %s: %.2f:1", $modo, $t, $ruta, $peor));
        }
    }

    // ── La foto en la vista ──

    public function testConFotoElHeroLlevaPictureCapaYClase(): void {
        $x = $this->home(["foto" => "assets/img/logo.png", "foto_movil" => "assets/img/logo.png"]);
        $sec = "//section[@id='inicio' and contains(@class,'portada-hero--foto')]";
        $this->assertSame(1, Html::contar($x, $sec));
        $this->assertSame(1, Html::contar($x, "$sec/picture[@aria-hidden='true']/source[@media='(min-width: 768px)']"));
        $img = $x->query("//section[@id='inicio']//img")->item(0);
        $this->assertSame("", $img->getAttribute("alt"), "decorativa: sin texto alternativo");
        $this->assertSame("async", $img->getAttribute("decoding"));
        $this->assertSame("high", $img->getAttribute("fetchpriority"), "es la imagen principal de la página");
        $this->assertSame(1, Html::contar($x, "$sec/div[contains(@class,'hero-capa') and @aria-hidden='true']"));
        $this->assertSame(0, Html::contar($x, "//section[@id='inicio']//*[@style]"), "nada de estilos en línea: la capa sale de tema.css");
        $this->assertStringNotContainsString("background-image", $x->document->saveHTML());
    }

    public function testElEscritorioYElMovilUsanSuPropiaFoto(): void {
        $x = $this->home(["foto" => "assets/img/logo.png", "foto_movil" => "assets/img/logo.svg"]);
        $this->assertStringEndsWith("/assets/img/logo.png", $x->query("//picture/source")->item(0)->getAttribute("srcset"));
        $this->assertStringEndsWith("/assets/img/logo.svg", $x->query("//section[@id='inicio']//img")->item(0)->getAttribute("src"));
    }

    public function testSiSoloHayUnaFotoSirveParaLosDosTamanos(): void {
        $x = $this->home(["foto" => "", "foto_movil" => "assets/img/logo.png"]);
        $this->assertSame(1, Html::contar($x, "//picture"));
        $this->assertStringEndsWith("/assets/img/logo.png", $x->query("//picture/source")->item(0)->getAttribute("srcset"));
        $x = $this->home(["foto" => "assets/img/logo.png", "foto_movil" => ""]);
        $this->assertStringEndsWith("/assets/img/logo.png", $x->query("//section[@id='inicio']//img")->item(0)->getAttribute("src"));
    }

    public function testSinFotoElHeroEsTipograficoSinPictureNiCapa(): void {
        $x = $this->home(["foto" => "", "foto_movil" => ""]);
        $this->assertSame(0, Html::contar($x, "//section[@id='inicio']//picture | //section[@id='inicio']//img | //*[contains(@class,'hero-capa')]"));
        $this->assertSame(0, Html::contar($x, "//*[contains(@class,'portada-hero--foto')]"));
        $this->assertSame(1, Html::contar($x, "//section[@id='inicio']//h1"), "el hero sigue completo");
        $this->assertSame(1, Html::contar($x, "//section[@id='inicio']//input[@type='search']"));
    }

    public function testUnaFotoQueNoEstaEnDiscoNiUnaRutaConPuntosNoPintanNada(): void {
        foreach (["assets/img/portada/no-existe.webp", "assets/img/../../app/Config/marca.php", "../index.php"] as $ruta) {
            $x = $this->home(["foto" => $ruta, "foto_movil" => $ruta]);
            $this->assertSame(0, Html::contar($x, "//picture | //*[contains(@class,'hero-capa')]"), $ruta);
        }
    }

    public function testLaPlantillaSinClavesDeFotoTambienEsTipografica(): void {
        \Marca::usarParaPruebas(["nombre" => "Plantilla", "portada" => ["titulo" => "T", "titulo_resalte" => "R", "subtitulo" => "S", "buscador" => "B"]]);
        $x = Html::xpath(Pantallas::render($this->db, "home"));
        $this->assertSame(0, Html::contar($x, "//picture | //*[contains(@class,'hero-capa')]"), "sin las claves, los valores neutros dejan el hero tipográfico");
    }

    public function testElAcentoDelTituloNoSePintaDeAmarilloSobreLaFoto(): void {
        $css = (string)file_get_contents(BASE_PATH . "/assets/css/base.css");
        $this->assertMatchesRegularExpression('/\.portada-hero--foto \.hero-resalte\s*\{\s*color:\s*inherit;/', $css);
        $this->assertMatchesRegularExpression('/\.hero-capa\s*\{[^}]*background:\s*var\(--hero-capa\);[^}]*opacity:\s*var\(--hero-capa-alfa\)/', $css);
        $this->assertMatchesRegularExpression('/\.hero-fondo img\s*\{[^}]*object-fit:\s*cover/', $css);
        $reglas = implode("}", array_filter(explode("}", $css), fn($r) => str_contains($r, ".hero-capa") || str_contains($r, ".hero-fondo")));
        $this->assertStringNotContainsString("gradient", $reglas, "DESIGN.md no admite degradados");
    }

    // ── La foto no entra en la plantilla ──

    public function testLaCarpetaDeLaFotoEstaEnGitignoreYNoHayArchivosVersionados(): void {
        $gi = (string)file_get_contents(BASE_PATH . "/.gitignore");
        $this->assertMatchesRegularExpression('~^/assets/img/portada/$~m', $gi);
        $salida = [];
        $codigo = 0;
        @exec("git -C " . escapeshellarg(BASE_PATH) . " ls-files assets/img/portada 2>&1", $salida, $codigo);
        if ($codigo !== 0) $this->markTestSkipped("sin git en esta copia");
        $this->assertSame([], array_filter($salida), "ningún archivo de assets/img/portada está versionado");
    }

    public function testLasFotosPesanPocoYTienenLaProporcionPedida(): void {
        foreach (self::FOTOS as $n => $ruta) {
            if (!is_file(BASE_PATH . "/$ruta")) $this->markTestSkipped("La plantilla no trae la foto.");
        }
        [$w, $h] = getimagesize(BASE_PATH . "/" . self::FOTOS["escritorio"]);
        $this->assertEqualsWithDelta(16 / 9, $w / $h, 0.01, "escritorio 16:9");
        $this->assertLessThanOrEqual(150 * 1024, filesize(BASE_PATH . "/" . self::FOTOS["escritorio"]));
        [$w, $h] = getimagesize(BASE_PATH . "/" . self::FOTOS["movil"]);
        $this->assertEqualsWithDelta(4 / 5, $w / $h, 0.01, "móvil 4:5");
        $this->assertLessThanOrEqual(80 * 1024, filesize(BASE_PATH . "/" . self::FOTOS["movil"]));
        $this->assertLessThan(filesize(BASE_PATH . "/" . self::FOTOS["escritorio"]), filesize(BASE_PATH . "/" . self::FOTOS["movil"]));
    }
}
