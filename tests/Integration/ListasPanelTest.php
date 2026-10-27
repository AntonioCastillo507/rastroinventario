<?php
namespace Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BaseDatos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/**
 * S5.3: listas del panel con un mismo componente (tabla densa en escritorio, tarjeta en móvil), con Partes como modelo.
 */
final class ListasPanelTest extends BaseDatos {
    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
    }

    /** @return array<string,array{0:string,1:string,2:list<string>,3:string}> pantalla => [pantalla, vista, columnas, título] */
    public static function listas(): array {
        return [
            "partes" => ["admin_partes", "partes", ["Pieza", "Precio", "Stock", "Acciones"], "Inventario de partes"],
            "pedidos" => ["admin_pedidos", "pedidos", ["Pedido", "Cliente", "Total", "Estado", "Fecha", "Acciones"], "Pedidos de la tienda"],
            "usuarios" => ["admin_usuarios", "usuarios", ["Usuario", "Roles", "Estado", "Creado", "Acciones"], "Usuarios del sistema"],
            "comentarios" => ["admin_comentarios", "comentarios", ["Comentario", "Calificación", "Estado", "Fecha", "Acciones"], "Moderación de comentarios"],
            "roles" => ["admin_roles", "roles", ["Rol", "Descripción", "Acciones"], "Roles y permisos"],
            "autos" => ["admin_autos", "autos", ["Auto", "Año", "Estado", "Acciones"], "Catálogo de autos"],
            "secciones" => ["admin_secciones", "secciones", ["Sección", "Estado", "Acciones"], "Secciones del almacén"],
            "ventas" => ["admin_ventas", "ventas", ["Pieza", "Cant.", "Total", "Vendedor", "Firma", "Fecha"], "Registro de ventas"],
        ];
    }

    #[DataProvider("listas")]
    public function testLaListaUsaElComponenteConSusColumnasYSuTitulo(string $pantalla, string $vista, array $columnas, string $titulo): void {
        $x = Html::xpath(Pantallas::render($this->db, $pantalla));
        $this->assertSame(1, Html::contar($x, "//div[contains(@class,'lista-card')]/table[contains(@class,'lista-tabla')]"), "$pantalla: tabla dentro de su tarjeta");
        $this->assertSame(0, Html::contar($x, "//*[contains(@class,'table-responsive') or contains(@class,'dark-table')]"), "$pantalla: sin la tabla vieja que se desliza de lado");
        $ths = [];
        foreach ($x->query("//table[contains(@class,'lista-tabla')]/thead/tr/th") as $th) {
            $this->assertSame("col", $th->getAttribute("scope"), "$pantalla: cabecera con scope");
            $ths[] = trim($th->textContent);
        }
        $this->assertSame($columnas, $ths, "$pantalla: columnas");
        $this->assertLessThanOrEqual(6, count($ths), "UX 3.6: como mucho 6 columnas en escritorio");
        $this->assertSame(1, Html::contar($x, "//h1[contains(@class,'panel-titulo')]"), "$pantalla: un h1");
        $this->assertStringStartsWith($titulo, trim($x->query("//h1")->item(0)->textContent));
        $this->assertSame(0, Html::contar($x, "//h1//i"), "$pantalla: sin icono en el título (DESIGN.md)");
        $conAcciones = end($columnas) === "Acciones";
        $this->assertSame($conAcciones ? 1 : 0, Html::contar($x, "//th[contains(@class,'col-acciones')]"), "$pantalla: acciones al final, si las hay");
        if ($conAcciones) $this->assertStringContainsString("col-acciones", $x->query("//table[contains(@class,'lista-tabla')]/thead/tr/th[last()]")->item(0)->getAttribute("class"));
    }

    #[DataProvider("listas")]
    public function testCadaCeldaDeDatosLlevaElDataLabelDeSuColumna(string $pantalla, string $vista, array $columnas): void {
        $x = Html::xpath(Pantallas::render($this->db, $pantalla));
        $filas = $x->query("//table[contains(@class,'lista-tabla')]/tbody/tr[not(contains(@class,'lista-vacia'))]");
        $this->assertGreaterThan(0, $filas->length, "$pantalla: hay filas con las que probar");
        foreach ($filas as $tr) {
            $q = new \DOMXPath($tr->ownerDocument);
            $tds = $q->query("./td", $tr);
            $this->assertSame(count($columnas), $tds->length, "$pantalla: tantas celdas como columnas");
            foreach ($tds as $i => $td) {
                $clases = " " . $td->getAttribute("class") . " ";
                if ($i === 0) { $this->assertStringContainsString(" celda-principal ", $clases, "$pantalla: la primera celda es la principal de la tarjeta"); continue; }
                if (str_contains($clases, " col-acciones ")) { $this->assertSame(count($columnas) - 1, $i); $this->assertFalse($td->hasAttribute("data-label")); continue; }
                $this->assertSame($columnas[$i], $td->getAttribute("data-label"), "$pantalla: data-label de la celda $i (es lo que muestra la tarjeta móvil)");
            }
        }
    }

    #[DataProvider("listas")]
    public function testLasAccionesTienenNombreAccesibleIconoOcultoYTextoParaLaTarjeta(string $pantalla): void {
        $x = Html::xpath(Pantallas::render($this->db, $pantalla));
        $botones = $x->query("//td[contains(@class,'col-acciones')]//*[contains(@class,'accion-btn')]");
        if ($pantalla === "admin_ventas") { $this->assertSame(0, $botones->length, "las ventas no tienen acciones"); return; }
        $this->assertGreaterThan(0, $botones->length, $pantalla);
        foreach ($botones as $b) {
            $q = new \DOMXPath($b->ownerDocument);
            $nombre = $b->getAttribute("aria-label");
            $this->assertNotSame("", $nombre, "$pantalla: nombre accesible");
            $this->assertSame(1, $q->query("./i[@aria-hidden='true']", $b)->length, "$pantalla: icono decorativo");
            $texto = trim($q->query("./span[contains(@class,'accion-texto')]", $b)->item(0)->textContent ?? "");
            $this->assertNotSame("", $texto, "$pantalla: el texto de la acción para la tarjeta");
            $this->assertStringStartsWith($texto, $nombre, "$pantalla: el nombre accesible empieza por el texto visible (WCAG 2.5.3)");
            $this->assertSame(0, $q->query(".//*[contains(@class,'text-warning') or contains(@class,'text-danger') or contains(@class,'text-success')]", $b)->length, "$pantalla: el color sale de la clase del componente");
        }
        foreach ($x->query("//td[contains(@class,'col-acciones')]//form") as $f) {
            $this->assertSame("POST", strtoupper($f->getAttribute("method")));
            $this->assertSame(1, (new \DOMXPath($f->ownerDocument))->query(".//input[@name='csrf_token']", $f)->length, "$pantalla: cada acción que cambia datos lleva su token");
        }
    }

    public function testPartesPonePrecioACadaFilaConElTokenYLaPiezaConAutoYSeccion(): void {
        $x = Html::xpath(Pantallas::render($this->db, "admin_partes"));
        $this->assertGreaterThan(0, Html::contar($x, "//td[contains(@class,'col-num')]/span[contains(@class,'text-precio') and contains(@class,'num')]"));
        foreach ($x->query("//td[contains(@class,'celda-principal')]") as $td) {
            $q = new \DOMXPath($td->ownerDocument);
            $this->assertSame(1, $q->query(".//span[contains(@class,'foto-baldosa')]", $td)->length, "foto 48 en su baldosa");
            $sub = trim($q->query(".//span[contains(@class,'celda-sub')]", $td)->item(0)->textContent);
            $this->assertStringContainsString(" · ", $sub, "auto y sección como segunda línea");
        }
        $this->assertSame(0, Html::contar($x, "//th[normalize-space()='Firma' or normalize-space()='#' or normalize-space()='Img']"), "id, firma e imagen ya no son columnas (UX 5.7)");
        $this->assertMatchesRegularExpression('/\(\d+\)/', trim($x->query("//h1")->item(0)->textContent), "el total de piezas en el título");
    }

    public function testPedidosYaNoMuestraElTelefonoNiElCorreoComoColumna(): void {
        $x = Html::xpath(Pantallas::render($this->db, "admin_pedidos"));
        $this->assertSame(0, Html::contar($x, "//th[normalize-space()='Email' or normalize-space()='Teléfono']"));
        $this->assertGreaterThan(0, Html::contar($x, "//td[@data-label='Cliente']/span[contains(@class,'celda-sub')]"), "el correo va como segunda línea del cliente");
    }

    public function testElFiltroDePartesEsUnGrupoDeCampoYBotonConDosSelectsYLimpiarSoloConFiltros(): void {
        $x = Html::xpath(Pantallas::render($this->db, "admin_partes"));
        $this->assertSame(1, Html::contar($x, "//form[@id='fAdm' and contains(@class,'lista-filtros')]/div[contains(@class,'input-group')]/input[@type='search' and @aria-label]"));
        $this->assertSame(1, Html::contar($x, "//form[@id='fAdm']/div[contains(@class,'input-group')]/button[@type='submit' and @aria-label='Buscar']"));
        $this->assertSame(2, Html::contar($x, "//form[@id='fAdm']/select[@aria-label]"));
        $this->assertSame(0, Html::contar($x, "//form[@id='fAdm']//*[@onchange]"), "el cambio de los selects lo maneja main.js, no un atributo en línea");
        $this->assertSame(0, Html::contar($x, "//a[contains(@class,'lista-filtros-limpiar')]"), "sin filtros activos no hay enlace de limpiar");
        $src = (string)file_get_contents(BASE_PATH . "/app/Views/admin/partes/index.php");
        $this->assertStringContainsString('class="lista-filtros-limpiar">Limpiar filtros</a>', $src);
        $this->assertMatchesRegularExpression('/<\?php if\(\(\$q\?\?""\)!==""\|\|\(\$filtro\?\?"all"\)!=="all"\|\|\(\$orden\?\?"recientes"\)!=="recientes"\): \?>\s*<a .*lista-filtros-limpiar/', $src);
    }

    public function testElEstadoVacioUsaElComponenteYOcupaTodasLasColumnasDeSuLista(): void {
        foreach (self::listas() as [$pantalla, $vista, $columnas]) {
            $src = (string)file_get_contents(BASE_PATH . "/app/Views/admin/$vista/index.php");
            if ($vista === "comentarios") { // sin Acciones para quien no es Administrador: 4 o 5 columnas
                $this->assertStringContainsString('$cols=$puedeModerar?5:4;', $src);
                $this->assertStringContainsString('$vaciaColspan=$cols; $vaciaTitulo=', $src);
            } else {
                $this->assertSame(1, preg_match('/\$vaciaColspan=(\d+); \$vaciaTitulo=/', $src, $m), "$vista: estado vacío con el componente");
                $this->assertSame(count($columnas), (int)$m[1], "$vista: colspan igual al número de columnas");
            }
            $this->assertStringContainsString('include BASE_PATH."/app/Views/layouts/lista_vacia.php"', $src, $vista);
            $this->assertStringNotContainsString("text-center text-secondary", $src, "$vista: sin la fila vieja");
        }
    }

    public function testElComponenteDeEstadoVacioDiceQueHacerConUnSoloBotonYEscapaTodo(): void {
        $render = function (array $v): string {
            extract($v); ob_start(); include BASE_PATH . "/app/Views/layouts/lista_vacia.php"; return (string)ob_get_clean();
        };
        $x = Html::xpath("<table><tbody>" . $render(["vaciaColspan" => 5, "vaciaTitulo" => "Sin <b>x</b>", "vaciaTexto" => "Texto & más", "vaciaAccion" => ["/a?b=1&c=2", "Hacer <i>algo</i>"]]) . "</tbody></table>");
        $this->assertSame("5", $x->query("//tr[@class='lista-vacia']/td")->item(0)->getAttribute("colspan"));
        $this->assertSame("Sin <b>x</b>", $x->query("//p[contains(@class,'vacio-titulo')]")->item(0)->textContent, "se escapa: llega como texto");
        $this->assertSame(0, $x->query("//b | //i")->length);
        $this->assertSame(1, $x->query("//div[@class='vacio']/a[contains(@class,'btn-gold')]")->length);
        $this->assertSame("/a?b=1&c=2", $x->query("//a")->item(0)->getAttribute("href"));
        $sin = Html::xpath("<table><tbody>" . $render(["vaciaColspan" => 2, "vaciaTitulo" => "T", "vaciaTexto" => "U"]) . "</tbody></table>");
        $this->assertSame(0, $sin->query("//a")->length, "sin acción no hay botón");
        $this->assertSame(1, $sin->query("//p[contains(@class,'vacio-texto')]")->length);
    }

    public function testLasListasYaNoUsanLosIconosDeColorNiLasClasesViejas(): void {
        foreach (self::listas() as [$pantalla, $vista]) {
            $src = (string)file_get_contents(BASE_PATH . "/app/Views/admin/$vista/index.php");
            foreach (["icon-btn", "dark-table", "table-responsive", "page-header", "text-warning", "fw-bold text-white", "badge-role"] as $viejo) {
                $this->assertStringNotContainsString($viejo, $src, "$vista: ya no usa $viejo");
            }
        }
    }

    // ── Hoja de estilos ──

    private static function css(): string { return (string)file_get_contents(BASE_PATH . "/assets/css/lista.css"); }

    private static function regla(string $selector, ?string $css = null): string {
        preg_match('/(?:^|[}\s{])' . preg_quote($selector, "/") . '\s*\{([^}]*)\}/m', $css ?? self::css(), $m);
        return $m[1] ?? "";
    }

    /** el texto del bloque @media de nivel superior que empieza por $consulta */
    private static function media(string $consulta): string {
        $css = self::css();
        $ini = strpos($css, "@media " . $consulta);
        if ($ini === false) return "";
        $prof = 0;
        for ($i = strpos($css, "{", $ini), $n = strlen($css); $i < $n; $i++) {
            if ($css[$i] === "{") $prof++;
            elseif ($css[$i] === "}" && --$prof === 0) return substr($css, $ini, $i - $ini + 1);
        }
        return "";
    }

    public function testLaTablaSigueLaDensidadDeUx36(): void {
        $this->assertMatchesRegularExpression('/height:\s*44px/', self::regla(".lista-tabla th"), "cabecera de 44 px");
        $this->assertMatchesRegularExpression('/font-size:\s*var\(--type-body-sm-size\)/', self::regla(".lista-tabla th"), "13 px");
        $this->assertStringNotContainsString("uppercase", self::regla(".lista-tabla th"), "sin mayúsculas forzadas");
        $this->assertMatchesRegularExpression('/position:\s*sticky;\s*top:\s*var\(--panel-barra-alto\)/', self::regla(".lista-tabla th"), "fija al desplazar, debajo del encabezado del panel");
        $this->assertMatchesRegularExpression('/height:\s*56px/', self::regla(".lista-tabla tbody tr"), "filas de 56 px");
        $this->assertMatchesRegularExpression('/padding:\s*var\(--spacing-xs\) var\(--spacing-md\)/', self::regla(".lista-tabla td"), "8 px de alto y 16 de lado en la celda; con la foto de 48 la fila llega a 64");
        $this->assertMatchesRegularExpression('/text-align:\s*right/', self::regla(".lista-tabla .col-num"), "números a la derecha");
        $this->assertMatchesRegularExpression('/border-radius:\s*var\(--rounded-xl\)/', self::regla(".lista-card"), "data-table-card: radio xl");
    }

    public function testLosBotonesDeAccionMiden40Y44EnTactil(): void {
        $this->assertMatchesRegularExpression('/min-width:\s*40px;\s*min-height:\s*40px/', self::regla(".accion-btn"));
        $this->assertMatchesRegularExpression('/@media \(pointer: coarse\)\s*\{\s*\.accion-btn\s*\{\s*min-width:\s*44px;\s*min-height:\s*44px/', self::css());
    }

    public function testBajoLos768PxCadaFilaEsUnaTarjetaConDataLabelYBotonesDe44(): void {
        $m = self::media("(max-width: 767.98px)");
        $this->assertNotSame("", $m);
        $this->assertMatchesRegularExpression('/\.lista-tabla, \.lista-tabla tbody, \.lista-tabla tr, \.lista-tabla td\s*\{\s*display:\s*block/', $m);
        $this->assertMatchesRegularExpression('/\.lista-tabla td\[data-label\]::before\s*\{\s*content:\s*attr\(data-label\)/', $m, "la etiqueta sale del HTML, no se duplica");
        $this->assertMatchesRegularExpression('/\.lista-tabla thead\s*\{\s*position:\s*absolute;[^}]*clip:\s*rect\(0, 0, 0, 0\)/', $m, "encabezados fuera de vista pero para el lector de pantalla");
        $this->assertStringNotContainsString("display: none", $m, "nada se oculta con display:none en la tarjeta");
        $this->assertMatchesRegularExpression('/border-radius:\s*var\(--rounded-lg\)/', $m, "admin-card-mobile: radio lg");
        $this->assertMatchesRegularExpression('/\.accion-btn\s*\{[^}]*min-height:\s*44px/', $m);
        $this->assertMatchesRegularExpression('/\.accion-texto\s*\{\s*position:\s*static/', $m, "el nombre de la acción se ve en la tarjeta");
        $this->assertMatchesRegularExpression('/\.lista-acciones\s*\{\s*display:\s*grid/', $m, "las acciones siempre visibles, en una fila");
    }

    public function testElTextoDeLaAccionSoloEstaOcultoEnEscritorioConLaTecnicaDelLectorDePantalla(): void {
        $r = self::regla(".accion-texto");
        $this->assertMatchesRegularExpression('/clip:\s*rect\(0, 0, 0, 0\)/', $r);
        $this->assertStringNotContainsString("display: none", $r);
    }

    public function testLosFiltrosVanDeADosEnMovilYEnUnaFilaDesdeTableta(): void {
        $this->assertMatchesRegularExpression('/grid-template-columns:\s*repeat\(2, minmax\(0, 1fr\)\)/', self::regla(".lista-filtros"));
        $this->assertMatchesRegularExpression('/grid-column:\s*1 \/ -1/', self::regla(".lista-filtros-buscar"), "el buscador ocupa la fila");
        $this->assertMatchesRegularExpression('/@media \(min-width: 768px\)\s*\{[^@]*\.lista-filtros\s*\{\s*grid-template-columns:\s*minmax\(220px, 2fr\) minmax\(0, 1fr\) minmax\(0, 1fr\) auto/', self::css());
        $this->assertMatchesRegularExpression('/min-height:\s*44px/', self::regla(".lista-filtros-limpiar"), "el enlace de limpiar también es táctil");
    }

    public function testLaHojaDeListasSoloUsaTokensYNoAnimaNada(): void {
        $css = (string)preg_replace('~/\*.*?\*/~s', "", self::css());
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,8}\b/', $css, "sin colores sueltos");
        $this->assertDoesNotMatchRegularExpression('/\brgba?\(/', $css);
        $this->assertDoesNotMatchRegularExpression('/transition|animation/', $css, "las listas no se animan");
        $this->assertStringContainsString("var(--danger-text)", self::regla(".accion-peligro"), "el rojo de la acción destructiva es el token de texto de error");
    }

    public function testElEstadoVacioSeCentraSinAlturaFijaYSinHover(): void {
        $this->assertMatchesRegularExpression('/text-align:\s*center/', self::regla(".lista-vacia td"));
        $this->assertMatchesRegularExpression('/padding:\s*var\(--spacing-xl\) var\(--spacing-md\)/', self::regla(".lista-vacia td"));
        $this->assertMatchesRegularExpression('/max-width:\s*40ch/', self::regla(".vacio"));
        $this->assertMatchesRegularExpression('/color:\s*var\(--text-strong\)/', self::regla(".vacio-titulo"));
        $this->assertMatchesRegularExpression('/color:\s*var\(--text-muted\)/', self::regla(".vacio-texto"));
        $this->assertMatchesRegularExpression('/background:\s*none/', self::regla(".lista-tabla tbody tr.lista-vacia:hover > td"), "una fila vacía no reacciona al cursor");
    }

    public function testElMarcoCargaListasCssDespuesDePanelCss(): void {
        $h = Pantallas::render($this->db, "admin_partes");
        $this->assertMatchesRegularExpression('~css/panel\.css\?v=[^"]+">\s*<link rel="stylesheet" href="[^"]*css/lista\.css\?v=[^"]+">~', $h);
    }
}
