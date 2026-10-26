<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Las imágenes que usa el sitio pesan poco, son del tipo que dice su extensión y el logo no se sirve mucho mayor de lo que se ve */
final class ImagenesPesoTest extends TestCase {
    private const MAXIMO_KB = 300;

    /** @return list<string> imágenes de assets/img que citan las vistas, las hojas de estilo o marca.php */
    private function usadas(): array {
        $codigo = "";
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(BASE_PATH . "/app", \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) if (in_array($f->getExtension(), ["php"], true) && $f->getFilename() !== "config.php" && $f->getFilename() !== "rsa_secret.php") $codigo .= file_get_contents($f->getPathname());
        foreach (glob(BASE_PATH . "/assets/css/*.css") as $css) $codigo .= file_get_contents($css);
        preg_match_all('~img/([A-Za-z0-9_.-]+\.(?:png|jpe?g|webp|gif|svg))~', $codigo, $m);
        return array_values(array_unique($m[1]));
    }

    public function testLasImagenesQueUsaElSitioExistenYPesanPoco(): void {
        $usadas = $this->usadas();
        $this->assertContains("logo.png", $usadas);
        $this->assertContains("hero-bg.jpg", $usadas);
        $this->assertContains("hero-bg-800.jpg", $usadas);
        foreach ($usadas as $nombre) {
            $ruta = BASE_PATH . "/assets/img/$nombre";
            $this->assertFileExists($ruta, $nombre);
            $this->assertLessThanOrEqual(self::MAXIMO_KB * 1024, filesize($ruta), sprintf("%s pesa %d KB (máximo %d)", $nombre, filesize($ruta) / 1024, self::MAXIMO_KB));
        }
    }

    public function testElTipoRealDeCadaImagenCoincideConSuExtension(): void {
        $esperado = ["jpg" => "image/jpeg", "jpeg" => "image/jpeg", "png" => "image/png", "webp" => "image/webp", "gif" => "image/gif"];
        foreach (glob(BASE_PATH . "/assets/img/*.*") as $ruta) {
            $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
            if (!isset($esperado[$ext])) continue;
            $this->assertSame($esperado[$ext], getimagesize($ruta)["mime"], basename($ruta) . " no es del tipo que dice su extensión");
        }
    }

    public function testElLogoNoEsMuchoMayorDeLoQueSeMuestra(): void {
        // se muestra a 28 y 32 px de alto; hasta 4x cubre las pantallas más densas
        [$ancho, $alto] = getimagesize(BASE_PATH . "/assets/img/logo.png");
        $this->assertLessThanOrEqual(32 * 4, $alto);
        $this->assertLessThanOrEqual(32 * 4 * 2, $ancho);
        $this->assertLessThanOrEqual(40 * 1024, filesize(BASE_PATH . "/assets/img/logo.png"));
        $im = imagecreatefrompng(BASE_PATH . "/assets/img/logo.png");
        $alfa = (imagecolorat($im, 0, 0) >> 24) & 127;
        $this->assertSame(127, $alfa, "el logo conserva la transparencia");
    }

    public function testLaFotoDeMovilEsMasChicaQueLaDeEscritorio(): void {
        $this->assertLessThan(filesize(BASE_PATH . "/assets/img/hero-bg.jpg"), filesize(BASE_PATH . "/assets/img/hero-bg-800.jpg"));
        $this->assertLessThanOrEqual(800, getimagesize(BASE_PATH . "/assets/img/hero-bg-800.jpg")[0]);
    }

    /** Foto sintética con detalle (degradados y formas), grande como una foto de teléfono recortada */
    private function fotoSintetica(int $w, int $h): string {
        $im = imagecreatetruecolor($w, $h);
        for ($y = 0; $y < $h; $y += 4) imagefilledrectangle($im, 0, $y, $w, $y + 3, imagecolorallocate($im, intdiv($y * 255, $h), 120, 255 - intdiv($y * 255, $h)));
        for ($i = 0; $i < 400; $i++) imagefilledellipse($im, ($i * 97) % $w, ($i * 61) % $h, 30 + $i % 50, 20 + $i % 40, imagecolorallocate($im, ($i * 7) % 256, ($i * 13) % 256, ($i * 29) % 256));
        $ruta = \Tests\Support\Imagenes::guardar("", "jpg");
        imagejpeg($im, $ruta, 92);
        return $ruta;
    }

    public function testLasDerivadasDeUnaFotoPesadaPesanPoco(): void {
        \Tests\Support\Imagenes::limpiarGuardados();
        $r = \ImageHelper::procesar($this->fotoSintetica(3200, 2400));
        $this->assertNotNull($r, \ImageHelper::$error);
        // 640 px de tarjeta: ~40 KB en una foto real; 1200 px de detalle: ~120 KB; 2400 px para ampliar: unos cientos de KB
        $maximos = ["thumbnails" => [$r["thumbnail"], 120], "medium" => [\ImageHelper::derivada($r["original"], "medium"), 400], "large" => [\ImageHelper::derivada($r["original"], "large"), 1500]];
        $kb = [];
        foreach ($maximos as $sub => [$nombre, $max]) {
            $kb[$sub] = filesize(UPLOAD_PATH . "$sub/$nombre") / 1024;
            $this->assertLessThanOrEqual($max, $kb[$sub], sprintf("%s pesa %d KB (máximo %d)", $sub, $kb[$sub], $max));
        }
        $this->assertLessThan($kb["medium"], $kb["thumbnails"]);
        $this->assertLessThan($kb["large"], $kb["medium"]);
        \Tests\Support\Imagenes::limpiarGuardados();
    }
}
