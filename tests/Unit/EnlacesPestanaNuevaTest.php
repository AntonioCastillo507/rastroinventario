<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Ninguna navegación interna abre pestaña nueva. Solo valen dos excepciones: un PDF (factura) y un enlace
 * externo, los dos con rel="noopener" y avisando a quien usa lector de pantalla. Además no hay window.open,
 * ni target en un formulario, ni un enlace que cierre la sesión.
 * Lee el código fuente (vistas, JS y controladores); EnlacesRenderizadosTest hace lo mismo con el HTML ya armado.
 */
final class EnlacesPestanaNuevaTest extends TestCase {
    /** @return list<string> problemas encontrados en $codigo (vacía si todo bien) */
    public static function revisar(string $codigo, string $archivo): array {
        $problemas = [];
        if (preg_match_all('/window\.open\s*\(/', $codigo, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as [, $pos]) $problemas[] = "$archivo:" . self::linea($codigo, $pos) . " window.open";
        }
        preg_match_all('/target\s*=\s*["\']_blank["\']/i', $codigo, $m, PREG_OFFSET_CAPTURE);
        foreach ($m[0] as [, $pos]) {
            $linea = self::linea($codigo, $pos);
            // la etiqueta que contiene el target: del "<" que la abre al primer ">" que no cierre un bloque de PHP
            $ini = strrpos(substr($codigo, 0, $pos), "<");
            while ($ini !== false && substr($codigo, $ini, 2) === "<?") $ini = strrpos(substr($codigo, 0, $ini), "<");
            preg_match('/(?<!\?)>/', substr($codigo, $pos), $fin, PREG_OFFSET_CAPTURE);
            $tag = substr($codigo, (int)$ini, $pos - (int)$ini + (int)($fin[0][1] ?? 0) + 1);
            if (!preg_match('/^<a\b/i', $tag)) { $problemas[] = "$archivo:$linea target=\"_blank\" fuera de un <a>"; continue; }
            preg_match('/href\s*=\s*"([^"]*)"/i', $tag, $h);
            $href = $h[1] ?? "";
            // externo: empieza con http(s):// o con una variable de PHP que no es APP_URL (p. ej. la URL de una red social)
            $externo = (bool)preg_match('~^(https?:)?//~i', $href) || (str_starts_with($href, "<?") && !str_contains($href, "APP_URL"));
            if (!$externo && !preg_match('/(action=factura|mod=factura)/', $href)) $problemas[] = "$archivo:$linea enlace interno en pestaña nueva: $href";
            if (!preg_match('/rel\s*=\s*"[^"]*noopener/i', $tag)) $problemas[] = "$archivo:$linea sin rel=\"noopener\": $href";
            if (!preg_match('/pestaña nueva|otra pestaña/iu', substr($codigo, (int)$ini, 700))) $problemas[] = "$archivo:$linea sin aviso accesible de pestaña nueva: $href";
        }
        if (preg_match_all('/href\s*=\s*"[^"]*logout\.php[^"]*"/i', $codigo, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as [, $pos]) $problemas[] = "$archivo:" . self::linea($codigo, $pos) . " enlace a logout.php (Salir es un formulario POST)";
        }
        return $problemas;
    }

    private static function linea(string $codigo, int $pos): int {
        return substr_count(substr($codigo, 0, $pos), "\n") + 1;
    }

    /** @return list<string> */
    private static function archivos(): array {
        $out = [];
        foreach (["app/Views", "app/Controllers"] as $d) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(BASE_PATH . "/$d", \FilesystemIterator::SKIP_DOTS)) as $f) {
                if ($f->getExtension() === "php") $out[] = $f->getPathname();
            }
        }
        foreach (glob(BASE_PATH . "/assets/js/*.js") ?: [] as $f) $out[] = $f;
        foreach (["index.php", "login.php", "registro.php", "olvide.php", "restablecer.php", "logout.php", "bootstrap.php"] as $f) $out[] = BASE_PATH . "/$f";
        return $out;
    }

    public function testEnTodoElCodigoNingunEnlaceInternoAbrePestanaNueva(): void {
        $archivos = self::archivos();
        $this->assertGreaterThan(40, count($archivos));
        $problemas = [];
        foreach ($archivos as $f) {
            foreach (self::revisar((string)file_get_contents($f), str_replace([BASE_PATH . "/", BASE_PATH . "\\"], "", $f)) as $p) $problemas[] = $p;
        }
        $this->assertSame([], $problemas, "Enlaces con pestaña nueva mal usados:\n" . implode("\n", $problemas));
    }

    public function testNingunFormularioUsaTarget(): void {
        foreach (self::archivos() as $f) {
            $this->assertDoesNotMatchRegularExpression('/<form\b[^>]*\btarget\s*=/i', (string)file_get_contents($f), "$f: un <form> con target");
        }
    }

    public function testLasPestanasNuevasQueQuedanSonPdfOExternas(): void {
        // las que sí existen: facturas (PDF) y redes sociales (externas)
        $hallados = 0;
        foreach (self::archivos() as $f) $hallados += preg_match_all('/target="_blank"/', (string)file_get_contents($f));
        $this->assertGreaterThanOrEqual(4, $hallados, "Deben seguir las facturas PDF y las redes externas");
    }

    // ── El detector mismo: cada mutación tiene que saltar ──

    public function testDetectaElBotonTiendaEnPestanaNueva(): void {
        $this->assertNotSame([], self::revisar('<a href="<?=APP_URL?>/" class="btn" target="_blank">Tienda</a>', "x.php"));
    }

    public function testDetectaUnHrefRelativoDelCarritoEnPestanaNueva(): void {
        $this->assertNotSame([], self::revisar('<a href="/index.php?action=carrito" target="_blank" rel="noopener noreferrer">Carrito (pestaña nueva)</a>', "x.php"));
    }

    public function testDetectaUnEnlaceDelMenuEnPestanaNueva(): void {
        $this->assertNotSame([], self::revisar('<a class="nav-link" href="<?=APP_URL.htmlspecialchars($ruta)?>" target="_blank" rel="noopener">x</a>', "x.php"));
    }

    public function testDetectaUnPdfSinRelNoopener(): void {
        $this->assertNotSame([], self::revisar('<a href="<?=APP_URL?>/?action=factura&pedido=1" target="_blank">Factura (pestaña nueva)</a>', "x.php"));
    }

    public function testDetectaUnPdfSinAvisoAccesible(): void {
        $this->assertNotSame([], self::revisar('<a href="<?=APP_URL?>/?action=factura&pedido=1" target="_blank" rel="noopener noreferrer">Factura</a>', "x.php"));
    }

    public function testAceptaUnPdfBienMarcadoYUnaRedExterna(): void {
        $this->assertSame([], self::revisar('<a href="<?=APP_URL?>/admin/?mod=factura&action=generar&id=1" target="_blank" rel="noopener noreferrer">Factura<span class="visually-hidden"> (PDF, se abre en una pestaña nueva)</span></a>', "x.php"));
        $this->assertSame([], self::revisar('<a href="https://www.facebook.com/" target="_blank" rel="noopener noreferrer">Facebook<span class="visually-hidden"> (se abre en otra pestaña)</span></a>', "x.php"));
        $this->assertSame([], self::revisar('<a href="<?=htmlspecialchars($r["url"])?>" target="_blank" rel="noopener noreferrer">x<span> (se abre en otra pestaña)</span></a>', "x.php"));
    }

    public function testDetectaWindowOpenYUnEnlaceAlLogout(): void {
        $this->assertNotSame([], self::revisar("window.open('/x');", "x.js"));
        $this->assertNotSame([], self::revisar('<a href="<?=APP_URL?>/logout.php">Salir</a>', "x.php"));
    }
}
