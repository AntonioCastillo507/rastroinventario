<?php
namespace Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BaseDatos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/**
 * Los CSS y JS propios se piden con ?v=<fecha de modificación del archivo> para que el navegador no
 * sirva copias viejas tras un cambio. Los archivos de assets/vendor/ ya llevan su versión en la ruta.
 */
final class VersionDeEstilosTest extends BaseDatos {
    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
    }

    /** @return array<string,array{0:string}> */
    public static function pantallas(): array {
        $o = [];
        foreach (Pantallas::nombres() as $n) $o[$n] = [$n];
        return $o;
    }

    public function testUrlLlevaLaFechaDelArchivo(): void {
        $esperado = APP_URL . "/assets/css/tema.css?v=" . filemtime(BASE_PATH . "/assets/css/tema.css");
        $this->assertSame($esperado, \Assets::url("css/tema.css"));
        $this->assertSame($esperado, \Assets::url("/css/tema.css"), "La barra inicial se ignora");
        $this->assertMatchesRegularExpression('~^' . preg_quote(APP_URL, "~") . '/assets/js/main\.js\?v=\d{9,}$~', \Assets::url("js/main.js"));
    }

    public function testUnArchivoQueNoExisteNoLlevaVersionInventada(): void {
        $this->assertSame(APP_URL . "/assets/css/no-existe.css", \Assets::url("css/no-existe.css"));
    }

    public function testLaVersionCambiaCuandoCambiaElArchivo(): void {
        $nombre = "zz_prueba_" . getmypid() . ".css";
        $ruta = BASE_PATH . "/assets/css/$nombre";
        file_put_contents($ruta, "a{}");
        try {
            touch($ruta, 1700000000);
            clearstatcache();
            $antes = \Assets::url("css/$nombre");
            touch($ruta, 1700000100);
            clearstatcache();
            $despues = \Assets::url("css/$nombre");
        } finally {
            @unlink($ruta);
        }
        $this->assertStringEndsWith("?v=1700000000", $antes);
        $this->assertStringEndsWith("?v=1700000100", $despues);
    }

    #[DataProvider("pantallas")]
    public function testCadaCssYJsPropioSePideConSuVersion(string $pantalla): void {
        $x = Html::xpath(Pantallas::render($this->db, $pantalla));
        $propios = 0;
        foreach ($x->query("//link[@rel='stylesheet']/@href|//script/@src") as $a) {
            $url = $a->nodeValue;
            if (!preg_match('~^' . preg_quote(APP_URL, "~") . '/assets/((?:css|js|fonts)/[^?]+)(\?.*)?$~', $url, $m)) continue;
            $propios++;
            $this->assertSame("?v=" . filemtime(BASE_PATH . "/assets/" . $m[1]), $m[2] ?? "", "$pantalla: $url debe llevar la fecha real del archivo");
        }
        $this->assertGreaterThanOrEqual(5, $propios, "$pantalla: fuentes, tema, estilos y scripts propios");
    }

    public function testLasVistasNoVuelvenAPedirLosPropiosSinVersion(): void {
        $sueltos = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(BASE_PATH . "/app/Views", \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->getExtension() !== "php") continue;
            if (preg_match_all('~APP_URL\?>/assets/(?:css|js|fonts)/[^"\']+~', (string)file_get_contents($f->getPathname()), $m)) {
                foreach ($m[0] as $ruta) $sueltos[] = basename($f->getPathname()) . ": $ruta";
            }
        }
        $this->assertSame([], $sueltos, "Usa Assets::url() para los css y js propios: " . implode("; ", $sueltos));
    }

    public function testLosArchivosDeVendorNoSeVersionan(): void {
        $x = Html::xpath(Pantallas::render($this->db, "home"));
        foreach ($x->query("//link/@href|//script/@src") as $a) {
            if (str_contains($a->nodeValue, "/assets/vendor/")) $this->assertStringNotContainsString("?v=", $a->nodeValue);
        }
    }
}
