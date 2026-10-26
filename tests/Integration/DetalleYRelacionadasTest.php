<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\Datos;
use Tests\Support\Html;

/**
 * P4, detalle de pieza (estructura A): un solo botón principal "Añadir al carrito" y "Comprar ahora" secundario, galería
 * reservada (1:1, miniaturas ocultas con una foto, "Ampliar" de 44 px sin función), cantidad con − y +, barra de compra
 * en teléfono y estante de piezas relacionadas (misma sección o mismo auto).
 */
final class DetalleYRelacionadasTest extends BaseDatos {
    protected function setUp(): void {
        parent::setUp();
        $_SERVER["REQUEST_METHOD"] = "GET";
        $_SERVER["REQUEST_URI"] = "/rastroinventario/?action=detalle&id=1";
    }

    protected function tearDown(): void {
        $_GET = [];
        unset($_SERVER["REQUEST_METHOD"], $_SERVER["REQUEST_URI"]);
        parent::tearDown();
    }

    private function auto(string $marca): int {
        $this->db->prepare("INSERT INTO autos (marca, modelo, anio) VALUES (?, 'Modelo R', 2019)")->execute([$marca]);
        return (int)$this->db->lastInsertId();
    }

    private function seccion(string $nombre): int {
        $this->db->prepare("INSERT INTO secciones (nombre) VALUES (?)")->execute([$nombre]);
        return (int)$this->db->lastInsertId();
    }

    private function pieza(string $nombre, int $stock = 10, ?int $auto = null, ?int $seccion = null, bool $activa = true): int {
        $id = Datos::parte($this->db, 20.00, $stock, $nombre);
        $this->db->prepare("UPDATE partes SET auto_id = ?, seccion_id = ?, activo = ? WHERE id = ?")->execute([$auto, $seccion, $activa ? 1 : 0, $id]);
        return $id;
    }

    private function detalle(int $id, array $get = []): \DOMXPath {
        $_GET = ["id" => (string)$id] + $get;
        ob_start();
        try { (new \PublicController())->detalle(); } finally { $html = (string)ob_get_clean(); }
        return Html::xpath($html);
    }

    private function nombres(array $filas): array { return array_map(fn($f) => $f["nombre"], $filas); }

    // ── Consulta de relacionadas ──

    public function testRelacionadasPrimeroLasQueCoincidenEnAutoYSeccionLuegoElAutoLuegoLaSeccion(): void {
        $a = $this->auto("RelAuto"); $s = $this->seccion("RelSec");
        $otroA = $this->auto("OtroAuto"); $otraS = $this->seccion("OtraSec");
        $base = $this->pieza("R Base", 10, $a, $s);
        $this->pieza("R Solo seccion", 10, $otroA, $s);
        $this->pieza("R Auto y seccion", 10, $a, $s);
        $this->pieza("R Solo auto", 10, $a, $otraS);
        $this->pieza("R Nada que ver", 10, $otroA, $otraS);
        $r = (new \ParteModel())->findRelacionadas($base, $s, $a, 10);
        $this->assertSame(["R Auto y seccion", "R Solo auto", "R Solo seccion"], $this->nombres($r));
        $this->assertArrayHasKey("marca", $r[0], "trae los mismos datos que el catálogo para la tarjeta");
    }

    public function testRelacionadasNoIncluyeLaPropiaNiInactivasNiAgotadas(): void {
        $s = $this->seccion("RelSec2");
        $base = $this->pieza("R2 Base", 10, null, $s);
        $this->pieza("R2 Inactiva", 10, null, $s, false);
        $this->pieza("R2 Agotada", 0, null, $s);
        $buena = $this->pieza("R2 Buena", 3, null, $s);
        $r = (new \ParteModel())->findRelacionadas($base, $s, null, 10);
        $this->assertSame([$buena], array_map(fn($f) => (int)$f["id"], $r));
    }

    public function testRelacionadasRespetaElLimiteYDesempataPorLaMasReciente(): void {
        $s = $this->seccion("RelSec3");
        $base = $this->pieza("R3 Base", 10, null, $s);
        $ids = [];
        foreach (range(1, 14) as $i) $ids[] = $this->pieza("R3 Pieza $i", 10, null, $s);
        $r = (new \ParteModel())->findRelacionadas($base, $s, null, 4);
        $this->assertCount(4, $r);
        $this->assertSame(array_reverse(array_slice($ids, 10)), array_map(fn($f) => (int)$f["id"], $r), "las 4 más recientes, de la última a la primera");
        $this->assertCount(1, (new \ParteModel())->findRelacionadas($base, $s, null, 0), "un límite absurdo se corrige a 1");
        $this->assertCount(12, (new \ParteModel())->findRelacionadas($base, $s, null, 500), "y uno enorme se limita a 12 aunque haya 14");
    }

    public function testSinSeccionNiAutoNoHayRelacionadas(): void {
        $base = $this->pieza("R4 Base", 10);
        $this->pieza("R4 Otra", 10);
        $this->assertSame([], (new \ParteModel())->findRelacionadas($base, null, null, 4));
    }

    // ── Pantalla ──

    public function testLaEstructuraTieneUnSoloH1UnBotonPrincipalYComprarAhoraSecundario(): void {
        $id = $this->pieza("D Estructura", 8);
        $x = $this->detalle($id);
        $this->assertSame(1, $x->query("//h1")->length);
        $this->assertSame("D Estructura", trim($x->query("//h1")->item(0)->textContent));
        $principal = $x->query("//main//form[@id='form-agregar']//button[normalize-space()='Añadir al carrito']");
        $this->assertSame(1, $principal->length);
        $this->assertStringContainsString("btn-gold", $principal->item(0)->getAttribute("class"));
        $comprar = $x->query("//a[@data-comprar-ahora]")->item(0);
        $this->assertSame("Comprar ahora", trim($comprar->textContent));
        $this->assertStringContainsString("btn-outline-secondary", $comprar->getAttribute("class"));
        $this->assertStringNotContainsString("btn-gold", $comprar->getAttribute("class"), "Comprar ahora no es el botón principal");
        $this->assertSame(APP_URL . "/?action=comprar&id=$id", $comprar->getAttribute("href"));
        $f = $x->query("//form[@id='form-agregar']")->item(0);
        $this->assertSame("POST", strtoupper($f->getAttribute("method")));
        $this->assertSame(1, $x->query(".//input[@name='csrf_token']", $f)->length);
        $this->assertSame("/rastroinventario/?action=detalle&id=1", $x->query(".//input[@name='volver']/@value", $f)->item(0)->nodeValue);
        $this->assertSame(1, $x->query("//form[@data-agregar-carrito][@id='form-agregar']")->length, "usa el mismo añadir sin recargar que las tarjetas");
    }

    public function testElPrecioYElStockSeMuestranConElTokenYConTexto(): void {
        $id = $this->pieza("D Precio", 3);
        $x = $this->detalle($id);
        $p = $x->query("//*[contains(@class,'detail-price')]")->item(0);
        $this->assertSame('$20.00', trim($p->textContent));
        $this->assertStringContainsString("num", $p->getAttribute("class"));
        $this->assertSame("Quedan 3", trim($x->query("//main//span[contains(@class,'stock-insignia')]")->item(0)->textContent));
    }

    public function testLaGaleriaReservadaTieneSuEsqueleto(): void {
        $id = $this->pieza("D Galeria", 8);
        $x = $this->detalle($id);
        $this->assertSame(1, $x->query("//figure[contains(@class,'galeria')][@data-galeria]")->length);
        $this->assertSame(1, $x->query("//figure//div[contains(@class,'galeria-principal')]/span[contains(@class,'foto-baldosa')]")->length, "figura 1:1 en su baldosa");
        $b = $x->query("//figure//button[contains(@class,'galeria-ampliar')]")->item(0);
        $this->assertSame("button", $b->getAttribute("type"));
        $this->assertStringContainsString("Ampliar", $b->getAttribute("aria-label"));
        $this->assertTrue($b->hasAttribute("disabled"), "sin función todavía");
        $ul = $x->query("//figure//ul[contains(@class,'galeria-miniaturas')]")->item(0);
        $this->assertSame("list", $ul->getAttribute("role"));
        $this->assertTrue($ul->hasAttribute("hidden"), "con una sola foto la tira se oculta");
        $m = $x->query(".//li/button", $ul);
        $this->assertSame(1, $m->length);
        $this->assertSame("Ver imagen 1 de 1", $m->item(0)->getAttribute("aria-label"));
        $this->assertSame("true", $m->item(0)->getAttribute("aria-current"));
        $css = (string)file_get_contents(BASE_PATH . "/assets/css/base.css");
        $this->assertMatchesRegularExpression('/\.galeria-ampliar \{[^}]*width: 44px; height: 44px/', $css);
        $this->assertMatchesRegularExpression('/\.galeria-miniaturas\[hidden\] \{ display: none; \}/', $css);
        $this->assertMatchesRegularExpression('/\.foto-64 \{ width: 64px; \}/', $css, "miniaturas de 64 px");
        $this->assertSame(0, $x->query("//dialog | //*[@data-zoom]")->length, "no hay zoom ni galería múltiple todavía");
    }

    public function testLaCantidadTieneBotonesMenosYMasYSoloSiHayMasDeUna(): void {
        $id = $this->pieza("D Cantidad", 8);
        $x = $this->detalle($id);
        $fila = $x->query("//*[@data-cantidad][@data-max='8']")->item(0);
        $this->assertNotNull($fila);
        $this->assertSame("Disminuir cantidad", $x->query(".//button[@data-cantidad-menos]/@aria-label", $fila)->item(0)->nodeValue);
        $this->assertSame("Aumentar cantidad", $x->query(".//button[@data-cantidad-mas]/@aria-label", $fila)->item(0)->nodeValue);
        $campo = $x->query(".//input[@data-cantidad-campo]", $fila)->item(0);
        $this->assertSame("form-agregar", $campo->getAttribute("form"), "el campo viaja con el formulario de añadir");
        $this->assertSame("1", $campo->getAttribute("min"));
        $this->assertSame("8", $campo->getAttribute("max"));
        $this->assertSame(1, $x->query("//label[@for='" . $campo->getAttribute("id") . "']")->length);
        $css = (string)file_get_contents(BASE_PATH . "/assets/css/base.css");
        $this->assertMatchesRegularExpression('/\.cantidad-control button \{[^}]*width: 44px; min-height: 44px/', $css);

        $unica = $this->pieza("D Unica", 1);
        $x = $this->detalle($unica);
        $this->assertSame(0, $x->query("//*[@data-cantidad]")->length, "con una unidad no hay selector");
        $this->assertSame(1, $x->query("//form[@id='form-agregar']")->length, "pero sí se puede añadir");
    }

    public function testLaBarraDeCompraDeTelefonoUsaElMismoFormulario(): void {
        $id = $this->pieza("D Barra", 8);
        $x = $this->detalle($id);
        $barra = $x->query("//div[contains(@class,'barra-compra')]")->item(0);
        $this->assertNotNull($barra);
        $b = $x->query(".//button[@type='submit']", $barra)->item(0);
        $this->assertSame("form-agregar", $b->getAttribute("form"));
        $this->assertSame("Añadir al carrito", trim($b->textContent));
        $this->assertSame('$20.00', trim($x->query(".//span[contains(@class,'precio')]", $barra)->item(0)->textContent));
        $css = (string)file_get_contents(BASE_PATH . "/assets/css/base.css");
        $this->assertMatchesRegularExpression('/\.barra-compra \{ display: none; \}/', $css, "solo en teléfono");
        $this->assertMatchesRegularExpression('/max-width: 767\.98px\) \{\s*\.detalle-agregar \{ display: none; \}/', $css, "en teléfono hay un solo botón principal a la vista");
        $this->assertMatchesRegularExpression('/\.barra-compra \{[^}]*position: fixed;[^}]*bottom: 0;[^}]*height: 64px/', $css);
    }

    public function testUnaPiezaAgotadaNoOfreceComprarNiTieneBarra(): void {
        $id = $this->pieza("D Agotada", 0);
        $x = $this->detalle($id);
        $this->assertSame(0, $x->query("//form[@id='form-agregar'] | //a[@data-comprar-ahora] | //div[contains(@class,'barra-compra')]")->length);
        $this->assertSame(1, $x->query("//*[@role='alert'][contains(.,'Pieza agotada')]")->length);
        $this->assertSame("Agotado", trim($x->query("//main//span[contains(@class,'stock-insignia')]")->item(0)->textContent));
    }

    public function testElEstanteDeRelacionadasUsaLaTarjetaYNoAparecesinPiezas(): void {
        $a = $this->auto("DetAuto"); $s = $this->seccion("DetSec");
        $id = $this->pieza("D Principal", 8, $a, $s);
        $x = $this->detalle($id);
        $this->assertSame(0, $x->query("//section[contains(@class,'detalle-relacionadas')]")->length, "sin relacionadas no hay estante");

        $this->pieza("D Rel 1", 8, $a, $s);
        $this->pieza("D Rel 2", 8, null, $s);
        $x = $this->detalle($id);
        $sec = $x->query("//section[contains(@class,'detalle-relacionadas')]")->item(0);
        $this->assertNotNull($sec);
        $this->assertSame("Piezas relacionadas", trim($x->query(".//h2", $sec)->item(0)->textContent));
        $this->assertSame(["D Rel 1", "D Rel 2"], array_map(fn($e) => trim($e->textContent), iterator_to_array($x->query(".//article[contains(@class,'tarjeta-pieza')]//h3/a", $sec))));
        $this->assertSame(0, $x->query(".//a[contains(@href,'id=$id')]", $sec)->length, "la pieza que se mira no se ofrece a sí misma");
        $this->assertSame(1, $x->query("//h1")->length);
    }

    public function testElEstanteTrae4ComoMaximo(): void {
        $s = $this->seccion("DetSec2");
        $id = $this->pieza("D Principal 2", 8, null, $s);
        foreach (range(1, 7) as $i) $this->pieza("D Many $i", 8, null, $s);
        $x = $this->detalle($id);
        $this->assertSame(4, $x->query("//section[contains(@class,'detalle-relacionadas')]//article")->length);
    }

    public function testComprarAhoraLlevaLaCantidadComoValorInicialDelFormularioDeCompra(): void {
        $id = $this->pieza("D Compra", 8);
        $_GET = ["id" => (string)$id, "cantidad" => "3"];
        ob_start();
        try { (new \PublicController())->comprar(); } finally { $html = (string)ob_get_clean(); }
        $x = Html::xpath($html);
        $this->assertSame("3", $x->query("//input[@name='cantidad']/@value")->item(0)->nodeValue);
        foreach (["99" => "8", "0" => "1", "-2" => "1", "abc" => "1", "2.5" => "1"] as $pedida => $esperada) {
            $_GET = ["id" => (string)$id, "cantidad" => (string)$pedida]; // las claves numéricas del arreglo pasan a entero
            ob_start();
            try { (new \PublicController())->comprar(); } finally { $html = (string)ob_get_clean(); }
            $this->assertSame($esperada, Html::xpath($html)->query("//input[@name='cantidad']/@value")->item(0)->nodeValue, "cantidad pedida $pedida");
        }
    }
}
