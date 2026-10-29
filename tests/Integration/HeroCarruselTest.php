<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\Datos;
use Tests\Support\Html;
use Tests\Support\Imagenes;
use Tests\Support\Pantallas;

/**
 * La consulta toma como mucho 4 piezas activas, con stock y con foto; la vista solo pinta la tarjeta con 2 o más fotos en disco.
 * El movimiento real (fundido, pausa, barritas) lo cubre el navegador integrado.
 */
final class HeroCarruselTest extends BaseDatos {
    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
        // Dentro de la transacción: ninguna pieza del volumen compite con las de la prueba
        $this->db->exec("UPDATE partes SET imagen_grande = NULL, thumbnail = NULL");
    }

    protected function tearDown(): void {
        \Marca::usarParaPruebas(null);
        parent::tearDown();
    }

    /** Pieza con foto real en disco (original, 640 y 1200) o con nombres de imagen que no existen */
    private function pieza(string $nombre, bool $enDisco = true, int $cantidad = 5, int $activo = 1): int {
        if ($enDisco) {
            $r = \ImageHelper::procesar(Imagenes::jpeg());
            [$orig, $thumb] = [$r["original"], $r["thumbnail"]];
        } else {
            $s = bin2hex(random_bytes(16));
            [$orig, $thumb] = ["parte_$s.jpg", "parte_{$s}_th.jpg"];
        }
        $id = Datos::parte($this->db, 12.50, $cantidad, $nombre);
        $this->db->prepare("UPDATE partes SET thumbnail = ?, imagen_grande = ?, activo = ? WHERE id = ?")->execute([$thumb, $orig, $activo, $id]);
        return $id;
    }

    private function portada(): \DOMXPath {
        return Html::xpath(Pantallas::render($this->db, "home"));
    }

    public function testLaConsultaTomaComoMuchoCuatroPiezasActivasConStockYFoto(): void {
        $buenas = [];
        for ($i = 1; $i <= 6; $i++) $buenas[] = $this->pieza("Buena $i");
        $mala1 = $this->pieza("Inactiva", true, 5, 0);
        $mala2 = $this->pieza("Sin stock", true, 0);
        $mala3 = Datos::parte($this->db, 9.00, 5, "Sin foto");
        for ($v = 0; $v < 5; $v++) {
            $ids = array_map(fn($p) => (int)$p["id"], (new \ParteModel())->paraHero(4));
            $this->assertCount(4, $ids);
            $this->assertSame($ids, array_values(array_unique($ids)), "sin repetidas");
            foreach ($ids as $id) {
                $this->assertContains($id, $buenas);
                $this->assertNotContains($id, [$mala1, $mala2, $mala3]);
            }
        }
    }

    public function testElTopeEsCuatroAunqueSePidaMas(): void {
        for ($i = 1; $i <= 6; $i++) $this->pieza("Tope $i");
        $this->assertCount(4, (new \ParteModel())->paraHero(50));
        $this->assertCount(1, (new \ParteModel())->paraHero(0), "mínimo una");
    }

    public function testConMenosDeDosFotosNoSeMuestraLaTarjeta(): void {
        $this->assertSame(0, Html::contar($this->portada(), "//*[@data-hero-carrusel]"), "ninguna");
        $this->pieza("Una sola");
        $this->assertSame(0, Html::contar($this->portada(), "//*[@data-hero-carrusel]"), "una");
        $this->pieza("Con archivo perdido", false);
        $this->assertSame(0, Html::contar($this->portada(), "//*[@data-hero-carrusel]"), "la foto que falta en disco no cuenta");
        $this->pieza("Segunda");
        $this->assertSame(1, Html::contar($this->portada(), "//*[@data-hero-carrusel]"), "dos");
    }

    public function testEstructuraDeLaTarjeta(): void {
        $ids = [$this->pieza("Faro izquierdo"), $this->pieza("Puerta derecha"), $this->pieza("Motor completo")];
        $x = $this->portada();
        $raiz = $x->query("//*[@data-hero-carrusel]");
        $this->assertSame(1, $raiz->length);
        $this->assertSame("carrusel", $raiz->item(0)->getAttribute("aria-roledescription"));
        $this->assertSame(\Marca::get("portada.hero_carrusel"), $raiz->item(0)->getAttribute("aria-label"));

        $diapos = $x->query("//*[@data-hero-carrusel]//a[@data-hero-diapo]");
        $this->assertSame(3, $diapos->length);
        $imgs = $x->query("//*[@data-hero-carrusel]//a[@data-hero-diapo]//img");
        foreach ($diapos as $i => $a) {
            $this->assertMatchesRegularExpression('~action=detalle&id=\d+$~', $a->getAttribute("href"), "toda la diapositiva enlaza a la pieza");
            $this->assertContains((int)preg_replace('~\D~', "", substr($a->getAttribute("href"), strrpos($a->getAttribute("href"), "=") + 1)), $ids);
            $img = $imgs->item($i);
            $this->assertMatchesRegularExpression('~_th\.jpg 640w, .+_1200\.jpg 1200w$~', $img->getAttribute("srcset"), "derivadas de 640 y 1200 px");
            $this->assertNotSame("", $img->getAttribute("sizes"));
            if ($i === 0) $this->assertFalse($img->hasAttribute("loading"), "la primera imagen no es lazy");
            else $this->assertSame("lazy", $img->getAttribute("loading"));
        }
        $html = $diapos->item(0)->textContent;
        $this->assertStringContainsString("$12.50", $html, "precio en la leyenda");

        $barras = $x->query("//*[@data-hero-carrusel]//button[@data-hero-barra]");
        $this->assertSame(3, $barras->length, "una barrita por diapositiva");
        foreach ($barras as $i => $b) {
            $this->assertSame("Ver la pieza " . ($i + 1) . " de 3", $b->getAttribute("aria-label"));
        }
        $pausa = $x->query("//*[@data-hero-carrusel]//button[@data-hero-pausa]")->item(0);
        $this->assertSame(\Marca::get("portada.hero_pausar"), $pausa->getAttribute("aria-label"));
        $this->assertSame(\Marca::get("portada.hero_reanudar"), $pausa->getAttribute("data-texto-reanudar"));
        $this->assertTrue($x->query("//*[@data-hero-barras]")->item(0)->hasAttribute("hidden"), "sin JavaScript los controles no se ven");
        $this->assertSame(1, Html::contar($x, "//script[contains(@src,'hero_carrusel.js')]"));
    }

    public function testLosTextosFijosVivenEnMarcaPhp(): void {
        $p = \Marca::todo()["portada"];
        foreach (["hero_carrusel", "hero_barra", "hero_pausar", "hero_reanudar"] as $k) {
            $this->assertNotSame("", trim((string)($p[$k] ?? "")), "falta portada.$k");
        }
        $this->assertStringContainsString("{n}", $p["hero_barra"]);
        $vista = (string)file_get_contents(BASE_PATH . "/app/Views/public/home.php");
        $this->assertStringNotContainsString("Pausar el cambio", $vista);
    }

    public function testEstilosDelHeroYDelAncla(): void {
        $css = (string)file_get_contents(BASE_PATH . "/assets/css/base.css");
        $this->assertMatchesRegularExpression('~\.hero-carrusel \{ display: none; \}~', $css, "oculta en móvil");
        $this->assertMatchesRegularExpression('~@media \(min-width: 992px\)\s*\{\s*\.hero-con-carrusel~', $css, "visible desde 992 px");
        $this->assertStringContainsString("transition: opacity .5s ease-out", $css, "fundido de 500 ms");
        $this->assertStringContainsString("hero-llena 5s linear", $css, "5 s por pieza");
        // la pausa tiene que ganarle en especificidad a la regla que anima la barrita activa, o no pausa nada
        $this->assertMatchesRegularExpression('~\.hero-carrusel\.hero-pausado \.hero-barra\.is-activa \.hero-barra-relleno[^{]*\{ animation-play-state: paused; \}~', $css);
        $this->assertStringContainsString("object-fit: contain", $css);
        $this->assertMatchesRegularExpression('~\.hero-barra \{[^}]*height: 44px~', $css, "zona táctil de 44 px");
        $this->assertMatchesRegularExpression('~@media \(prefers-reduced-motion: reduce\)\s*\{\s*\.hero-diapo \{ transition: none; \}~', $css);
        $this->assertDoesNotMatchRegularExpression('~\.hero-diapo[^{]*\{[^}]*(width|height)\s*:[^;]*;[^}]*transition[^}]*(width|height)~', $css, "no se anima tamaño");
    }

    public function testElScriptPausaYRespetaMovimientoReducido(): void {
        $js = (string)file_get_contents(BASE_PATH . "/assets/js/hero_carrusel.js");
        $this->assertStringContainsString("prefers-reduced-motion: reduce", $js);
        $this->assertStringContainsString("mouseenter", $js);
        $this->assertStringContainsString("focusin", $js);
        $this->assertStringContainsString("aria-pressed", $js);
        $this->assertStringNotContainsString("setInterval", $js, "el avance lo marca la animación de la barrita, que se pausa con ella");
    }
}
