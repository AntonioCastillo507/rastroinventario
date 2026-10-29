<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\Datos;
use Tests\Support\Html;
use Tests\Support\Imagenes;
use Tests\Support\Pantallas;

/**
 * S7b-A: carrusel de piezas del hero, cuadro «Por qué elegirnos» y el ancla #comentarios.
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

        $puntos = $x->query("//*[@data-hero-carrusel]//button[@data-hero-punto]");
        $this->assertSame(3, $puntos->length, "un punto por diapositiva");
        foreach ($puntos as $i => $b) {
            $this->assertSame("Ver la pieza " . ($i + 1) . " de 3", $b->getAttribute("aria-label"));
        }
        $this->assertSame(\Marca::get("portada.hero_anterior"), $x->query("//button[@data-hero-anterior]")->item(0)->getAttribute("aria-label"));
        $this->assertSame(\Marca::get("portada.hero_siguiente"), $x->query("//button[@data-hero-siguiente]")->item(0)->getAttribute("aria-label"));
        $this->assertSame(0, Html::contar($x, "//*[@data-hero-pausa or @data-hero-barra]"), "sin botón de pausa ni barritas");
        $this->assertSame(3, Html::contar($x, "//a[@data-hero-diapo]//*[contains(@class,'hero-ver')]"), "«Ver detalle →» en cada leyenda");
        $this->assertTrue($x->query("//*[@data-hero-puntos]")->item(0)->hasAttribute("hidden"), "sin JavaScript los controles no se ven");
        $this->assertTrue($x->query("//button[@data-hero-anterior]")->item(0)->hasAttribute("hidden"));
        $this->assertSame(1, Html::contar($x, "//script[contains(@src,'hero_carrusel.js')]"));
    }

    public function testLosTextosFijosVivenEnMarcaPhp(): void {
        $p = \Marca::todo()["portada"];
        foreach (["hero_carrusel", "hero_barra", "hero_anterior", "hero_siguiente", "hero_ver", "porque_insignia", "porque_titulo", "porque_subtitulo", "porque_texto", "porque_boton"] as $k) {
            $this->assertNotSame("", trim((string)($p[$k] ?? "")), "falta portada.$k");
        }
        $this->assertStringContainsString("{n}", $p["hero_barra"]);
        $vista = (string)file_get_contents(BASE_PATH . "/app/Views/public/home.php");
        $this->assertStringNotContainsString("Pieza siguiente", $vista);
        $this->assertArrayNotHasKey("hero_pausar", $p);
        $this->assertArrayNotHasKey("hero_reanudar", $p);
        $this->assertStringNotContainsString("Conoce más sobre nosotros", $vista);
    }

    public function testCuadroPorQueElegirnosConFoto(): void {
        $m = \Marca::todo();
        $m["nosotros"]["imagen"] = "assets/img/portada/hero-movil.webp"; // cualquier archivo que exista en esta copia
        if (!is_file(BASE_PATH . "/" . $m["nosotros"]["imagen"])) $m["nosotros"]["imagen"] = "assets/css/base.css"; // solo para que el archivo exista
        \Marca::usarParaPruebas($m);
        $x = $this->portada();
        $this->assertSame(1, Html::contar($x, "//section[contains(@class,'portada-porque')]//div[contains(@class,'porque-tarjeta')]"));
        $this->assertSame(1, Html::contar($x, "//section[contains(@class,'portada-porque')]//h2"));
        $this->assertSame(1, Html::contar($x, "//section[contains(@class,'portada-porque')]//*[contains(@class,'porque-insignia')]"));
        $this->assertSame(1, Html::contar($x, "//section[contains(@class,'portada-porque')]//*[contains(@class,'porque-sub')]"));
        $enlace = $x->query("//section[contains(@class,'portada-porque')]//a[contains(@class,'btn-gold')]");
        $this->assertSame(1, $enlace->length);
        $this->assertStringEndsWith("/?action=nosotros", $enlace->item(0)->getAttribute("href"));
        $this->assertSame(\Marca::get("portada.porque_boton"), trim($enlace->item(0)->textContent));
        $foto = $x->query("//section[contains(@class,'portada-porque')]//div[contains(@class,'porque-foto')]/img");
        $this->assertSame(1, $foto->length);
        $this->assertSame("lazy", $foto->item(0)->getAttribute("loading"));
        // la foto va antes que el texto: en móvil queda arriba
        $this->assertSame("porque-foto", explode(" ", $x->query("//div[contains(@class,'porque-tarjeta')]/div")->item(0)->getAttribute("class"))[0]);
    }

    public function testCuadroPorQueElegirnosSinFotoNoDejaImagenRota(): void {
        $m = \Marca::todo();
        $m["nosotros"]["imagen"] = "";
        \Marca::usarParaPruebas($m);
        $x = $this->portada();
        $this->assertSame(1, Html::contar($x, "//div[contains(@class,'porque-tarjeta') and contains(@class,'porque-sin-foto')]"));
        $this->assertSame(0, Html::contar($x, "//div[contains(@class,'porque-tarjeta')]//img"));
    }

    public function testEstilosDelHeroYDelAncla(): void {
        $css = (string)file_get_contents(BASE_PATH . "/assets/css/base.css");
        $this->assertMatchesRegularExpression('~#comentarios\s*\{\s*scroll-margin-top:\s*6rem;~', $css, "el ancla de comentarios queda bajo la barra fija");
        $this->assertMatchesRegularExpression('~\.hero-carrusel \{ display: none; \}~', $css, "oculta en móvil");
        $this->assertMatchesRegularExpression('~@media \(min-width: 992px\)\s*\{\s*\.hero-con-carrusel~', $css, "visible desde 992 px");
        $this->assertMatchesRegularExpression('~@media \(min-width: 1200px\) \{ \.hero-con-carrusel \{ grid-template-columns: minmax\(0, 1fr\) 520px~', $css, "tarjeta de 520 px");
        $this->assertStringContainsString("transition: opacity .5s ease-out", $css, "fundido de 500 ms");
        $this->assertMatchesRegularExpression('~\.hero-foto img \{[^}]*object-fit: cover~', $css, "la foto del hero recorta");
        $this->assertMatchesRegularExpression('~\.hero-flecha \{[^}]*width: 44px; height: 44px~', $css);
        $this->assertStringNotContainsString("hero-barra", $css);
        $this->assertStringNotContainsString("hero-pausa", $css);
        $this->assertMatchesRegularExpression('~\.hero-punto-marca \{[^}]*8px~', $css);
        $this->assertMatchesRegularExpression('~@media \(prefers-reduced-motion: reduce\)\s*\{\s*\.hero-diapo \{ transition: none; \}~', $css);
        $this->assertStringContainsString(".portada-seccion ~ .portada-seccion { border-top: 1px solid var(--hairline); }", $css, "divisor entre todas las secciones de la portada");
    }

    public function testElScriptPausaYSeDetieneTrasUnaVuelta(): void {
        $js = (string)file_get_contents(BASE_PATH . "/assets/js/hero_carrusel.js");
        foreach (["prefers-reduced-motion: reduce", "mouseenter", "focusin", "touchstart", "vueltaCompleta", "data-hero-anterior"] as $t) $this->assertStringContainsString($t, $js, $t);
        $this->assertStringNotContainsString("aria-pressed", $js, "sin botón de pausa");
        $this->assertStringContainsString("var INTERVALO = 5000;", $js);
    }

    public function testConLaSeccionExcluidaNoSaleNingunaDeSusPiezasYConLaListaVaciaSi(): void {
        $this->assertSame(["Motor y transmisión"], \Marca::get("portada.hero_excluir_secciones"), "la lista editable vive en marca.php");
        $nombre = "Sec excluida " . bin2hex(random_bytes(3));
        $this->db->prepare("INSERT INTO secciones (nombre) VALUES (?)")->execute([$nombre]);
        $idSec = (int)$this->db->lastInsertId();
        $deSec = [];
        for ($i = 1; $i <= 3; $i++) { $id = $this->pieza("Excluible $i"); $this->db->prepare("UPDATE partes SET seccion_id = ? WHERE id = ?")->execute([$idSec, $id]); $deSec[] = $id; }
        $libre = $this->pieza("Sin sección");
        for ($v = 0; $v < 8; $v++) {
            $ids = array_map(fn($p) => (int)$p["id"], (new \ParteModel())->paraHero(4, [$nombre]));
            $this->assertSame([$libre], $ids, "solo sale la pieza que no es de la sección excluida");
        }
        $vistos = [];
        for ($v = 0; $v < 20; $v++) foreach ((new \ParteModel())->paraHero(4, []) as $p) $vistos[(int)$p["id"]] = true;
        $this->assertNotEmpty(array_intersect(array_keys($vistos), $deSec), "con la lista vacía sí pueden salir");
        $m = \Marca::todo(); $m["portada"]["hero_excluir_secciones"] = [$nombre]; \Marca::usarParaPruebas($m);
        $this->assertSame(0, Html::contar($this->portada(), "//*[@data-hero-carrusel]"), "menos de 2 piezas tras excluir: sin tarjeta");
        $m["portada"]["hero_excluir_secciones"] = []; \Marca::usarParaPruebas($m);
        $this->assertSame(1, Html::contar($this->portada(), "//*[@data-hero-carrusel]"));
    }
}
