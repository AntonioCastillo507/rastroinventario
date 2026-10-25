<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guarda estática (diseño, sub-paso 1): ninguna vista, hoja de estilos ni script carga recursos de un
 * dominio externo (CDN, fuentes de Google, etc.). Todo vive en assets/. Si una vista vuelve a apuntar
 * a un recurso externo, o a un archivo de assets/ que no existe, esta prueba falla.
 * Los enlaces <a href="https://..."> no cuentan: navegar a otro sitio no es cargar un recurso.
 */
final class RecursosAutoalojadosTest extends TestCase {
    /** Etiquetas que descargan algo al abrir la página */
    private const ETIQUETAS = '(?:link|script|img|iframe|source|video|audio|embed|object|input|track)';

    /** @return list<string> */
    private static function archivosPhp(): array {
        $out = [];
        foreach (["app", "admin"] as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(BASE_PATH . "/$dir", \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->getExtension() === "php") $out[] = $f->getPathname();
            }
        }
        foreach (glob(BASE_PATH . "/*.php") as $f) $out[] = $f;
        sort($out);
        return $out;
    }

    /** @return list<string> hojas de estilo y scripts propios (no los de vendor/) */
    private static function archivosPropios(): array {
        return array_merge(
            glob(BASE_PATH . "/assets/css/*.css"),
            glob(BASE_PATH . "/assets/fonts/*.css"),
            glob(BASE_PATH . "/assets/js/*.js")
        );
    }

    /** Quita las etiquetas PHP para que su '>' no corte las etiquetas HTML */
    private static function sinPhp(string $codigo): string {
        return (string)preg_replace('/<\?(?:php|=).*?\?>/s', "PHP", $codigo);
    }

    /** @return list<string> URL externas que carga el HTML dado */
    public static function externosEnHtml(string $codigo): array {
        $t = self::sinPhp($codigo);
        preg_match_all('/<' . self::ETIQUETAS . '\b[^>]*?\b(?:src|href|data|poster)\s*=\s*["\']\s*((?:https?:)?\/\/[^"\']+)/i', $t, $m);
        return $m[1];
    }

    /** @return list<string> URL externas que carga el CSS dado (url(...) e @import) */
    public static function externosEnCss(string $codigo): array {
        preg_match_all('/url\(\s*["\']?\s*((?:https?:)?\/\/[^)"\']+)/i', $codigo, $a);
        preg_match_all('/@import\s+(?:url\()?\s*["\']\s*((?:https?:)?\/\/[^"\']+)/i', $codigo, $b);
        return array_values(array_unique(array_merge($a[1], $b[1])));
    }

    public function testDetectorEncuentraRecursosExternos(): void {
        $this->assertSame(["https://cdn.x.com/a.css"], self::externosEnHtml('<link rel="stylesheet" href="https://cdn.x.com/a.css">'));
        $this->assertSame(["//cdn.x.com/a.js"], self::externosEnHtml('<script src="//cdn.x.com/a.js"></script>'));
        $this->assertSame(["http://x.com/i.png"], self::externosEnHtml('<img alt="a" src="http://x.com/i.png">'));
        $this->assertSame(["https://cdn.x.com/b.css"], self::externosEnHtml('<link href="<?=APP_URL?>/a.css" rel="x"><link href="https://cdn.x.com/b.css">'));
        $this->assertSame([], self::externosEnHtml('<a href="https://wa.me/507">Chat</a><link href="<?=APP_URL?>/assets/a.css">'));
        $this->assertSame(["https://fonts.gstatic.com/a.woff2"], self::externosEnCss('@font-face{src:url("https://fonts.gstatic.com/a.woff2")}'));
        $this->assertSame(["https://fonts.googleapis.com/css"], self::externosEnCss('@import url("https://fonts.googleapis.com/css");'));
        $this->assertSame([], self::externosEnCss("a{background:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'%3E\")}"));
    }

    public function testNingunaVistaCargaRecursosExternos(): void {
        $fallos = [];
        foreach (self::archivosPhp() as $ruta) {
            foreach (self::externosEnHtml((string)file_get_contents($ruta)) as $url) {
                $fallos[] = substr($ruta, strlen(BASE_PATH) + 1) . " → $url";
            }
        }
        $this->assertSame([], $fallos, "Recursos externos en vistas: " . implode("; ", $fallos));
    }

    public function testNingunaHojaDeEstilosNiScriptCargaRecursosExternos(): void {
        $fallos = [];
        foreach (self::archivosPropios() as $ruta) {
            $codigo = (string)file_get_contents($ruta);
            $urls = str_ends_with($ruta, ".js")
                ? array_merge(self::externosEnCss($codigo), self::externosEnHtml($codigo))
                : self::externosEnCss($codigo);
            foreach ($urls as $url) $fallos[] = basename($ruta) . " → $url";
        }
        $this->assertSame([], $fallos, "Recursos externos en assets: " . implode("; ", $fallos));
    }

    public function testLosRecursosLocalesDeLasVistasExisten(): void {
        $faltan = [];
        $revisados = 0;
        foreach (self::archivosPhp() as $ruta) {
            $t = (string)file_get_contents($ruta);
            preg_match_all('/<' . self::ETIQUETAS . '\b[^>]*?\b(?:src|href)\s*=\s*["\']<\?=\s*APP_URL\s*\?>\/(assets\/[^"\'?#]+)/i', $t, $m);
            // los archivos propios se piden con Assets::url("css/x.css"), que añade ?v=
            preg_match_all('/<' . self::ETIQUETAS . '\b[^>]*?\b(?:src|href)\s*=\s*["\']<\?=\s*Assets::url\("([^"]+)"\)\s*\?>/i', $t, $propios);
            foreach (array_unique(array_merge($m[1], array_map(fn($r) => "assets/" . $r, $propios[1]))) as $rel) {
                $revisados++;
                if (!is_file(BASE_PATH . "/" . $rel)) $faltan[] = basename($ruta) . " → $rel";
            }
        }
        $this->assertGreaterThan(8, $revisados);
        $this->assertSame([], $faltan, "Archivos de assets/ que no existen: " . implode("; ", $faltan));
    }

    public function testLasPlantillasUsanElParcialDeRecursos(): void {
        $parcial = (string)file_get_contents(BASE_PATH . "/app/Views/layouts/recursos_head.php");
        foreach (["bootstrap/css/bootstrap.min.css", "bootstrap-icons/bootstrap-icons.min.css", "fonts/fuentes.css"] as $r) {
            $this->assertStringContainsString($r, $parcial);
        }
        foreach (["auth/login", "auth/registro", "auth/olvide", "auth/restablecer", "layouts/public_header", "layouts/admin_header"] as $v) {
            $this->assertStringContainsString("recursos_head.php", (string)file_get_contents(BASE_PATH . "/app/Views/$v.php"), $v);
        }
        foreach (["layouts/public_footer", "layouts/admin_footer"] as $v) {
            $this->assertStringContainsString("/assets/vendor/bootstrap/js/bootstrap.bundle.min.js", (string)file_get_contents(BASE_PATH . "/app/Views/$v.php"), $v);
        }
    }

    public function testFuentesYLicenciasEstanEnElProyecto(): void {
        foreach ([
            "assets/fonts/manrope-latin-wght-normal.woff2", "assets/fonts/manrope-latin-ext-wght-normal.woff2",
            "assets/fonts/ibm-plex-sans-latin-wght-normal.woff2", "assets/fonts/ibm-plex-sans-latin-ext-wght-normal.woff2",
            "assets/fonts/OFL-Manrope.txt", "assets/fonts/OFL-IBM-Plex-Sans.txt",
            "assets/vendor/bootstrap/css/bootstrap.min.css", "assets/vendor/bootstrap/js/bootstrap.bundle.min.js",
            "assets/vendor/bootstrap-icons/bootstrap-icons.min.css",
            "assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff2", "assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff",
        ] as $r) {
            $this->assertFileExists(BASE_PATH . "/$r");
        }
        foreach (["OFL-Manrope", "OFL-IBM-Plex-Sans"] as $l) {
            $this->assertStringContainsString("SIL OPEN FONT LICENSE", (string)file_get_contents(BASE_PATH . "/assets/fonts/$l.txt"));
        }
        $css = (string)file_get_contents(BASE_PATH . "/assets/fonts/fuentes.css");
        $this->assertSame(4, preg_match_all('/@font-face/', $css));
        $this->assertSame(4, preg_match_all('/font-display:\s*swap/', $css));
        foreach (glob(BASE_PATH . "/assets/fonts/*.woff2") as $f) {
            $this->assertStringContainsString(basename($f), $css, "fuentes.css no declara " . basename($f));
            $this->assertSame("wOF2", substr((string)file_get_contents($f), 0, 4), basename($f) . " no es un WOFF2");
        }
    }
}
