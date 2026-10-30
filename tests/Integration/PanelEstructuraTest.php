<?php
namespace Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BaseDatos;
use Tests\Support\Datos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/**
 * S5.1: estructura del panel. Barra lateral agrupada (contraíble en escritorio, cajón en móvil), encabezado con
 * título, tema y menú de cuenta (Salir por POST), y módulos según los permisos del rol.
 */
final class PanelEstructuraTest extends BaseDatos {
    private const GRUPOS = ["General" => ["Dashboard"], "Catálogo" => ["Partes", "Autos", "Marcas", "Secciones"], "Ventas" => ["Pedidos", "Ventas", "Reportes", "Comentarios"], "Administración" => ["Usuarios", "Roles", "Seguridad"], "Cuenta" => ["Cambiar contraseña"]];

    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
    }

    private static function css(): string { return (string)file_get_contents(BASE_PATH . "/assets/css/panel.css"); }

    /** declaraciones de la primera regla cuyo selector es exactamente $selector (fuera o dentro de un @media) */
    private static function regla(string $selector): string {
        preg_match('/(?:^|[}\s{])' . preg_quote($selector, "/") . '\s*\{([^}]*)\}/m', self::css(), $m);
        return $m[1] ?? "";
    }

    /** @return array<string,array{0:string,1:string}> pantalla => [pantalla, módulo activo del menú] */
    public static function pantallas(): array {
        return [
            "admin_dashboard" => ["admin_dashboard", "Dashboard"],
            "admin_partes" => ["admin_partes", "Partes"],
            "admin_partes_editar" => ["admin_partes_editar", "Partes"],
            "admin_pedidos" => ["admin_pedidos", "Pedidos"],
            "admin_pedido_detalle" => ["admin_pedido_detalle", "Pedidos"],
            "admin_ventas_crear" => ["admin_ventas_crear", "Ventas"],
            "admin_seguridad" => ["admin_seguridad", "Seguridad"],
            "admin_usuarios" => ["admin_usuarios", "Usuarios"],
            "admin_autos" => ["admin_autos", "Autos"],
            "admin_secciones_crear" => ["admin_secciones_crear", "Secciones"],
            "admin_roles_editar" => ["admin_roles_editar", "Roles"],
            "admin_comentarios" => ["admin_comentarios", "Comentarios"],
            "admin_reportes" => ["admin_reportes", "Reportes"],
        ];
    }

    #[DataProvider("pantallas")]
    public function testBarraLateralAgrupadaConElModuloActivoYElTituloEnElEncabezado(string $pantalla, string $activo): void {
        $x = Html::xpath(Pantallas::render($this->db, $pantalla));
        $aside = $x->query("//aside[@id='panelMenu']");
        $this->assertSame(1, $aside->length, "$pantalla: una barra lateral");
        $this->assertStringContainsString("offcanvas-lg offcanvas-start", $aside->item(0)->getAttribute("class"), "cajón por debajo de 992 px");
        $this->assertSame("-1", $aside->item(0)->getAttribute("tabindex"));
        $this->assertSame(1, $x->query("//*[@id='" . $aside->item(0)->getAttribute("aria-labelledby") . "']")->length, "el cajón se nombra con un elemento que existe");
        $this->assertSame(1, Html::contar($x, "//aside[@id='panelMenu']/nav[@aria-label='Módulos del panel']"));

        // grupos y módulos, en orden, cada lista nombrada por su título
        $grupos = [];
        foreach ($x->query("//aside//div[contains(@class,'admin-grupo')]") as $g) {
            $q = new \DOMXPath($g->ownerDocument);
            $titulo = $q->query("./p[contains(@class,'admin-grupo-titulo')]", $g)->item(0);
            $lista = $q->query("./ul", $g)->item(0);
            $this->assertSame($titulo->getAttribute("id"), $lista->getAttribute("aria-labelledby"), "$pantalla: lista nombrada por su título");
            foreach ($q->query(".//a", $g) as $a) $grupos[trim($titulo->textContent)][] = trim($a->textContent);
        }
        $this->assertSame(self::GRUPOS, $grupos, "$pantalla: grupos y orden");

        // el activo: uno solo, con aria-current, clase y el mismo texto que el título del encabezado
        $marcados = [];
        foreach ($x->query("//aside//a[@aria-current='page']") as $a) $marcados[] = trim($a->textContent);
        $this->assertSame([$activo], $marcados, "$pantalla: opción activa");
        foreach ($x->query("//aside//a[contains(@class,'admin-enlace')]") as $a) {
            $this->assertSame(trim($a->textContent) === $activo, str_contains(" " . $a->getAttribute("class") . " ", " activo "), "$pantalla: clase de " . trim($a->textContent));
            $this->assertSame(1, $x->query(".//i[@aria-hidden='true']", $a)->length, "icono decorativo");
        }
        $this->assertSame($activo, trim($x->query("//header[contains(@class,'admin-topbar')]//p[contains(@class,'admin-topbar-titulo')]")->item(0)->textContent), "$pantalla: título del encabezado");
        $this->assertSame(1, Html::contar($x, "//h1"), "$pantalla: el título del encabezado no es un segundo h1");
    }

    public function testEncabezadoConMenuDelCajonTemaYCuenta(): void {
        $x = Html::xpath(Pantallas::render($this->db, "admin_partes"));
        $abrir = $x->query("//header//button[@data-bs-toggle='offcanvas']");
        $this->assertSame(1, $abrir->length);
        $this->assertSame("#panelMenu", $abrir->item(0)->getAttribute("data-bs-target"));
        $this->assertSame("Abrir el menú", $abrir->item(0)->getAttribute("aria-label"));
        $this->assertStringContainsString("d-lg-none", $abrir->item(0)->getAttribute("class"), "solo en móvil y tableta");
        $this->assertSame(1, Html::contar($x, "//aside//button[@data-bs-dismiss='offcanvas' and @aria-label='Cerrar el menú']"), "el cajón se cierra con un botón con nombre");
        $this->assertSame(1, Html::contar($x, "//header//button[@data-tema-toggle and @aria-label]"), "interruptor de tema");
        $this->assertSame(1, Html::contar($x, "//header//div[contains(@class,'dropdown')]/button[@data-bs-toggle='dropdown' and @aria-expanded='false' and contains(@aria-label,'Admin de prueba')]"), "menú de la cuenta con el nombre de la persona");
        $this->assertSame("A", trim($x->query("//header//span[contains(@class,'admin-cuenta-inicial')]")->item(0)->textContent));
    }

    public function testElMenuDeLaCuentaTieneTiendaYCerrarSesionPorPostYLaContrasenaVaEnLaBarraLateral(): void {
        $x = Html::xpath(Pantallas::render($this->db, "admin_dashboard"));
        $menu = "//header//ul[contains(@class,'dropdown-menu')]";
        $this->assertSame(0, $x->query("$menu//a[contains(@href,'mi_clave')]")->length, "la contraseña ya no está en el desplegable");
        $this->assertSame(["Ver la tienda", "Cerrar sesión"], array_map(fn($n) => trim($n->textContent), iterator_to_array($x->query("$menu//a | $menu//button"))));
        // Barra lateral: grupo «Cuenta» con la contraseña, antes del botón de contraer
        $grupo = "//aside//div[contains(@class,'admin-grupo')][.//p[normalize-space()='Cuenta']]";
        $clave = $x->query("$grupo//a[normalize-space()='Cambiar contraseña']");
        $this->assertSame(1, $clave->length);
        $this->assertStringContainsString("mod=usuarios&action=mi_clave", $clave->item(0)->getAttribute("href"));
        $this->assertSame(1, $x->query("$grupo//a//i[contains(@class,'bi-key')]")->length, "con su icono de llave");
        $this->assertSame(1, $x->query("$grupo/following::button[@data-panel-menu-toggle]")->length, "queda encima de «Contraer menú»");
        $this->assertSame(1, $x->query("//aside//p[normalize-space()='Cuenta']")->length);
        $tienda = $x->query("$menu//a[normalize-space()='Ver la tienda']");
        $this->assertSame(1, $tienda->length);
        $this->assertSame("", $tienda->item(0)->getAttribute("target"), "la tienda abre en la misma pestaña");
        $form = $x->query("$menu//form");
        $this->assertSame(1, $form->length);
        $this->assertSame("post", strtolower($form->item(0)->getAttribute("method")));
        $this->assertStringEndsWith("/logout.php", $form->item(0)->getAttribute("action"));
        $this->assertSame(1, Html::contar($x, "$menu//form//input[@type='hidden' and @name='csrf_token' and string-length(@value)>0]"), "con token CSRF");
        $boton = $x->query("$menu//form//button[@type='submit']")->item(0);
        $this->assertSame("Cerrar sesión", trim($boton->textContent));
        $this->assertStringContainsString("item-peligro", $boton->getAttribute("class"), "en el rojo de peligro");
        $this->assertSame(0, Html::contar($x, "//a[contains(@href,'logout.php')]"), "ningún enlace de salida por GET");
    }

    public function testElBotonParaContraerEstaSoloEnEscritorioYDiceSuEstado(): void {
        $x = Html::xpath(Pantallas::render($this->db, "admin_partes"));
        $b = $x->query("//aside//button[@data-panel-menu-toggle]");
        $this->assertSame(1, $b->length);
        $this->assertSame("true", $b->item(0)->getAttribute("aria-expanded"));
        $this->assertSame("panelMenu", $b->item(0)->getAttribute("aria-controls"));
        $this->assertSame("Contraer menú", trim($b->item(0)->textContent), "con texto, no solo icono");
        $this->assertStringContainsString("d-none d-lg-block", $b->item(0)->parentNode->getAttribute("class"));
    }

    public function testElMarcoCargaSuHojaYSuScriptYElContenedorEsElMain(): void {
        $h = Pantallas::render($this->db, "admin_partes");
        $this->assertMatchesRegularExpression('~<link rel="stylesheet" href="[^"]*css/panel\.css\?v=[^"]+">~', $h);
        $this->assertMatchesRegularExpression('~<head>.*<script src="[^"]*js/panel\.js\?v=[^"]+"></script>.*</head>~s', $h, "panel.js va en el head para fijar el estado antes de pintar");
        $x = Html::xpath($h);
        $this->assertSame(1, Html::contar($x, "//div[contains(@class,'admin-shell')]/div[contains(@class,'admin-main')]/main[@id='contenido' and contains(@class,'admin-contenido')]"));
        $this->assertSame(1, Html::contar($x, "//div[contains(@class,'admin-main')]/footer[contains(@class,'admin-footer')]"));
    }

    private function conRol(array $permisos): int {
        $uid = Datos::usuario($this->db);
        $this->db->prepare("INSERT INTO roles (nombre) VALUES (?)")->execute(["Rol S51 " . bin2hex(random_bytes(3))]);
        $rol = (int)$this->db->lastInsertId();
        foreach ($permisos as $modulo => $ver) $this->db->prepare("INSERT INTO permisos (rol_id, modulo, puede_ver) VALUES (?,?,?)")->execute([$rol, $modulo, $ver]);
        $this->db->prepare("INSERT INTO usuario_roles (usuario_id, rol_id) VALUES (?,?)")->execute([$uid, $rol]);
        return $uid;
    }

    public function testModulosVisiblesSoloListaLosQueTienenPuedeVer(): void {
        $uid = $this->conRol(["partes" => 1, "pedidos" => 1, "autos" => 0, "roles" => 0]);
        $this->assertEqualsCanonicalizing(["partes", "pedidos"], (new \UserModel())->modulosVisibles($uid));
        $this->assertSame([], (new \UserModel())->modulosVisibles(Datos::usuario($this->db)), "sin rol, nada");
    }

    public function testModulosVisiblesSumaLosRolesDeLaPersona(): void {
        $uid = $this->conRol(["partes" => 1, "autos" => 0]);
        $this->db->prepare("INSERT INTO roles (nombre) VALUES (?)")->execute(["Rol S51 b " . bin2hex(random_bytes(3))]);
        $rol2 = (int)$this->db->lastInsertId();
        $this->db->prepare("INSERT INTO permisos (rol_id, modulo, puede_ver) VALUES (?,?,1)")->execute([$rol2, "autos"]);
        $this->db->prepare("INSERT INTO usuario_roles (usuario_id, rol_id) VALUES (?,?)")->execute([$uid, $rol2]);
        $this->assertEqualsCanonicalizing(["partes", "autos"], (new \UserModel())->modulosVisibles($uid), "con un rol que lo permite basta");
    }

    private function renderComo(int $uid, string $mod = "dashboard", string $action = "", string $nombre = "Persona limitada"): \DOMXPath {
        $_SESSION["user"] = ["id" => $uid, "nombre" => $nombre, "email" => "limitada@example.invalid"];
        $_GET = ["mod" => $mod] + ($action !== "" ? ["action" => $action] : []);
        $title = "Pantalla de prueba";
        ob_start();
        try {
            include BASE_PATH . "/app/Views/layouts/admin_header.php";
            include BASE_PATH . "/app/Views/layouts/admin_footer.php";
        } finally {
            $html = (string)ob_get_clean();
            $_GET = [];
            unset($_SESSION["user"]);
        }
        $this->ultimoHtml = $html;
        return Html::xpath($html);
    }

    private string $ultimoHtml = "";

    public function testElMenuSoloMuestraLosModulosQueElRolPuedeVer(): void {
        $uid = $this->conRol(["partes" => 1, "pedidos" => 1, "ventas" => 0, "autos" => 0]);
        $x = $this->renderComo($uid, "partes");
        $textos = [];
        foreach ($x->query("//aside//nav//a") as $a) $textos[] = trim($a->textContent);
        $this->assertSame(["Dashboard", "Partes", "Pedidos", "Cambiar contraseña"], $textos, "el panel de inicio y la contraseña propia siempre; el resto según permisos");
        $grupos = [];
        foreach ($x->query("//aside//p[contains(@class,'admin-grupo-titulo')]") as $p) $grupos[] = trim($p->textContent);
        $this->assertSame(["General", "Catálogo", "Ventas", "Cuenta"], $grupos, "un grupo sin módulos no aparece; Cuenta es de todos");
    }

    public function testReportesSigueElPermisoDeVentas(): void {
        $x = $this->renderComo($this->conRol(["ventas" => 1]), "dashboard");
        $textos = [];
        foreach ($x->query("//aside//nav//a") as $a) $textos[] = trim($a->textContent);
        $this->assertSame(["Dashboard", "Ventas", "Reportes", "Cambiar contraseña"], $textos, "ReporteController exige el permiso de ventas");
    }

    public function testMiContrasenaNoMarcaNingunModuloYSeLlamaComoEs(): void {
        $x = $this->renderComo($this->conRol(["usuarios" => 1]), "usuarios", "mi_clave");
        $actuales = $x->query("//aside//a[@aria-current='page']");
        $this->assertSame(1, $actuales->length, "solo se marca su opción del grupo Cuenta, no el módulo Usuarios");
        $this->assertStringContainsString("mi_clave", $actuales->item(0)->getAttribute("href"));
        $this->assertSame("Mi contraseña", trim($x->query("//p[contains(@class,'admin-topbar-titulo')]")->item(0)->textContent));
        $this->assertSame(1, Html::contar($x, "//aside//a[@aria-current='page' and normalize-space()='Cambiar contraseña']"));
        $this->assertSame(0, Html::contar($x, "//header//a[@aria-current='page']"));
    }

    public function testElNombreDeLaPersonaSeEscapa(): void {
        $this->renderComo($this->conRol(["partes" => 1]), "partes", "", "<b>\"Eva\"</b>");
        $this->assertStringNotContainsString("<b>", $this->ultimoHtml);
        $this->assertStringContainsString("&lt;b&gt;&quot;Eva&quot;&lt;/b&gt;", $this->ultimoHtml);
    }

    // ── Hoja de estilos ──

    public function testEscritorioTieneRejillaDe248PxQueSeContraeA72(): void {
        $css = self::css();
        $this->assertMatchesRegularExpression('/--panel-menu-ancho:\s*248px/', $css);
        $this->assertMatchesRegularExpression('/html\[data-panel-menu="colapsado"\]\s*\{\s*--panel-menu-ancho:\s*72px/', $css);
        $this->assertMatchesRegularExpression('/@media \(min-width: 992px\)\s*\{\s*\.admin-shell\s*\{[^}]*grid-template-columns:\s*var\(--panel-menu-ancho\) minmax\(0, 1fr\)/', $css, "desde 992 px, que es donde Bootstrap deja de ser cajón");
        $this->assertMatchesRegularExpression('/@media \(min-width: 992px\)\s*\{[^@]*\.admin-sidebar\.offcanvas-lg\s*\{[^}]*position:\s*fixed[^}]*height:\s*100dvh/', $css, "fija a la altura de la ventana en escritorio");
    }

    public function testElEncabezadoMide64PxYElContenedorLlegaA1440ConRelleno16_24_32(): void {
        $css = self::css();
        $this->assertMatchesRegularExpression('/--panel-barra-alto:\s*64px/', $css);
        $this->assertMatchesRegularExpression('/height:\s*var\(--panel-barra-alto\)/', self::regla(".admin-topbar"));
        $this->assertMatchesRegularExpression('/height:\s*var\(--panel-barra-alto\)/', self::regla(".admin-sidebar-cabeza"), "la cabecera de la barra y el encabezado alinean");
        $c = self::regla(".admin-contenido");
        $this->assertMatchesRegularExpression('/max-width:\s*1440px/', $c);
        $this->assertMatchesRegularExpression('/padding:\s*var\(--spacing-lg\) var\(--spacing-md\)/', $c, "16 px en móvil");
        $this->assertMatchesRegularExpression('/@media \(min-width: 768px\) \{[^\n]*\.admin-contenido \{ padding-inline: var\(--spacing-lg\)/', $css, "24 px desde 768");
        $this->assertMatchesRegularExpression('/@media \(min-width: 1200px\) \{[^\n]*\.admin-contenido \{ padding-inline: var\(--spacing-xl\)/', $css, "32 px desde 1200");
    }

    public function testLasZonasSonDe40PxEnEscritorioY44EnTactilYMovil(): void {
        $css = self::css();
        $this->assertMatchesRegularExpression('/min-height:\s*40px/', self::regla(".admin-enlace"));
        $this->assertMatchesRegularExpression('/min-height:\s*40px/', self::regla(".admin-icono, .admin-cuenta"));
        $this->assertMatchesRegularExpression('/@media \(max-width: 991\.98px\), \(pointer: coarse\)\s*\{\s*\.admin-enlace, \.admin-icono, \.admin-cuenta, \.admin-cuenta-menu \.dropdown-item\s*\{\s*min-height:\s*44px/', $css);
        $this->assertMatchesRegularExpression('/\.admin-icono\s*\{\s*min-width:\s*44px/', $css);
    }

    public function testLaColumnaDeIconosEscondeElTextoSinQuitarloDelLectorDePantalla(): void {
        $css = self::css();
        $this->assertMatchesRegularExpression('/@media \(min-width: 992px\)\s*\{[^@]*html\[data-panel-menu="colapsado"\] \.admin-sidebar \.admin-enlace-texto[^{]*\{[^}]*clip:\s*rect\(0, 0, 0, 0\)/s', $css);
        $this->assertDoesNotMatchRegularExpression('/colapsado"\] \.admin-sidebar \.admin-enlace-texto[^{]*\{[^}]*display:\s*none/s', $css, "display:none lo quitaría del lector de pantalla");
    }

    public function testElActivoSeMarcaConBarraYPesoNoSoloConColor(): void {
        $a = self::regla(".admin-enlace.activo");
        $this->assertMatchesRegularExpression('/font-weight:\s*600/', $a);
        $this->assertMatchesRegularExpression('/background:\s*var\(--surface-elevated\)/', $a);
        $this->assertMatchesRegularExpression('/width:\s*3px/', self::regla(".admin-enlace.activo::before"));
        $this->assertMatchesRegularExpression('/background:\s*var\(--logo\)/', self::regla(".admin-enlace.activo::before"));
    }

    public function testElMovimientoEsSoloElDelCajonYNadaAnimaTamano(): void {
        $css = self::css();
        preg_match_all('/transition[^;{}]*;/', $css, $m);
        $this->assertSame(["transition: transform 250ms ease-out;"], $m[0], "una sola transición: el deslizamiento del cajón, 250 ms ease-out");
        preg_match_all('/animation\s*:[^;{}]*;/', $css, $an);
        $this->assertSame(["animation: modal-entra 200ms ease-out;"], $an[0], "una sola animación: la aparición del modal de confirmación (S5.2)");
        preg_match('/@keyframes modal-entra\s*\{((?:[^{}]*\{[^}]*\})+)\s*\}/', $css, $kf);
        $this->assertDoesNotMatchRegularExpression('/width|height|padding|margin|shadow/', $kf[1] ?? "x", "los fotogramas solo mueven opacidad y transform");
        $this->assertDoesNotMatchRegularExpression('/transition[^;]*(width|height|padding|grid)/', $css);
    }

    public function testLaHojaDelPanelSoloUsaTokensDeTema(): void {
        $css = (string)preg_replace('~/\*.*?\*/~s', "", self::css());
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,8}\b/', $css, "sin colores sueltos");
        $this->assertDoesNotMatchRegularExpression('/\brgba?\(/', $css);
    }

    public function testLasReglasViejasDeLaBarraSuperiorYaNoExisten(): void {
        foreach (["style.css", "extra.css", "base.css"] as $f) {
            $this->assertDoesNotMatchRegularExpression('/\.admin-nav|\.admin-content\b/', (string)file_get_contents(BASE_PATH . "/assets/css/$f"), $f);
        }
    }
}
