<?php
namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Imagenes;

/** Subida de imágenes (hallazgo 12): ImageHelper::procesar y ImageHelper::delete, con imágenes creadas al vuelo */
final class ImageHelperTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Imagenes::limpiarGuardados();
    }

    protected function tearDown(): void {
        Imagenes::limpiarGuardados();
        parent::tearDown();
    }

    private function medidas(string $archivo): array {
        $i = getimagesize($archivo);
        $this->assertNotFalse($i, "el archivo guardado no es una imagen");
        return [$i[0], $i[1], $i[2]];
    }

    // ── Imágenes reales ──────────────────────────────────────────────────

    public static function formatos(): array {
        return [
            "jpeg" => ["jpeg", IMAGETYPE_JPEG, "jpg"],
            "png"  => ["png",  IMAGETYPE_PNG,  "png"],
            "gif"  => ["gif",  IMAGETYPE_GIF,  "gif"],
            "webp" => ["webp", IMAGETYPE_WEBP, "webp"],
        ];
    }

    #[DataProvider("formatos")]
    public function testAceptaImagenesRealesYGuardaOriginalYMiniatura(string $fabrica, int $tipo, string $ext): void {
        $r = \ImageHelper::procesar(Imagenes::$fabrica(800, 600));
        $this->assertNotNull($r, \ImageHelper::$error);
        $this->assertSame("", \ImageHelper::$error);

        $this->assertMatchesRegularExpression('/^parte_[0-9a-f]{32}\.' . $ext . '$/', $r["original"]);
        $this->assertMatchesRegularExpression('/^parte_[0-9a-f]{32}_th\.jpg$/', $r["thumbnail"]);

        [$w, $h, $t] = $this->medidas(UPLOAD_PATH . "parts/" . $r["original"]);
        $this->assertSame([800, 600, $tipo], [$w, $h, $t], "el original conserva medidas y formato");
        [$tw, $th, $tt] = $this->medidas(UPLOAD_PATH . "thumbnails/" . $r["thumbnail"]);
        $this->assertSame(IMAGETYPE_JPEG, $tt);
        $this->assertLessThanOrEqual(\ImageHelper::THUMB_W, $tw);
        $this->assertLessThanOrEqual(\ImageHelper::THUMB_H, $th);
        $this->assertSame(2, Imagenes::archivosGuardados());
    }

    public function testLaMiniaturaNoAgrandaUnaImagenChica(): void {
        $r = \ImageHelper::procesar(Imagenes::png(80, 60));
        $this->assertNotNull($r, \ImageHelper::$error);
        $this->assertSame([80, 60], array_slice($this->medidas(UPLOAD_PATH . "thumbnails/" . $r["thumbnail"]), 0, 2));
    }

    public function testRespetaLaOrientacionExif(): void {
        $r = \ImageHelper::procesar(Imagenes::jpegConOrientacion(6, 40, 20));
        $this->assertNotNull($r, \ImageHelper::$error);
        $this->assertSame([20, 40], array_slice($this->medidas(UPLOAD_PATH . "parts/" . $r["original"]), 0, 2), "girada 90°");
    }

    public function testElNombreLoPonePrefijoYAzarNuncaElUsuario(): void {
        $a = \ImageHelper::procesar(Imagenes::jpeg(), "../../evil");
        $b = \ImageHelper::procesar(Imagenes::jpeg(), "../../evil");
        $this->assertStringStartsWith("evil_", $a["original"]);
        $this->assertDoesNotMatchRegularExpression('#[/\\\\]|\.\.#', $a["original"] . $a["thumbnail"]);
        $this->assertNotSame($a["original"], $b["original"]);
    }

    public function testLaExtensionDelArchivoSubidoNoCuenta(): void {
        // Un PNG real con nombre .php y un JPEG real con nombre .png se tratan por su contenido
        $png = Imagenes::png();
        rename($png, $png . ".php");
        $r = \ImageHelper::procesar($png . ".php");
        $this->assertNotNull($r, \ImageHelper::$error);
        $this->assertStringEndsWith(".png", $r["original"]);
    }

    // ── Archivos hostiles ───────────────────────────────────────────────

    public static function hostiles(): array {
        return [
            "php disfrazado de jpg"      => ["phpDisfrazado", "no es una imagen"],
            "html disfrazado"            => ["html", "no es una imagen"],
            "svg real"                   => ["svg", "no es una imagen"],
            "archivo vacío"              => ["vacio", "vacío"],
            "jpeg truncado"              => ["jpegTruncado", ""],
            "png truncado"               => ["pngTruncado", ""],
            "gif truncado"               => ["gifTruncado", ""],
            "webp truncado"              => ["webpTruncado", ""],
            "png 20000x20000"            => ["gigante", "demasiado grande"],
            "png de 25 megapíxeles"      => ["veinticincoMp", "demasiado grande"],
            "png de 6001 px de ancho"    => ["anchoPasado", "demasiado grande"],
            "png de 16 MP y un poco más" => ["pixelesPasados", "demasiado grande"],
            "más pesado que el límite"   => ["pesado", "supera el máximo"],
        ];
    }

    private function hostil(string $tipo): string {
        return match($tipo) {
            "phpDisfrazado" => Imagenes::phpDisfrazado(),
            "html"          => Imagenes::html(),
            "svg"           => Imagenes::svg(),
            "vacio"         => Imagenes::vacio(),
            "jpegTruncado"  => Imagenes::truncado(Imagenes::jpeg(400, 300), 0.5),
            "pngTruncado"   => Imagenes::truncado(Imagenes::png(400, 300), 0.5),
            "gifTruncado"   => Imagenes::truncado(Imagenes::gif(400, 300), 0.5),
            "webpTruncado"  => Imagenes::truncado(Imagenes::webp(400, 300), 0.5),
            "gigante"       => Imagenes::pngCabecera(20000, 20000),
            "veinticincoMp" => Imagenes::pngCabecera(5000, 5000),
            "anchoPasado"   => Imagenes::pngCabecera(6001, 10),
            "pixelesPasados" => Imagenes::pngCabecera(4001, 4000), // 16 004 000 px: cada lado cabe, el total no
            "pesado"        => Imagenes::pesado(\ImageHelper::limiteBytes() + 1),
        };
    }

    #[DataProvider("hostiles")]
    public function testRechazaYNoDejaNadaGuardado(string $tipo, string $motivo): void {
        $this->assertNull(\ImageHelper::procesar($this->hostil($tipo)), $tipo);
        $this->assertNotSame("", \ImageHelper::$error, "tiene que explicar el rechazo");
        if ($motivo !== "") $this->assertStringContainsString($motivo, \ImageHelper::$error);
        $this->assertSame(0, Imagenes::archivosGuardados(), "no queda ningún archivo");
    }

    public function testArchivoInexistenteSeRechaza(): void {
        $this->assertNull(\ImageHelper::procesar(RASTRO_TEST_TMP . "/no_existe.jpg"));
        $this->assertNotSame("", \ImageHelper::$error);
    }

    public function testPolyglotSeAceptaComoImagenPeroLaSalidaNoLlevaCodigo(): void {
        $r = \ImageHelper::procesar(Imagenes::polyglot());
        $this->assertNotNull($r, \ImageHelper::$error);
        foreach ([UPLOAD_PATH . "parts/" . $r["original"], UPLOAD_PATH . "thumbnails/" . $r["thumbnail"]] as $f) {
            $bytes = file_get_contents($f);
            $this->assertStringNotContainsString("<?php", $bytes);
            $this->assertStringNotContainsString("system(", $bytes);
        }
    }

    public function testLosLimitesDeMedidasSonLosDeLaPolitica(): void {
        $this->assertSame(6000, \ImageHelper::MAX_SIDE);
        $this->assertSame(16000000, \ImageHelper::MAX_PIXELS);
        $this->assertSame(5242880, \ImageHelper::MAX_SIZE);
    }

    public function testElLimiteRealNuncaSuperaLos5MB(): void {
        $this->assertGreaterThan(0, \ImageHelper::limiteBytes());
        $this->assertLessThanOrEqual(\ImageHelper::MAX_SIZE, \ImageHelper::limiteBytes());
        $this->assertMatchesRegularExpression('/^\d+(\.\d)? MB$/', \ImageHelper::limiteTexto());
    }

    // ── delete ─────────────────────────────────────────────────────────

    public function testDeleteBorraLoPropioYNadaFueraDeSuCarpeta(): void {
        $canario = RASTRO_TEST_TMP . "/canario.jpg";
        file_put_contents($canario, "no me borres");
        foreach (["parts", "thumbnails"] as $sub) if (!is_dir(UPLOAD_PATH . $sub)) mkdir(UPLOAD_PATH . $sub, 0777, true); // sin las carpetas, "../.." no se resuelve y la prueba no probaría nada

        \ImageHelper::delete("../../canario.jpg", "../canario.jpg");
        \ImageHelper::delete($canario, $canario);
        \ImageHelper::delete("..", ".");
        \ImageHelper::delete("", null);
        \ImageHelper::delete("x.php", "y.txt");
        $this->assertFileExists($canario);

        $r = \ImageHelper::procesar(Imagenes::jpeg());
        $this->assertSame(2, Imagenes::archivosGuardados());
        \ImageHelper::delete($r["original"], $r["thumbnail"]);
        $this->assertSame(0, Imagenes::archivosGuardados());
    }

    public function testDeleteNoTocaArchivosQueNoSonImagenes(): void {
        if (!is_dir(UPLOAD_PATH . "parts")) mkdir(UPLOAD_PATH . "parts", 0777, true);
        file_put_contents(UPLOAD_PATH . "parts/x.php", "<?php // no soy imagen");
        \ImageHelper::delete("x.php", null);
        $this->assertFileExists(UPLOAD_PATH . "parts/x.php");
    }
}
