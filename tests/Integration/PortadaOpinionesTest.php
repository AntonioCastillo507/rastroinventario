<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\Datos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/** Sección «Opiniones de nuestros clientes» de la portada: qué reseñas salen, estructura, textos de marca.php y los scripts de datos demo */
final class PortadaOpinionesTest extends BaseDatos {
    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
    }

    protected function tearDown(): void {
        \Marca::usarParaPruebas(null);
        parent::tearDown();
    }

    private function resena(int $parte, string $nombre, string $texto, ?int $rating = 5, int $publicado = 1, string $fecha = "2026-10-01 10:00:00", int $autoPub = 1): int {
        $this->db->prepare("INSERT INTO comentarios (parte_id, nombre_visitante, comentario, rating, auto_pub, publicado, creado_en) VALUES (?, ?, ?, ?, ?, ?, ?)")
                 ->execute([$parte, $nombre, $texto, $rating, $autoPub, $publicado, $fecha]);
        return (int)$this->db->lastInsertId();
    }

    /** Pantallas crea sus propios datos (con una reseña) la primera vez que se pinta: se crean ya y se ocultan todas las reseñas */
    private function sinResenas(): void {
        Pantallas::render($this->db, "home");
        $this->db->exec("UPDATE comentarios SET publicado = 0");
    }

    private function home(): \DOMXPath {
        return Html::xpath(Pantallas::render($this->db, "home"));
    }

    private function nombres(): array {
        return array_column((new \ComentarioModel())->paraPortada(50), "nombre_visitante");
    }

    // ── La consulta ──
    public function testSoloSalenLasResenasVisiblesDePiezasActivasYLaMasRecientePrimero(): void {
        $p = Datos::parte($this->db, 10.00, 5, "Pieza Op Visible");
        $oculta = Datos::parte($this->db, 10.00, 5, "Pieza Op Oculta");
        $this->resena($p, "Zq Vieja", "Primera opinión", 4, 1, "2026-09-01 10:00:00");
        $this->resena($p, "Zq Nueva", "Segunda opinión", 5, 1, "2026-10-02 10:00:00");
        $this->resena($p, "Zq Oculta", "Esta se ocultó", 5, 0, "2026-10-03 10:00:00");
        $this->resena($oculta, "Zq PiezaInactiva", "Pieza desactivada", 5, 1, "2026-10-04 10:00:00");
        $this->db->prepare("UPDATE partes SET activo = 0 WHERE id = ?")->execute([$oculta]);
        $r = array_values(array_filter($this->nombres(), fn($n) => str_starts_with($n, "Zq ")));
        $this->assertSame(["Zq Nueva", "Zq Vieja"], $r);
    }

    public function testUnaResenaDeUnaPiezaQueNoExisteNoSale(): void {
        $p = Datos::parte($this->db, 10.00, 5, "Pieza Op Borrada");
        $this->db->exec("SET FOREIGN_KEY_CHECKS = 0");
        try {
            $this->resena(999999, "Zq Huerfana", "Sin pieza");
            $this->resena($p, "Zq ConPieza", "Con pieza");
        } finally { $this->db->exec("SET FOREIGN_KEY_CHECKS = 1"); }
        $r = array_values(array_filter($this->nombres(), fn($n) => str_starts_with($n, "Zq ")));
        $this->assertSame(["Zq ConPieza"], $r);
    }

    public function testElLimiteSeRespetaYUnTextoVacioNoSale(): void {
        $p = Datos::parte($this->db, 10.00, 5, "Pieza Op Limite");
        for ($i = 1; $i <= 5; $i++) $this->resena($p, "Zq $i", "Texto $i", 5, 1, "2026-10-0$i 10:00:00");
        $this->resena($p, "Zq Vacia", "   ", 5, 1, "2026-10-09 10:00:00");
        $r = (new \ComentarioModel())->paraPortada(3);
        $this->assertCount(3, $r);
        $this->assertSame(["Zq 5", "Zq 4", "Zq 3"], array_column($r, "nombre_visitante"));
    }

    // ── La vista ──
    public function testSinResenasVisiblesLaSeccionNoSale(): void {
        $this->sinResenas();
        $x = $this->home();
        $this->assertSame(0, Html::contar($x, "//section[contains(@class,'portada-opiniones')]"));
        $this->assertSame(0, Html::contar($x, "//script[contains(@src,'opiniones_carrusel')]"));
    }

    public function testLaSeccionVaEntrePorQueElegirnosYElLlamadoAContactar(): void {
        $this->sinResenas();
        $this->resena(Datos::parte($this->db, 10.00, 5, "Pieza Op Orden"), "Zq Orden", "Texto");
        $x = $this->home();
        $ids = [];
        foreach ($x->query("//main//section[contains(@class,'portada-seccion')]") as $s) $ids[] = $s->getAttribute("aria-labelledby");
        $i = array_search("t-opiniones", $ids, true);
        $this->assertNotFalse($i);
        $this->assertSame("t-porque", $ids[$i - 1], "después de Por qué elegirnos");
        $this->assertSame("t-cta", $ids[$i + 1], "antes de ¿No encuentras tu pieza?");
    }

    public function testLaTarjetaLlevaAvatarNombreEstrellasTextoYEnlaceALaPieza(): void {
        $this->sinResenas();
        $p = Datos::parte($this->db, 10.00, 5, "Pieza Op <Tarjeta>");
        $this->resena($p, "ángel <b>X</b>", "Muy buena & barata", 4);
        $x = $this->home();
        $li = $x->query("//section[contains(@class,'portada-opiniones')]//li[contains(@class,'opinion')]")->item(0);
        $this->assertNotNull($li);
        $this->assertSame("Á", trim($x->query(".//*[contains(@class,'opinion-avatar')]", $li)->item(0)->textContent), "inicial en mayúscula");
        $this->assertSame("true", $x->query(".//*[contains(@class,'opinion-avatar')]", $li)->item(0)->getAttribute("aria-hidden"));
        $this->assertSame("ángel <b>X</b>", trim($x->query(".//*[contains(@class,'opinion-nombre')]", $li)->item(0)->textContent), "el nombre se escapa");
        $est = $x->query(".//span[contains(@class,'estrellas')]", $li)->item(0);
        $this->assertSame("4 de 5 estrellas", $est->getAttribute("aria-label"));
        $this->assertSame("img", $est->getAttribute("role"));
        $this->assertSame(4, $x->query(".//i[contains(@class,'bi-star-fill')]", $est)->length);
        $this->assertSame(1, $x->query(".//i[not(contains(@class,'bi-star-fill'))]", $est)->length);
        $this->assertSame("Muy buena & barata", trim($x->query(".//p[contains(@class,'opinion-texto')]", $li)->item(0)->textContent));
        $a = $x->query(".//a[contains(@class,'opinion-pieza')]", $li)->item(0);
        $this->assertSame(APP_URL . "/?action=detalle&id=$p#comentarios", $a->getAttribute("href"));
        $this->assertSame("Sobre Pieza Op <Tarjeta>", trim($a->textContent));
    }

    public function testElCarruselVaDentroDelContenedorYLasFlechasSonChevrones(): void {
        $this->resena(Datos::parte($this->db, 10.00, 5, "Pieza Op Flechas"), "Zq Flechas", "Texto");
        $x = $this->home();
        $base = "//section[contains(@class,'portada-opiniones')]/div[contains(@class,'container-xl')]";
        $this->assertSame(1, Html::contar($x, "$base/div[contains(@class,'opiniones-cabeza')]/h2[@id='t-opiniones']"));
        $this->assertSame(1, Html::contar($x, "$base/div[contains(@class,'opiniones-cabeza')]/p[contains(@class,'opiniones-sub')]"));
        $this->assertSame(1, Html::contar($x, "$base/div[@data-opiniones-carrusel]"));
        $v = $x->query("$base/div[@data-opiniones-carrusel]/div[@data-opiniones-ventana]")->item(0);
        $this->assertNotNull($v);
        $this->assertSame("0", $v->getAttribute("tabindex"), "la ventana recibe el foco del teclado");
        $this->assertSame("region", $v->getAttribute("role"));
        $this->assertNotSame("", $v->getAttribute("aria-label"));
        foreach (["anterior", "siguiente"] as $b) {
            $btn = $x->query("$base//button[@data-opiniones-$b and contains(@class,'opiniones-flecha')]")->item(0);
            $this->assertNotNull($btn, $b);
            $this->assertSame("button", $btn->getAttribute("type"));
            $this->assertNotSame("", $btn->getAttribute("aria-label"));
        }
        $this->assertSame(1, Html::contar($x, "$base//div[@data-opiniones-puntos and contains(@class,'hero-puntos')]"), "puntos de paginación");
        $this->assertSame(1, Html::contar($x, "//script[contains(@src,'opiniones_carrusel.js')]"));
    }

    public function testLosTextosSalenDeMarcaPhpYSeEscapan(): void {
        $m = \Marca::todo();
        $m["portada"]["opiniones_titulo"] = "Clientes <i>X</i>";
        $m["portada"]["opiniones_siguiente"] = "Sigue \"ya\"";
        \Marca::usarParaPruebas($m);
        $this->resena(Datos::parte($this->db, 10.00, 5, "Pieza Op Marca"), "Zq Marca", "Texto");
        $x = $this->home();
        $this->assertSame("Clientes <i>X</i>", trim($x->query("//h2[@id='t-opiniones']")->item(0)->textContent));
        $this->assertSame("Sigue \"ya\"", $x->query("//button[@data-opiniones-siguiente]")->item(0)->getAttribute("aria-label"));
    }

    public function testElCssYElJsDefinenLasColumnasLasFlechasYElTeclado(): void {
        $css = (string)file_get_contents(BASE_PATH . "/assets/css/base.css");
        $this->assertStringContainsString("scroll-snap-type: x mandatory", $css);
        $this->assertMatchesRegularExpression('~\.opinion \{ flex: 0 0 100%;~', $css, "1 visible en móvil");
        $this->assertMatchesRegularExpression('~@media \(min-width: 768px\) \{[^@]*\.opinion \{ flex-basis: calc\(\(100% - var\(--spacing-lg\)\) / 2\); \}~', $css, "2 en tableta");
        $this->assertStringContainsString("@media (min-width: 1024px) { .opinion { flex-basis: calc((100% - 2 * var(--spacing-lg)) / 3); } }", $css, "3 en escritorio");
        $this->assertStringContainsString("scrollbar-width: none", $css, "sin barra de scroll nativa");
        $this->assertStringContainsString(".opiniones-ventana::-webkit-scrollbar { display: none; }", $css);
        $this->assertMatchesRegularExpression('~\.opiniones-flecha \{[^}]*width: 44px; height: 44px;[^}]*background: none; border: 0;~', $css, "chevron sin fondo ni borde, zona de 44 px");
        $this->assertStringContainsString("opiniones-flecha:focus-visible", $css);
        $this->assertStringContainsString("line-height: 44px", $css, "enlace de la pieza de 44 px");
        $js = (string)file_get_contents(BASE_PATH . "/assets/js/opiniones_carrusel.js");
        $this->assertStringContainsString('"ArrowLeft"', $js);
        $this->assertStringContainsString('"ArrowRight"', $js);
        $this->assertStringContainsString("aria-current", $js, "el punto activo lleva estado actual");
        $this->assertStringContainsString("aria-label", $js);
        $this->assertStringNotContainsString("setInterval", $js, "sin movimiento automático");
        $this->assertStringNotContainsString("requestAnimationFrame", $js, "sin movimiento automático");
    }

    // ── Scripts de datos demo ──
    private function sqlSinTransaccion(string $archivo): string {
        $sql = (string)file_get_contents(BASE_PATH . "/database/$archivo");
        $sql = preg_replace('~^\s*(START TRANSACTION|COMMIT);\s*$~m', "", $sql);
        return trim(explode("-- Comprobación", $sql)[0]);
    }

    private function piezasDemo(): void {
        foreach (["Puerta Delantera Izquierda", "Alternador 90A", "Faro Delantero Izquierdo", "Bomba de Agua", "Radiador de Aluminio", "Batería 12V 55Ah",
                  "Espejo Retrovisor Ext. Izq.", "Parachoques Frontal", "Motor de Arranque", "Parabrisas Delantero"] as $n) Datos::parte($this->db, 10.00, 5, $n);
    }

    public function testDemoOpinionesInsertaDiezMarcadasRepetiblesYQuitarDemoLasBorra(): void {
        $this->piezasDemo();
        $propia = $this->resena(Datos::parte($this->db, 10.00, 5, "Pieza Op Real"), "Zq Cliente Real", "Comentario verdadero", 5, 1, "2026-10-01 10:00:00", 1);
        $this->db->exec($this->sqlSinTransaccion("demo_opiniones.sql"));
        $this->db->exec($this->sqlSinTransaccion("demo_opiniones.sql")); // repetir no duplica
        $demo = $this->db->query("SELECT rating, publicado, comentario FROM comentarios WHERE auto_pub = 2")->fetchAll();
        $this->assertCount(10, $demo);
        $ratings = array_map("intval", array_column($demo, "rating"));
        $this->assertGreaterThanOrEqual(3, min($ratings));
        $this->assertLessThanOrEqual(5, max($ratings));
        $this->assertSame(1, count(array_filter($ratings, fn($r) => $r === 3)), "una sola de 3 estrellas");
        $this->assertGreaterThanOrEqual(7, count(array_filter($ratings, fn($r) => $r >= 4)), "la mayoría de 4 y 5");
        $this->assertSame([1], array_map("intval", array_values(array_unique(array_column($demo, "publicado")))));

        $this->db->exec($this->sqlSinTransaccion("quitar_demo.sql"));
        $this->assertSame(0, (int)$this->db->query("SELECT COUNT(*) FROM comentarios WHERE auto_pub = 2")->fetchColumn());
        $this->assertSame(1, (int)$this->db->query("SELECT COUNT(*) FROM comentarios WHERE id = $propia")->fetchColumn(), "la reseña real no se toca");
    }

    public function testQuitarDemoBorraPedidosYUsuariosExampleInvalidPeroNoLosReales(): void {
        $this->db->prepare("INSERT INTO usuarios (nombre, email, telefono, direccion, password) VALUES (?, ?, '', '', '!')")->execute(["Zq Demo", "zq.demo@example.invalid"]);
        $this->db->prepare("INSERT INTO usuarios (nombre, email, telefono, direccion, password) VALUES (?, ?, '', '', '!')")->execute(["Zq Real", "zq.real@correo-real.test"]);
        foreach (["zq.demo@example.invalid", "zq.real@correo-real.test"] as $c) {
            $this->db->prepare("INSERT INTO pedidos (cliente_nombre, cliente_telefono, cliente_email, cliente_direccion, estado, subtotal, total) VALUES ('Zq', '1', ?, 'x', 'pendiente', 10, 10.70)")->execute([$c]);
        }
        $this->db->exec($this->sqlSinTransaccion("quitar_demo.sql"));
        $this->assertSame(0, (int)$this->db->query("SELECT COUNT(*) FROM usuarios WHERE email LIKE '%@example.invalid'")->fetchColumn());
        $this->assertSame(0, (int)$this->db->query("SELECT COUNT(*) FROM pedidos WHERE cliente_email LIKE '%@example.invalid'")->fetchColumn());
        $this->assertSame(1, (int)$this->db->query("SELECT COUNT(*) FROM usuarios WHERE email = 'zq.real@correo-real.test'")->fetchColumn());
        $this->assertSame(1, (int)$this->db->query("SELECT COUNT(*) FROM pedidos WHERE cliente_email = 'zq.real@correo-real.test'")->fetchColumn());
    }
}
