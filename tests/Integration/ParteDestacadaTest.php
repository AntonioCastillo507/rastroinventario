<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\Datos;
use Tests\Support\Html;
use Tests\Support\Imagenes;
use Tests\Support\Pantallas;

/** S7b-C.2: piezas destacadas en la portada (columna, validaciones del formulario, modal con vista previa 21:10, hero y lista del panel). */
final class ParteDestacadaTest extends BaseDatos {
    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
        $this->db->exec("UPDATE partes SET imagen_grande = NULL, thumbnail = NULL, destacado = 0");
    }

    private function pieza(string $nombre, bool $conFoto = true, bool $destacada = false, int $cantidad = 5): int {
        $id = Datos::parte($this->db, 12.50, $cantidad, $nombre);
        if ($conFoto) {
            $r = \ImageHelper::procesar(Imagenes::jpeg());
            $this->db->prepare("UPDATE partes SET thumbnail = ?, imagen_grande = ? WHERE id = ?")->execute([$r["thumbnail"], $r["original"], $id]);
        }
        if ($destacada) $this->db->prepare("UPDATE partes SET destacado = 1 WHERE id = ?")->execute([$id]);
        return $id;
    }

    private function enviar(string $accion, array $post, int $id = 0): \DOMXPath {
        $uid = Datos::usuario($this->db);
        $this->db->prepare("INSERT INTO usuario_roles (usuario_id, rol_id) SELECT ?, id FROM roles WHERE nombre = 'Administrador'")->execute([$uid]);
        $_SESSION["user"] = ["id" => $uid, "nombre" => "Admin de prueba", "email" => "admin@example.invalid"];
        $_SESSION["user_roles"] = ["Administrador"];
        $_SERVER["REQUEST_METHOD"] = $post ? "POST" : "GET";
        $_GET = ["mod" => "partes", "action" => $accion] + ($id ? ["id" => (string)$id] : []);
        $_POST = $post ? $post + ["csrf_token" => \CsrfToken::generate()] : [];
        ob_start();
        try {
            (new \ParteController())->$accion();
        } finally {
            $html = (string)ob_get_clean();
            $_SERVER["REQUEST_METHOD"] = "GET";
            $_GET = $_POST = [];
            unset($_SESSION["user"], $_SESSION["user_roles"], $_SESSION["flash"]);
        }
        return Html::xpath($html);
    }

    private function portada(): \DOMXPath { return Html::xpath(Pantallas::render($this->db, "home")); }
    private function base(string $nombre): array { return ["nombre" => $nombre, "precio" => "5", "cantidad" => "2"]; }
    private function contar(): int { return (new \ParteModel())->contarDestacadas(); }

    // ── esquema ──
    public function testLaColumnaExisteEnLaBaseYEnElEsquemaYLaMigracionEsIdempotente(): void {
        $c = $this->db->query("SHOW COLUMNS FROM partes LIKE 'destacado'")->fetch();
        $this->assertNotFalse($c);
        $this->assertStringContainsString("tinyint(1)", $c["Type"]);
        $this->assertSame("NO", $c["Null"]);
        $this->assertSame("0", (string)$c["Default"]);
        $esq = (string)file_get_contents(BASE_PATH . "/database/schema.sql");
        $this->assertMatchesRegularExpression("~`destacado` tinyint\\(1\\) NOT NULL DEFAULT '0'~", $esq);
        $mig = (string)file_get_contents(BASE_PATH . "/database/migraciones/004_partes_destacado.sql");
        $this->assertStringContainsString("information_schema.COLUMNS", $mig, "solo agrega la columna si falta");
        $this->assertMatchesRegularExpression("~ADD COLUMN `destacado` tinyint\\(1\\) NOT NULL DEFAULT ''0''~", $mig);
        $this->assertDoesNotMatchRegularExpression("~\\b(DROP|DELETE|TRUNCATE)\\b~i", $mig, "no borra nada");
    }

    // ── validaciones en el servidor ──
    public function testUnaPiezaSinFotoNoSePuedeDestacarAlCrear(): void {
        $x = $this->enviar("crear", $this->base("Sin foto destacada") + ["destacado" => "1"]);
        $this->assertStringContainsString(\ParteController::ERR_DESTACADA_SIN_FOTO, $x->query("//*[@role='alert']")->item(0)->textContent);
        $this->assertSame(0, (int)$this->db->query("SELECT COUNT(*) FROM partes WHERE nombre = 'Sin foto destacada'")->fetchColumn(), "no se guarda nada");
        $this->assertSame(1, Html::contar($x, "//input[@name='destacado'][@checked]"), "el formulario vuelve con la casilla marcada");
    }

    public function testUnaPiezaSinFotoNoSePuedeDestacarAlEditar(): void {
        $id = $this->pieza("Sin foto", false);
        $x = $this->enviar("editar", $this->base("Sin foto") + ["destacado" => "1"], $id);
        $this->assertStringContainsString("sin foto no puede ser destacada", $x->query("//*[@role='alert']")->item(0)->textContent);
        $this->assertSame(0, (int)$this->db->query("SELECT destacado FROM partes WHERE id = $id")->fetchColumn());
    }

    public function testElTopeEsSeisDestacadasALaVez(): void {
        for ($i = 1; $i <= 6; $i++) $this->pieza("Destacada $i", true, true);
        $septima = $this->pieza("Séptima");
        $x = $this->enviar("editar", $this->base("Séptima") + ["destacado" => "1"], $septima);
        $this->assertStringContainsString("Ya hay 6 piezas destacadas; quita una", $x->query("//*[@role='alert']")->item(0)->textContent);
        $this->assertSame(0, (int)$this->db->query("SELECT destacado FROM partes WHERE id = $septima")->fetchColumn());
        $this->assertSame(6, $this->contar());
    }

    public function testLaReglaDeValidacionDelFormulario(): void {
        for ($i = 1; $i <= 5; $i++) $this->pieza("D$i", true, true);
        $sexta = $this->pieza("Sexta");
        $m = new \ReflectionMethod(\ParteController::class, "errorDestacada");
        $m->setAccessible(true);
        $c = new \ParteController();
        $this->assertSame("", $m->invoke($c, true, $sexta, true), "la sexta cabe");
        $this->db->prepare("UPDATE partes SET destacado = 1 WHERE id = ?")->execute([$sexta]);
        $this->assertSame("", $m->invoke($c, true, $sexta, true), "la que ya cuenta no se descuenta dos veces al editarla");
        $otra = $this->pieza("Otra");
        $this->assertSame("", $m->invoke($c, false, $otra, true), "quitarla nunca da error");
        $this->assertSame(\ParteController::ERR_DESTACADAS_TOPE, $m->invoke($c, true, $otra, true));
        $this->assertSame(\ParteController::ERR_DESTACADA_SIN_FOTO, $m->invoke($c, true, 0, false));
    }

    public function testLasInactivasNoCuentanParaElTope(): void {
        for ($i = 1; $i <= 6; $i++) $this->pieza("Destacada $i", true, true);
        $this->db->exec("UPDATE partes SET activo = 0 WHERE nombre = 'Destacada 1'");
        $this->assertSame(5, $this->contar());
    }

    // ── formulario y modal ──
    public function testElFormularioTieneLaCasillaEnOrganizacionYElModalConLaVistaPrevia21a10(): void {
        $x = $this->enviar("crear", []);
        $this->assertSame(1, Html::contar($x, "//section[.//h2[@id='pf-org']]//input[@name='destacado'][@type='checkbox'][@value='1'][@data-destacar]"), "casilla en la tarjeta de Organización");
        $this->assertStringContainsString("Mostrar en la portada (destacada)", $x->query("//label[@for='f-destacado']")->item(0)->textContent);
        $m = $x->query("//dialog[@id='modalDestacar']");
        $this->assertSame(1, $m->length);
        $this->assertStringContainsString("modal-confirmar", $m->item(0)->getAttribute("class"), "reutiliza el componente modal de confirmación");
        $t = $m->item(0)->textContent;
        foreach (["En la portada la foto se muestra horizontal, recortada al centro, en formato 21:10", "Lo principal de la pieza debe verse en el centro de la foto", "Mínimo recomendado: 1200 × 570 px", "Sí, se ve bien, destacarla", "Cancelar"] as $f) $this->assertStringContainsString($f, $t, $f);
        $this->assertSame(1, Html::contar($x, "//dialog[@id='modalDestacar']//span[contains(@class,'hero-foto')]/img[@data-destacar-img]"), "la vista previa usa la misma caja .hero-foto que la tarjeta del hero");
        $css = (string)file_get_contents(BASE_PATH . "/assets/css/base.css");
        $this->assertMatchesRegularExpression('~\.hero-foto \{[^}]*aspect-ratio: 21 / 10~', $css);
        $this->assertMatchesRegularExpression('~\.hero-foto img \{[^}]*object-fit: cover~', $css);
    }

    public function testElScriptDelModalCancelaDesmarcaYExigeFoto(): void {
        $js = (string)file_get_contents(BASE_PATH . "/assets/js/parte_destacar.js");
        foreach (["showModal", "data-foto-actual", "createObjectURL", "acepto"] as $t) $this->assertStringContainsString($t, $js, $t);
        $this->assertMatchesRegularExpression('~if \(!acepto\) casilla\.checked = false~', $js, "al cancelar la casilla vuelve a desmarcada");
        $this->assertMatchesRegularExpression('~if \(!src\) \{\s*casilla\.checked = false~', $js, "sin foto no se abre y se desmarca");
    }

    public function testEditarMarcaLaCasillaYMuestraLaFotoActualEnElModal(): void {
        $id = $this->pieza("Con foto", true, true);
        $x = $this->enviar("editar", [], $id);
        $this->assertSame(1, Html::contar($x, "//input[@name='destacado'][@checked]"));
        $this->assertMatchesRegularExpression('~/uploads/.+~', $x->query("//input[@name='destacado']")->item(0)->getAttribute("data-foto-actual"));
    }

    // ── hero ──
    public function testElHeroUsaSoloLasDestacadasCuandoHayDosOMas(): void {
        $d1 = $this->pieza("Dest A", true, true);
        $d2 = $this->pieza("Dest B", true, true);
        for ($i = 1; $i <= 5; $i++) $this->pieza("Normal $i");
        for ($v = 0; $v < 6; $v++) {
            $ids = array_map(fn($p) => (int)$p["id"], (new \ParteModel())->paraHero(4, []));
            $this->assertSame([$d1, $d2], $ids, "solo las destacadas, en orden estable por id");
        }
        $this->assertSame(2, Html::contar($this->portada(), "//*[@data-hero-carrusel]//a[@data-hero-diapo]"));
    }

    public function testLasDestacadasSinStockInactivasOSinFotoNoCuentanParaElMinimo(): void {
        $this->pieza("Dest A", true, true);
        $this->pieza("Sin stock", true, true, 0);
        $this->pieza("Sin foto", false, true);
        $in = $this->pieza("Inactiva", true, true);
        $this->db->prepare("UPDATE partes SET activo = 0 WHERE id = ?")->execute([$in]);
        $normales = [];
        for ($i = 1; $i <= 4; $i++) $normales[] = $this->pieza("Normal $i");
        $ids = array_map(fn($p) => (int)$p["id"], (new \ParteModel())->paraHero(4, []));
        $this->assertCount(4, $ids, "con una sola destacada vuelve a las aleatorias");
        $this->assertNotEmpty(array_intersect($ids, $normales));
    }

    public function testConMenosDeDosDestacadasElHeroEsAleatorioYRespetaLasSeccionesExcluidas(): void {
        $nombre = "Sec excl " . bin2hex(random_bytes(3));
        $this->db->prepare("INSERT INTO secciones (nombre) VALUES (?)")->execute([$nombre]);
        $sec = (int)$this->db->lastInsertId();
        $dest = $this->pieza("Única destacada", true, true);
        $this->db->prepare("UPDATE partes SET seccion_id = ? WHERE id = ?")->execute([$sec, $dest]);
        $libres = [$this->pieza("Libre 1"), $this->pieza("Libre 2")];
        for ($v = 0; $v < 6; $v++) {
            $ids = array_map(fn($p) => (int)$p["id"], (new \ParteModel())->paraHero(4, [$nombre]));
            $this->assertEqualsCanonicalizing($libres, $ids, "una sola destacada no basta: sale el azar y la sección excluida no aporta");
        }
    }

    public function testElHeroPuedeLlevarSeisDestacadas(): void {
        for ($i = 1; $i <= 6; $i++) $this->pieza("Dest $i", true, true);
        $this->assertCount(6, (new \ParteModel())->paraHero(4, []));
    }

    // ── lista del panel ──
    public function testLaListaMarcaLasDestacadasConLaFichaPortada(): void {
        $this->pieza("Es destacada", true, true);
        $this->pieza("No es destacada");
        $x = $this->enviar("index", []);
        $this->assertSame(1, Html::contar($x, "//span[contains(@class,'chip-info')][normalize-space()='Portada']"));
        $this->assertSame(1, Html::contar($x, "//span[contains(@class,'celda-titulo')][normalize-space()='Es destacada']/following-sibling::span[normalize-space()='Portada']"));
    }
}
