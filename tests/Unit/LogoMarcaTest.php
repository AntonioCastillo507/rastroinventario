<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\Imagenes;

/** Subida de logos de marcas: tipo por contenido, tamaños, transparencia y nombre generado por el servidor */
final class LogoMarcaTest extends TestCase {
    private const SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 50"><path d="M0 0h100v50z"/></svg>';

    protected function setUp(): void {
        parent::setUp();
        $this->limpiar();
    }
    protected function tearDown(): void {
        $this->limpiar();
        parent::tearDown();
    }
    private function limpiar(): void {
        $d = \LogoMarca::dir();
        if (!is_dir($d)) return;
        foreach (array_diff(scandir($d), [".", ".."]) as $f) @unlink($d . $f);
    }
    private function guardados(): array {
        return is_dir(\LogoMarca::dir()) ? array_values(array_diff(scandir(\LogoMarca::dir()), [".", ".."])) : [];
    }

    /** PNG o WebP con fondo transparente (o blanco sólido) y un cuadro opaco en el centro */
    private function conAlfa(string $tipo, int $w = 120, int $h = 60, bool $transparente = true): string {
        $im = imagecreatetruecolor($w, $h);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, $transparente ? imagecolorallocatealpha($im, 0, 0, 0, 127) : imagecolorallocate($im, 255, 255, 255));
        imagefilledrectangle($im, intdiv($w, 4), intdiv($h, 4), intdiv($w * 3, 4), intdiv($h * 3, 4), imagecolorallocate($im, 10, 20, 30));
        ob_start();
        $tipo === "png" ? imagepng($im) : imagewebp($im, null, 90);
        return Imagenes::guardar((string)ob_get_clean(), $tipo);
    }

    public function testAceptaPngYWebpConTransparenciaYLosGuardaConNombreDelServidor(): void {
        foreach (["png", "webp"] as $tipo) {
            $n = \LogoMarca::procesar($this->conAlfa($tipo));
            $this->assertNotNull($n, \LogoMarca::$error);
            $this->assertMatchesRegularExpression('/^marca_[0-9a-f]{32}\.' . $tipo . '$/', $n);
            $this->assertTrue(\LogoMarca::existe($n));
            $this->assertNotEmpty(\LogoMarca::url($n));
        }
    }

    public function testLaExtensionSaleDelTipoRealNoDelNombreDelArchivo(): void {
        $png = $this->conAlfa("png");
        $disfrazado = Imagenes::guardar((string)file_get_contents($png), "jpg"); // PNG llamado .jpg
        $n = \LogoMarca::procesar($disfrazado);
        $this->assertNotNull($n, \LogoMarca::$error);
        $this->assertStringEndsWith(".png", $n);
    }

    public function testAceptaSvgYGuardaElReconstruidoNoElSubido(): void {
        $sucio = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 50" onload="alert(1)"><script>alert(1)</script><path d="M0 0h100v50z" onclick="x()"/></svg>';
        $n = \LogoMarca::procesar(Imagenes::guardar($sucio, "svg"));
        $this->assertNotNull($n, \LogoMarca::$error);
        $this->assertStringEndsWith(".svg", $n);
        $guardado = (string)file_get_contents(\LogoMarca::dir() . $n);
        $this->assertStringNotContainsString("script", $guardado);
        $this->assertStringNotContainsString("onload", $guardado);
        $this->assertStringNotContainsString("onclick", $guardado);
        $this->assertStringContainsString("<path", $guardado);
    }

    public function testElNombreSubidoNuncaSeUsa(): void {
        $f = Imagenes::dir() . DIRECTORY_SEPARATOR . "logo-del-cliente.svg";
        file_put_contents($f, self::SVG);
        $n = \LogoMarca::procesar($f);
        $this->assertStringNotContainsString("cliente", $n);
        $this->assertSame([$n], $this->guardados());
    }

    public function testRechazaPhpDisfrazadoDePngJpgOSvg(): void {
        foreach (["png", "jpg", "svg"] as $ext) {
            $this->assertNull(\LogoMarca::procesar(Imagenes::guardar("<?php echo 'hola'; system(\$_GET['x']); ?>", $ext)), $ext);
            $this->assertNotSame("", \LogoMarca::$error);
        }
        $this->assertNull(\LogoMarca::procesar(Imagenes::phpDisfrazado()));
        $this->assertNull(\LogoMarca::procesar(Imagenes::html()));
        $this->assertSame([], $this->guardados(), "nada se guarda si se rechaza");
    }

    public function testRechazaJpegYGif(): void {
        $this->assertNull(\LogoMarca::procesar(Imagenes::jpeg()));
        $this->assertSame("El logo debe ser PNG, WebP o SVG.", \LogoMarca::$error);
        $this->assertNull(\LogoMarca::procesar(Imagenes::gif()));
        $this->assertSame([], $this->guardados());
    }

    public function testRechazaPngYWebpConFondoSolido(): void {
        $this->assertNull(\LogoMarca::procesar($this->conAlfa("png", 120, 60, false)));
        $this->assertStringContainsString("transparente", \LogoMarca::$error);
        $this->assertNull(\LogoMarca::procesar($this->conAlfa("webp", 120, 60, false)));
        $this->assertSame([], $this->guardados());
    }

    public function testRechazaLadoMayorA4000SinDecodificar(): void {
        $this->assertNull(\LogoMarca::procesar(Imagenes::pngCabecera(4001, 10)));
        $this->assertStringContainsString("demasiado grande", \LogoMarca::$error);
        $this->assertNull(\LogoMarca::procesar(Imagenes::pngCabecera(10, 4001)));
        $this->assertNull(\LogoMarca::procesar(Imagenes::pngCabecera(30000, 30000)));
        $this->assertSame([], $this->guardados());
    }

    public function testElLimiteDe4000EsInclusivo(): void {
        // 4000 px pasa el límite de lado; la cabecera sin datos falla después, como imagen dañada, no como «demasiado grande»
        $this->assertNull(\LogoMarca::procesar(Imagenes::pngCabecera(4000, 10)));
        $this->assertStringNotContainsString("demasiado grande", \LogoMarca::$error);
    }

    public function testRechazaVacioPesadoYTruncado(): void {
        $this->assertNull(\LogoMarca::procesar(Imagenes::vacio()));
        $this->assertSame("El archivo está vacío.", \LogoMarca::$error);
        $pesado = Imagenes::guardar(str_repeat("A", \LogoMarca::MAX_BYTES_RASTER + 1), "png");
        $this->assertNull(\LogoMarca::procesar($pesado));
        $png = $this->conAlfa("png", 300, 200);
        $this->assertNull(\LogoMarca::procesar(Imagenes::truncado($png, 0.6)));
        $this->assertSame([], $this->guardados());
    }

    public function testSvgDeMasDe100KbSeRechazaAntesDeLeerlo(): void {
        $grande = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><path d="M0 0h10v10z"/><!--' . str_repeat("x", 102400) . '--></svg>';
        $this->assertGreaterThan(102400, strlen($grande));
        $this->assertNull(\LogoMarca::procesar(Imagenes::guardar($grande, "svg")));
        $this->assertStringContainsString("100 KB", \LogoMarca::$error);
    }

    public function testRedimensionaLoQueSuperaLos600px(): void {
        $n = \LogoMarca::procesar($this->conAlfa("png", 1800, 900));
        $this->assertNotNull($n, \LogoMarca::$error);
        [$w, $h] = getimagesize(\LogoMarca::dir() . $n);
        $this->assertSame(600, $w);
        $this->assertSame(300, $h);
    }

    public function testLoGuardadoNoConservaBytesPegadosAlOriginal(): void {
        $f = $this->conAlfa("png");
        file_put_contents($f, "\n<?php system('x'); ?>", FILE_APPEND);
        $n = \LogoMarca::procesar($f);
        if ($n !== null) $this->assertStringNotContainsString("<?php", (string)file_get_contents(\LogoMarca::dir() . $n));
        else $this->assertNotSame("", \LogoMarca::$error);
    }

    public function testNombreSeguroYBorrarSoloTocanNombresPropios(): void {
        $this->assertTrue(\LogoMarca::nombreSeguro("marca_" . str_repeat("a", 32) . ".svg"));
        foreach ([null, "", "x.png", "../marca_" . str_repeat("a", 32) . ".png", "marca_" . str_repeat("a", 32) . ".php", "marca_" . str_repeat("A", 32) . ".png", "marca_" . str_repeat("a", 31) . ".png", "marca_" . str_repeat("a", 32) . ".png\n"] as $malo) {
            $this->assertFalse(\LogoMarca::nombreSeguro($malo), (string)$malo);
        }
        @mkdir(\LogoMarca::dir(), 0777, true);
        file_put_contents(\LogoMarca::dir() . "ajeno.png", "x");
        \LogoMarca::borrar("ajeno.png");
        $this->assertFileExists(\LogoMarca::dir() . "ajeno.png", "un nombre ajeno no se borra");
        $n = \LogoMarca::procesar(Imagenes::guardar(self::SVG, "svg"));
        \LogoMarca::borrar($n);
        $this->assertFileDoesNotExist(\LogoMarca::dir() . $n);
    }

    public function testSubirExigeUnaSubidaRealDelFormulario(): void {
        $this->assertNull(\LogoMarca::subir(["error" => UPLOAD_ERR_NO_FILE]));
        $this->assertNull(\LogoMarca::subir(["error" => UPLOAD_ERR_INI_SIZE]));
        $this->assertNull(\LogoMarca::subir(["error" => UPLOAD_ERR_OK, "tmp_name" => Imagenes::guardar(self::SVG, "svg")]));
        $this->assertSame("El archivo no llegó por el formulario.", \LogoMarca::$error);
    }
}
