<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\Datos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/**
 * Panel > Comentarios: fila ordenada (autor, estrellas, pieza con su auto, texto), fila que enlaza a la pieza,
 * orden por cabeceras (lista blanca, desempate por id) y sin forma de eliminar un comentario desde el panel.
 */
final class ComentariosPanelTest extends BaseDatos {
    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
    }

    private function auto(string $marca = "Toyota", string $modelo = "Corolla", int $anio = 2018): int {
        $this->db->prepare("INSERT INTO autos (marca, modelo, anio) VALUES (?,?,?)")->execute([$marca, $modelo, $anio]);
        return (int)$this->db->lastInsertId();
    }

    private function comentario(int $parte, string $autor, string $texto, ?int $rating, int $publicado = 1, string $fecha = "2026-01-01 10:00:00"): int {
        $this->db->prepare("INSERT INTO comentarios (parte_id, nombre_visitante, comentario, publicado, rating, creado_en) VALUES (?,?,?,?,?,?)")->execute([$parte, $autor, $texto, $publicado, $rating, $fecha]);
        return (int)$this->db->lastInsertId();
    }

    private function parteConAuto(string $nombre, bool $activa = true): int {
        $id = Datos::parte($this->db, 10, 5, $nombre);
        $this->db->prepare("UPDATE partes SET auto_id = ?, activo = ? WHERE id = ?")->execute([$this->auto(), $activa ? 1 : 0, $id]);
        return $id;
    }

    /** La vista con una lista de comentarios, un orden y una página dados, como Administrador o no */
    private function vista(string $orden = "fecha_desc", int $pagina = 1, bool $admin = true): \DOMXPath {
        $m = new \ComentarioModel();
        $pag = \Paginador::crear($pagina, $m->contar(), \Paginador::PANEL);
        $comentarios = $m->findAllWithParte($pag, $orden);
        $puedeModerar = $admin; $puedeEditar = $admin; $destacadas = $m->contarDestacadas();
        $_SESSION["user"] = ["id" => Datos::usuario($this->db), "nombre" => "Admin", "email" => "a@example.invalid"];
        $_GET = ["mod" => "comentarios"];
        ob_start();
        try { include BASE_PATH . "/app/Views/admin/comentarios/index.php"; } finally { $h = (string)ob_get_clean(); $_GET = []; unset($_SESSION["user"]); }
        $this->html = $h;
        return Html::xpath($h);
    }

    private string $html = "";

    // ── Portada (S7b-D) ──

    public function testLaFilaMuestraLaFichaPortadaYElBotonCorrectoSegunEstaDestacada(): void {
        $p = $this->parteConAuto("Pieza Portada Panel");
        $a = $this->comentario($p, "Zq Destacada", "Texto uno", 5);
        $b = $this->comentario($p, "Zq Normal", "Texto dos", 5);
        $c = $this->comentario($p, "Zq Oculta", "Texto tres", 5, 0);
        $this->db->prepare("UPDATE comentarios SET destacado = 1 WHERE id = ?")->execute([$a]);
        $x = $this->vista();
        $fila = fn(string $n) => $x->query("//tr[.//span[contains(@class,'celda-titulo') and starts-with(normalize-space(),'$n')]]")->item(0);
        $this->assertSame(1, $x->query(".//span[contains(@class,'chip-portada')]", $fila("Zq Destacada"))->length, "ficha Portada en la destacada");
        $this->assertSame(0, $x->query(".//span[contains(@class,'chip-portada')]", $fila("Zq Normal"))->length);
        $this->assertSame(1, $x->query(".//form[contains(@action,'action=quitar_portada&id=$a')]//input[@name='csrf_token' or @type='hidden']", $fila("Zq Destacada"))->length);
        $this->assertSame(1, $x->query(".//form[contains(@action,'action=destacar&id=$b')]", $fila("Zq Normal"))->length);
        $this->assertSame(0, $x->query(".//form[contains(@action,'action=destacar')]", $fila("Zq Oculta"))->length, "una oculta no ofrece destacar");
    }

    // ── 1. La fila ──

    public function testLaFilaTraeAutorEstrellasPiezaConAutoYElTextoCompleto(): void {
        $p = $this->parteConAuto("Transmisión Automática");
        $largo = str_repeat("Muy buena pieza, llegó rápido y funciona perfecto. ", 8);
        $this->comentario($p, "Rosa Prueba", $largo, 4);
        $this->comentario($p, "Luis Sin Nota", "Sin calificar", null);
        $x = $this->vista();
        $fila = $x->query("//tr[.//span[contains(@class,'celda-titulo') and normalize-space()='Rosa Prueba']]")->item(0);
        $q = new \DOMXPath($fila->ownerDocument);
        $this->assertSame("Transmisión Automática · Toyota Corolla 2018", trim($q->query(".//*[contains(@class,'celda-sub')]", $fila)->item(0)->textContent), "pieza · marca modelo año en una línea");
        $this->assertSame(trim($largo), trim($q->query(".//span[contains(@class,'comentario-texto')]", $fila)->item(0)->textContent), "el texto va completo: el recorte a 2 líneas es de CSS, no corta el HTML");
        $e = $q->query(".//span[contains(@class,'estrellas')]", $fila)->item(0);
        $this->assertSame("4 de 5 estrellas", $e->getAttribute("aria-label"));
        $this->assertSame("img", $e->getAttribute("role"));
        $this->assertSame(4, $q->query(".//i[contains(@class,'bi-star-fill')]", $e)->length);
        $this->assertSame(1, $q->query(".//i[@class='bi bi-star']", $e)->length, "5 iconos: 4 llenos y 1 vacío");
        $sin = $x->query("//tr[.//span[normalize-space()='Luis Sin Nota']]")->item(0);
        $this->assertStringContainsString("Sin calificación", $sin->textContent);
        $this->assertSame(0, (new \DOMXPath($sin->ownerDocument))->query(".//span[contains(@class,'estrellas')]", $sin)->length);
    }

    public function testElTextoSeRecortaA2LineasConCssYLasEstrellasUsanElTokenDeEnlace(): void {
        $css = (string)file_get_contents(BASE_PATH . "/assets/css/lista.css");
        preg_match('/\.comentario-texto\s*\{([^}]*)\}/', $css, $m);
        $this->assertMatchesRegularExpression('/-webkit-line-clamp:\s*2/', $m[1]);
        $this->assertMatchesRegularExpression('/line-clamp:\s*2/', $m[1]);
        $this->assertMatchesRegularExpression('/overflow:\s*hidden/', $m[1]);
        preg_match('/\.estrellas\s*\{([^}]*)\}/', $css, $e);
        $this->assertStringContainsString("var(--link)", $e[1]);
    }

    public function testElModeloTraeLaPiezaConSuAutoYSiEstaActivaEnUnSoloJoin(): void {
        $p = $this->parteConAuto("Faro");
        $this->comentario($p, "A", "texto largo suficiente", 5);
        $fila = (new \ComentarioModel())->findAllWithParte(null, "fecha_desc")[0];
        foreach (["parte_nombre" => "Faro", "marca" => "Toyota", "modelo" => "Corolla", "anio" => 2018, "parte_activo" => 1] as $k => $v) $this->assertEquals($v, $fila[$k], $k);
        $src = (string)file_get_contents(BASE_PATH . "/app/Models/ComentarioModel.php");
        $this->assertSame(1, preg_match_all('/SELECT c\.\*,p\.nombre AS parte_nombre/', $src), "una sola consulta de lista");
        $this->assertStringContainsString("LEFT JOIN partes p ON p.id=c.parte_id LEFT JOIN autos a ON a.id=p.auto_id", $src);
    }

    // ── 2. La fila enlaza a la pieza ──

    public function testLaFilaEnlazaALaPaginaPublicaEnLaMismaPestanaConAnclaALosComentarios(): void {
        $p = $this->parteConAuto("Faro Enlace");
        $this->comentario($p, "Ana", "texto largo suficiente", 3);
        $x = $this->vista();
        $a = $x->query("//tr[contains(@class,'fila-enlazada')]//a[contains(@class,'fila-enlace')]");
        $this->assertGreaterThan(0, $a->length);
        $h = $a->item(0)->getAttribute("href");
        $this->assertSame(APP_URL . "/?action=detalle&id=" . $p . "#comentarios", $h);
        $this->assertSame("", $a->item(0)->getAttribute("target"), "misma pestaña");
        $this->assertStringContainsString("Faro Enlace", $a->item(0)->getAttribute("aria-label"));
        $this->assertStringContainsString("sección de comentarios", $a->item(0)->getAttribute("aria-label"));
        $this->assertStringContainsString('id="comentarios"', (string)file_get_contents(BASE_PATH . "/app/Views/public/detalle.php"), "la ancla existe en la página de la pieza");
    }

    public function testUnaPiezaDesactivadaSeIndicaYNoEnlaza(): void {
        $p = $this->parteConAuto("Pieza Vieja", false);
        $this->comentario($p, "Beto", "texto largo suficiente", 2);
        $x = $this->vista();
        $tr = $x->query("//tr[.//span[normalize-space()='Beto']]")->item(0);
        $q = new \DOMXPath($tr->ownerDocument);
        $this->assertSame(0, $q->query(".//a[contains(@class,'fila-enlace')]", $tr)->length, "sin enlace");
        $this->assertStringNotContainsString("fila-enlazada", $tr->getAttribute("class"));
        $this->assertStringContainsString("Pieza desactivada", $tr->textContent);
        $this->assertStringContainsString("Pieza Vieja", $tr->textContent);
    }

    public function testElEnlaceCubreLaFilaYLasAccionesQuedanPorEncima(): void {
        $css = (string)file_get_contents(BASE_PATH . "/assets/css/lista.css");
        $this->assertMatchesRegularExpression('/\.fila-enlace::after\s*\{[^}]*position:\s*absolute;\s*inset:\s*0;\s*z-index:\s*1/', $css);
        $this->assertMatchesRegularExpression('/tr\.fila-enlazada\s*\{[^}]*position:\s*relative;\s*cursor:\s*pointer/', $css);
        $this->assertMatchesRegularExpression('/\.fila-enlazada \.lista-acciones\s*\{[^}]*position:\s*relative;\s*z-index:\s*2/', $css);
    }

    // ── 3. Orden ──

    public function testElOrdenSoloAceptaLaListaBlancaYUnValorRaroVuelveAMasRecientes(): void {
        foreach (["fecha_desc", "fecha_asc", "rating_desc", "rating_asc", "estado_desc", "estado_asc"] as $o) $this->assertSame($o, \ComentarioModel::ordenValido($o));
        foreach (["", "id", "fecha", "c.id; DROP TABLE comentarios", "FECHA_DESC", "rating_desc, id", "../x"] as $malo) $this->assertSame("fecha_desc", \ComentarioModel::ordenValido($malo), "«{$malo}»");
        $this->assertSame(\ComentarioModel::ORDEN_POR_DEFECTO, "fecha_desc");
    }

    public function testCadaOrdenDevuelveLasFilasEnElOrdenPedidoYDesempataPorId(): void {
        $p = $this->parteConAuto("Pieza orden");
        $a = $this->comentario($p, "A", "texto largo suficiente", 5, 1, "2026-01-01 10:00:00");
        $b = $this->comentario($p, "B", "texto largo suficiente", 1, 0, "2026-01-02 10:00:00");
        $c = $this->comentario($p, "C", "texto largo suficiente", null, 1, "2026-01-02 10:00:00"); // misma fecha que B
        $d = $this->comentario($p, "D", "texto largo suficiente", 3, 1, "2025-12-31 10:00:00");
        $m = new \ComentarioModel();
        $ids = fn(string $o) => array_values(array_filter(array_column($m->findAllWithParte(null, $o), "id"), fn($i) => in_array((int)$i, [$a, $b, $c, $d], true)));
        $this->assertEquals([$c, $b, $a, $d], $ids("fecha_desc"), "más recientes; B y C empatan y gana el id mayor");
        $this->assertEquals([$d, $a, $b, $c], $ids("fecha_asc"), "más antiguos; el empate por id ascendente");
        $this->assertEquals([$a, $d, $b, $c], $ids("rating_desc"), "mejor calificados; sin nota al final");
        $this->assertEquals([$b, $d, $a, $c], $ids("rating_asc"), "peor calificados; sin nota al final");
        $this->assertEquals([$d, $c, $a, $b], $ids("estado_desc"), "publicados primero, desempate por id");
        $this->assertEquals([$b, $d, $c, $a], $ids("estado_asc"), "ocultos primero");
        $this->assertEquals($ids("fecha_desc"), $ids("cualquier cosa"), "valor raro: por defecto");
    }

    public function testLasCabecerasSonEnlacesConFlechaSoloEnLaActivaYConservanLaPagina(): void {
        $p = $this->parteConAuto("Pieza pagina");
        for ($i = 0; $i < 45; $i++) $this->comentario($p, "N$i", "texto largo suficiente", ($i % 5) + 1, 1, sprintf("2026-02-%02d 10:00:00", ($i % 27) + 1));
        $x = $this->vista("rating_desc", 2);
        $ths = $x->query("//thead//th[.//a[contains(@class,'orden-enlace')]]");
        $this->assertSame(3, $ths->length, "Calificación, Estado y Fecha ordenan; Comentario y Acciones no");
        $por = [];
        foreach ($ths as $th) {
            $q = new \DOMXPath($th->ownerDocument);
            $por[trim($th->textContent)] = [$th->getAttribute("aria-sort"), $q->query(".//i[contains(@class,'bi-arrow-')]", $th)->length ? $q->query(".//i", $th)->item(0)->getAttribute("class") : null, $q->query(".//a", $th)->item(0)->getAttribute("href")];
        }
        $base = APP_URL . "/admin/?";
        $this->assertSame(["descending", "bi bi-arrow-down", $base . "mod=comentarios&orden=rating_asc&page=2"], $por["Calificación"], "la activa lleva flecha y su clic invierte; conserva la página");
        $this->assertSame(["none", null, $base . "mod=comentarios&page=2"], $por["Fecha"], "inactivas: sin flecha y empiezan en descendente (que es el orden por defecto: sin parámetro)");
        $this->assertSame(["none", null, $base . "mod=comentarios&orden=estado_desc&page=2"], $por["Estado"]);
        $x2 = $this->vista("fecha_asc");
        $f = $x2->query("//thead//th[.//a[normalize-space()='Fecha']]")->item(0);
        $this->assertSame("ascending", $f->getAttribute("aria-sort"));
        $this->assertSame("bi bi-arrow-up", $x2->query(".//i", $f)->item(0)->getAttribute("class"), "ascendente: flecha hacia arriba");
        $this->assertStringEndsWith("mod=comentarios", $x2->query(".//a", $f)->item(0)->getAttribute("href"), "desde ascendente vuelve al de por defecto, sin parámetro");
    }

    public function testLaPaginacionConservaElOrden(): void {
        $p = $this->parteConAuto("Pieza paginada");
        for ($i = 0; $i < 25; $i++) $this->comentario($p, "P$i", "texto largo suficiente", 3);
        $m = new \ComentarioModel();
        $pag = \Paginador::crear(1, $m->contar(), \Paginador::PANEL);
        $this->assertStringContainsString("orden=rating_asc", $pag->url(2, ["mod" => "comentarios", "orden" => "rating_asc"], APP_URL . "/admin/"));
        $src = (string)file_get_contents(BASE_PATH . "/app/Controllers/ComentarioController.php");
        $this->assertStringContainsString('"orden"=>$orden', $src, "el controlador pasa el orden a la paginación");
    }

    public function testEnMovilUnSelectOrdenarPorConLasCuatroOpciones(): void {
        $this->comentario($this->parteConAuto("X"), "Z", "texto largo suficiente", 2);
        $x = $this->vista("rating_desc");
        $f = $x->query("//form[contains(@class,'lista-orden-movil')]");
        $this->assertSame(1, $f->length);
        $this->assertSame("GET", strtoupper($f->item(0)->getAttribute("method")));
        $this->assertSame(1, $x->query(".//label[@for='f-orden' and normalize-space()='Ordenar por']", $f->item(0))->length, "etiqueta visible");
        $o = [];
        foreach ($x->query(".//select[@id='f-orden' and @name='orden']/option", $f->item(0)) as $op) $o[$op->getAttribute("value")] = [trim($op->textContent), $op->hasAttribute("selected")];
        $this->assertSame(["fecha_desc" => ["Más recientes", false], "fecha_asc" => ["Más antiguos", false], "rating_desc" => ["Mejor calificados", true], "rating_asc" => ["Peor calificados", false]], $o);
        $this->assertSame(1, $x->query("//form[@id='fAdm']")->length, "main.js lo envía al cambiar, igual que los filtros de partes");
        $css = (string)file_get_contents(BASE_PATH . "/assets/css/lista.css");
        $this->assertMatchesRegularExpression('/\.lista-orden-movil\s*\{\s*display:\s*none/', $css, "oculto desde 768 px");
        $this->assertMatchesRegularExpression('/@media \(max-width: 767\.98px\) \{ \.lista-orden-movil \{ display: block; \} \}/', $css);
        $x2 = $this->vista("estado_asc");
        $this->assertSame(1, $x2->query("//select[@id='f-orden']/option[@value='estado_asc' and @selected]")->length, "si se ordena por estado desde la cabecera, el select lo muestra");
    }

    // ── 4. Sin eliminar; ocultar solo el Administrador ──

    public function testYaNoExisteFormaDeEliminarUnComentarioDesdeElPanel(): void {
        $this->assertFalse(method_exists(\ComentarioController::class, "eliminar"), "la acción del servidor ya no existe");
        $this->comentario($this->parteConAuto("Y"), "W", "texto largo suficiente", 4);
        $h = $this->vista();
        $this->assertStringNotContainsString("action=eliminar", $this->html);
        $this->assertSame(0, Html::contar($h, "//*[contains(@class,'accion-peligro') or contains(normalize-space(),'Eliminar')]"), "ni botón ni modal de eliminar");
        $this->assertStringNotContainsString("hardDelete", (string)file_get_contents(BASE_PATH . "/app/Controllers/ComentarioController.php"));
        $this->assertStringNotContainsString("puede_borrar", (string)file_get_contents(BASE_PATH . "/app/Controllers/ComentarioController.php"));
    }

    public function testElAdministradorVeOcultarYMostrarConModalYOtroRolNoVeAcciones(): void {
        $p = $this->parteConAuto("Pieza mod");
        $vis = $this->comentario($p, "Visible", "texto largo suficiente", 3, 1);
        $ocu = $this->comentario($p, "Oculto", "texto largo suficiente", 3, 0);
        $x = $this->vista("fecha_desc", 1, true);
        $f = $x->query("//form[contains(@action,'action=toggle')]");
        $this->assertGreaterThanOrEqual(2, $f->length);
        $titulos = [];
        foreach ($f as $form) {
            $this->assertSame("POST", strtoupper($form->getAttribute("method")));
            $this->assertSame(1, (new \DOMXPath($form->ownerDocument))->query(".//input[@name='csrf_token']", $form)->length);
            $d = json_decode($form->getAttribute("data-confirmar"), true, 512, JSON_THROW_ON_ERROR);
            $titulos[] = $d["titulo"];
            $this->assertSame(["Ocultar" => true, "Mostrar" => false][$d["boton"]], !empty($d["peligro"]), "ocultar pide confirmar con botón rojo; mostrar, con el principal");
        }
        $this->assertContains("Ocultar el comentario de «Visible»", $titulos);
        $this->assertContains("Mostrar el comentario de «Oculto»", $titulos);
        $x2 = $this->vista("fecha_desc", 1, false);
        $this->assertSame(0, Html::contar($x2, "//form[contains(@action,'action=toggle')]"), "sin Administrador no hay acciones");
        $this->assertSame(0, Html::contar($x2, "//th[contains(@class,'col-acciones')]"), "ni columna de Acciones");
    }

    public function testElPanelRealMuestraComentariosYLaVistaDelAdministradorEstaEnPantallas(): void {
        $x = Html::xpath(Pantallas::render($this->db, "admin_comentarios"));
        $this->assertSame(1, Html::contar($x, "//table[contains(@class,'lista-tabla')]"));
        $this->assertGreaterThan(0, Html::contar($x, "//form[contains(@action,'action=toggle')]"), "el usuario de las pantallas es Administrador");
    }
}
