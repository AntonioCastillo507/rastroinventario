<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\Datos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/**
 * Portada (P6): hero de una columna con buscador, franja de confianza oculta, categorías, «Recién llegadas»,
 * «Compra por marca» y banda final. Todos los textos salen de marca.php y las piezas, de la base de pruebas.
 */
final class PortadaTest extends BaseDatos {
    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
        Pantallas::render($this->db, "home"); // crea los datos de apoyo de las pantallas antes de las piezas de cada prueba
    }

    protected function tearDown(): void {
        \Marca::usarParaPruebas(null);
        $_GET = [];
        parent::tearDown();
    }

    private function home(?array $portada = null): array {
        if ($portada !== null) {
            $m = \Marca::todo();
            $m["portada"] = array_replace($m["portada"], $portada);
            \Marca::usarParaPruebas($m);
        }
        $html = Pantallas::render($this->db, "home");
        return [Html::xpath($html), $html];
    }

    private function auto(string $marca, string $modelo = "Modelo X", int $anio = 2020): int {
        $this->db->prepare("INSERT INTO autos (marca, modelo, anio) VALUES (?, ?, ?)")->execute([$marca, $modelo, $anio]);
        return (int)$this->db->lastInsertId();
    }

    private function pieza(string $nombre, int $autoId, int $cant = 5, int $activo = 1): int {
        $id = Datos::parte($this->db, 12.00, $cant, $nombre);
        $this->db->prepare("UPDATE partes SET auto_id = ?, activo = ? WHERE id = ?")->execute([$autoId, $activo, $id]);
        return $id;
    }

    // ── Hero ──

    public function testElHeroTieneUnH1UnBuscadorGetAlCatalogoYUnaEtiqueta(): void {
        [$x] = $this->home();
        $this->assertSame(1, Html::contar($x, "//h1"));
        $this->assertSame(1, Html::contar($x, "//section[@id='inicio']//form[@role='search' and @method='GET']"));
        $this->assertSame(1, Html::contar($x, "//section[@id='inicio']//form//input[@type='hidden' and @name='ver' and @value='catalogo']"));
        $this->assertSame(1, Html::contar($x, "//section[@id='inicio']//form//input[@type='search' and @name='q' and @id='hero-q']"));
        $this->assertSame(1, Html::contar($x, "//section[@id='inicio']//label[@for='hero-q']"), "el campo lleva su etiqueta");
    }

    public function testElHeroNoLlevaSelectorDeVehiculoNiOtrosCampos(): void {
        [$x] = $this->home();
        $this->assertSame(0, Html::contar($x, "//section[@id='inicio']//select"), "el selector por vehículo es una tarea aparte");
        $this->assertSame(0, Html::contar($x, "//section[@id='inicio']//details"));
        $this->assertSame(0, Html::contar($x, "//section[@id='inicio']//input[@type!='hidden' and @type!='search']"));
    }

    public function testElSubtituloYElTituloSalenDeLaMarca(): void {
        [$x] = $this->home(["titulo" => "Titulo de prueba", "titulo_resalte" => "resalte de prueba", "subtitulo" => "Subtitulo de prueba"]);
        $this->assertSame("Titulo de prueba resalte de prueba", preg_replace('/\s+/', " ", trim($x->query("//h1")->item(0)->textContent)));
        $this->assertSame("Subtitulo de prueba", trim($x->query("//section[@id='inicio']//p[contains(@class,'hero-sub')]")->item(0)->textContent));
    }

    public function testElSubtituloDeLaMarcaEsElAprobado(): void {
        $real = require BASE_PATH . "/app/Config/marca.php";
        $this->assertSame("Repuestos usados para tu carro en Panamá. Busca por nombre o por marca.", $real["portada"]["subtitulo"]);
    }

    public function testNoQuedaLaFranjaDeCifras(): void {
        [$x, $html] = $this->home();
        $this->assertSame(0, Html::contar($x, "//*[contains(@class,'stats-bar') or contains(@class,'stat-pill-num')]"));
        $this->assertStringNotContainsString("hero-section", $html);
    }

    // ── Franja de confianza ──

    public function testConLaListaVaciaNoSePintaLaFranjaNiSuContenedor(): void {
        [$x, $html] = $this->home();
        $this->assertSame([], \Marca::confianza());
        $this->assertSame(0, Html::contar($x, "//*[contains(@class,'portada-confianza') or contains(@class,'confianza-lista')]"));
        $this->assertStringNotContainsString("portada-confianza", $html);
    }

    public function testLaMarcaTrajoLaFranjaVacia(): void {
        $real = require BASE_PATH . "/app/Config/marca.php";
        $this->assertSame([], $real["portada"]["confianza"], "ninguna afirmación está confirmada todavía");
    }

    public function testConCuatroElementosSalenYEscapados(): void {
        $el = fn($n) => ["icono" => "x", "titulo" => "Titulo $n <b>", "texto" => "Texto $n & más"];
        [$x, $html] = $this->home(["confianza" => [$el(1), $el(2), $el(3), $el(4)]]);
        $this->assertSame(1, Html::contar($x, "//section[contains(@class,'portada-confianza')]"));
        $this->assertSame(4, Html::contar($x, "//section[contains(@class,'portada-confianza')]//li"));
        $this->assertStringContainsString("Titulo 1 &lt;b&gt;", $html);
        $this->assertStringNotContainsString("Titulo 1 <b>", $html);
        $this->assertStringContainsString("Texto 2 &amp; más", $html);
    }

    public function testConCincoElementosSoloSalenCuatro(): void {
        $lista = array_map(fn($n) => ["icono" => "", "titulo" => "Elemento $n", "texto" => ""], [1, 2, 3, 4, 5]);
        [$x] = $this->home(["confianza" => $lista]);
        $this->assertSame(4, Html::contar($x, "//section[contains(@class,'portada-confianza')]//li"));
        $this->assertSame(0, Html::contar($x, "//*[contains(text(),'Elemento 5')]"));
    }

    public function testLosElementosSinTituloNoSalenYSiNingunoLoTieneLaFranjaNoSePinta(): void {
        [$x] = $this->home(["confianza" => [["icono" => "x", "titulo" => "  ", "texto" => "Solo texto"], ["texto" => "Otro"]]]);
        $this->assertSame(0, Html::contar($x, "//*[contains(@class,'portada-confianza')]"));
        $this->assertSame([], \Marca::confianza());
    }

    public function testNingunaAfirmacionDeConfianzaEstaEscritaEnLaVista(): void {
        $vista = strtolower((string)file_get_contents(BASE_PATH . "/app/Views/public/home.php"));
        foreach (["garantía", "garantia", "devolución", "devolucion", "retiro", "envío", "envio", "whatsapp", "wa.me", "original"] as $p) {
            $this->assertStringNotContainsString($p, $vista, "«{$p}» no se escribe en la vista: sale de marca.php");
        }
    }

    // ── Categorías ──

    public function testLasCategoriasSonEnlacesAlCatalogoConSuConteo(): void {
        [$x] = $this->home();
        $filas = $this->db->query("SELECT s.id, s.nombre, COUNT(p.id) n FROM secciones s LEFT JOIN partes p ON p.seccion_id = s.id AND p.activo = 1 AND p.cantidad > 0 WHERE s.activo = 1 GROUP BY s.id ORDER BY s.nombre")->fetchAll();
        $this->assertNotEmpty($filas);
        $enlaces = $x->query("//ul[contains(@class,'cat-lista')]//a");
        $this->assertSame(count($filas), $enlaces->length);
        foreach ($filas as $i => $f) {
            $a = $enlaces->item($i);
            $this->assertSame(APP_URL . "/?ver=catalogo&seccion=" . $f["id"], $a->getAttribute("href"));
            $this->assertStringContainsString($f["nombre"], $a->textContent);
            $this->assertStringContainsString($f["n"] . " piezas", $a->textContent);
        }
    }

    // ── Recién llegadas ──

    public function testRecienLlegadasSonLasCuatroMasNuevasConStockYActivas(): void {
        $a = $this->auto("MarcaReciente");
        $ids = [];
        for ($i = 1; $i <= 5; $i++) $ids[] = $this->pieza("Reciente $i", $a);
        $agotada = $this->pieza("Reciente agotada", $a, 0);
        $inactiva = $this->pieza("Reciente inactiva", $a, 5, 0);
        [$x] = $this->home();
        $tarjetas = $x->query("//section[contains(@class,'portada-nuevas')]//article[contains(@class,'tarjeta-pieza')]");
        $this->assertSame(4, $tarjetas->length);
        $esperados = array_slice(array_reverse($ids), 0, 4);
        foreach ($esperados as $i => $id) $this->assertSame("parte-$id", $tarjetas->item($i)->getAttribute("id"), "orden: de la más nueva a la más vieja");
        $this->assertSame(0, Html::contar($x, "//article[@id='parte-$agotada' or @id='parte-$inactiva']"));
    }

    public function testRecienLlegadasUsanLaTarjetaDeProductoYElEnlaceVerTodoTrae(): void {
        $a = $this->auto("MarcaTarjeta");
        $this->pieza("Pieza tarjeta", $a);
        [$x] = $this->home();
        $t = $x->query("//section[contains(@class,'portada-nuevas')]//article[contains(@class,'tarjeta-pieza')]")->item(0);
        $q = new \DOMXPath($t->ownerDocument);
        foreach (["tarjeta-foto", "tarjeta-titulo", "tarjeta-compat", "stock-insignia", "precio", "tarjeta-agregar"] as $c) {
            $this->assertGreaterThanOrEqual(1, $q->query(".//*[contains(@class,'$c')]", $t)->length, "la tarjeta de S2 lleva $c");
        }
        $total = (int)$this->db->query("SELECT COUNT(*) FROM partes WHERE activo = 1 AND cantidad > 0")->fetchColumn();
        $ver = $x->query("//section[contains(@class,'portada-nuevas')]//a[contains(@class,'seccion-ver')]")->item(0);
        $this->assertSame(APP_URL . "/?ver=catalogo", $ver->getAttribute("href"));
        $this->assertStringContainsString("Ver las $total piezas", $ver->textContent);
    }

    // ── Compra por marca ──

    public function testCompraPorMarcaMuestraElConteoDePiezasALaVenta(): void {
        $a = $this->auto("Zeta Marca & Co");
        $b = $this->auto("Zeta Otra");
        $this->pieza("Z1", $a); $this->pieza("Z2", $a, 3); $this->pieza("Z3 agotada", $a, 0); $this->pieza("Z4 inactiva", $a, 5, 0);
        $this->pieza("O1", $b);
        $this->auto("Sin Piezas");
        [$x, $html] = $this->home();
        $chips = [];
        foreach ($x->query("//ul[contains(@class,'marca-lista')]//a") as $a2) {
            $nombre = trim($x->query(".//*[contains(@class,'marca-tarjeta-nombre')]", $a2)->item(0)->textContent);
            $n = trim($x->query(".//*[contains(concat(' ',@class,' '),' marca-tarjeta-n ')]", $a2)->item(0)->textContent);
            $chips[$nombre] = [$n, $a2->getAttribute("href")];
        }
        $this->assertSame("2 piezas", $chips["Zeta Marca & Co"][0], "solo cuentan las activas y con stock");
        $this->assertSame(APP_URL . "/?ver=catalogo&marca=Zeta%20Marca%20%26%20Co", $chips["Zeta Marca & Co"][1], "el nombre va codificado en la dirección");
        $this->assertSame("1 pieza", $chips["Zeta Otra"][0]);
        $this->assertArrayNotHasKey("Sin Piezas", $chips, "una marca sin piezas a la venta no sale");
        $this->assertStringContainsString("Zeta Marca &amp; Co", $html);
    }

    public function testLaMarcaMasPiezasVaPrimeroYElConteoCoincideConElCatalogo(): void {
        $pm = new \ParteModel();
        $filas = $pm->getMarcasConConteo();
        $this->assertNotEmpty($filas);
        $previo = PHP_INT_MAX;
        foreach ($filas as $f) {
            $this->assertLessThanOrEqual($previo, (int)$f["total"]);
            $previo = (int)$f["total"];
            $this->assertSame((int)$f["total"], $pm->contarPublic("", 0, $f["marca"]), "el conteo de {$f["marca"]} es el del catálogo al pulsar el enlace");
        }
    }

    public function testElNombreDeMarcaConComillasNoRompeLaConsultaNiElHtml(): void {
        $a = $this->auto("O'Brien \"X\" <i>");
        $this->pieza("Pieza rara", $a);
        [$x, $html] = $this->home();
        $this->assertStringNotContainsString("<i>", str_replace(["<i aria-hidden", "</i>"], "", $html), "el nombre sale escapado");
        $this->assertGreaterThanOrEqual(1, Html::contar($x, "//ul[contains(@class,'marca-lista')]//a[contains(@href,'marca=O%27Brien')]"));
    }

    public function testSinPiezasALaVentaNoSePintaLaSeccionDeMarcas(): void {
        $this->db->exec("UPDATE partes SET activo = 0");
        [$x] = $this->home();
        $this->assertSame(0, Html::contar($x, "//ul[contains(@class,'marca-lista')]"));
        $this->assertSame(0, Html::contar($x, "//section[@aria-labelledby='t-marcas']"));
        $this->assertSame(1, Html::contar($x, "//section[contains(@class,'portada-nuevas')]"), "la sección de novedades sigue, con su aviso");
        $this->assertSame(0, Html::contar($x, "//section[contains(@class,'portada-nuevas')]//*[contains(@class,'rejilla-piezas')]"), "sin piezas no hay rejilla vacía");
        $this->assertStringContainsString("No hay piezas disponibles", $x->query("//section[contains(@class,'portada-nuevas')]")->item(0)->textContent);
    }

    // ── Banda final ──

    public function testLaBandaFinalLlevaAContactoSinWhatsApp(): void {
        [$x, $html] = $this->home();
        $banda = $x->query("//section[contains(@class,'portada-cta')]")->item(0);
        $q = new \DOMXPath($banda->ownerDocument);
        $enlaces = $q->query(".//a", $banda);
        $this->assertSame(1, $enlaces->length, "un solo botón");
        $this->assertSame(APP_URL . "/?action=contacto", $enlaces->item(0)->getAttribute("href"));
        $this->assertStringNotContainsString("wa.me", $banda->ownerDocument->saveHTML($banda));
        $this->assertStringNotContainsString("target=", $banda->ownerDocument->saveHTML($banda), "abre en la misma pestaña");
        $this->assertSame(1, $q->query(".//*[contains(@class,'btn-gold')]", $banda)->length);
    }

    // ── Estructura general ──

    public function testOrdenDeLasSeccionesYUnSoloBotonPrincipalPorBloque(): void {
        [$x] = $this->home();
        $ids = [];
        foreach ($x->query("//main//section") as $s) $ids[] = $s->getAttribute("id") ?: $s->getAttribute("aria-labelledby");
        $this->assertSame(["inicio", "t-cats", "t-nuevas", "t-marcas", "t-cta"], $ids);
        $this->assertSame(1, Html::contar($x, "//section[@id='inicio']//*[contains(@class,'btn-gold')]"));
    }

    public function testLasSeccionesDelPieYLasMarcasTienenSuTituloH2(): void {
        [$x] = $this->home();
        $this->assertSame(4, Html::contar($x, "//main//section/descendant::h2[@id]"));
        $this->assertSame(1, Html::contar($x, "//main"));
    }
}
