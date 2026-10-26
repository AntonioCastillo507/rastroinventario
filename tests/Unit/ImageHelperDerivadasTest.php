<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\Imagenes;

/**
 * P1, sistema de imágenes: cada foto se guarda con su original y tres derivadas (640, 1200 y 2400 px) completas, sin recortar
 * ni estirar, sobre blanco; avisos de proporción y de resolución; regeneración de las que ya existen; borrado completo.
 */
final class ImageHelperDerivadasTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Imagenes::limpiarGuardados();
    }

    protected function tearDown(): void {
        Imagenes::limpiarGuardados();
        parent::tearDown();
    }

    private function medidas(string $sub, string $nombre): array {
        $i = getimagesize(UPLOAD_PATH . "$sub/$nombre");
        $this->assertNotFalse($i, "$sub/$nombre no es una imagen");
        return [$i[0], $i[1], $i[2]];
    }

    private function jpegGrande(int $w, int $h): string {
        return Imagenes::jpeg($w, $h);
    }

    public function testLasTresDerivadasTienenSuLadoMayorYLaProporcionDeLaOriginal(): void {
        $r = \ImageHelper::procesar($this->jpegGrande(3000, 2000));
        $this->assertNotNull($r, \ImageHelper::$error);
        $esperado = ["thumbnails" => [640, 427], "medium" => [1200, 800], "large" => [2400, 1600]];
        foreach ($esperado as $sub => [$w, $h]) {
            $nombre = $sub === "thumbnails" ? $r["thumbnail"] : \ImageHelper::derivada($r["original"], $sub);
            [$dw, $dh, $tipo] = $this->medidas($sub, $nombre);
            $this->assertSame(IMAGETYPE_JPEG, $tipo, $sub);
            $this->assertEqualsWithDelta($w, $dw, 1, "$sub ancho");
            $this->assertEqualsWithDelta($h, $dh, 1, "$sub alto");
        }
        $this->assertSame([3000, 2000], array_slice($this->medidas("parts", $r["original"]), 0, 2), "el original se conserva");
    }

    public function testUnaFotoChicaNoSeAmplia(): void {
        $r = \ImageHelper::procesar($this->jpegGrande(300, 200));
        foreach (["thumbnails" => $r["thumbnail"], "medium" => \ImageHelper::derivada($r["original"], "medium"), "large" => \ImageHelper::derivada($r["original"], "large")] as $sub => $n) {
            $this->assertSame([300, 200], array_slice($this->medidas($sub, $n), 0, 2), "$sub no se agranda");
        }
    }

    public function testUnaFotoAlargadaSeConservaCompletaSinRecortarNiEstirar(): void {
        $r = \ImageHelper::procesar($this->jpegGrande(2000, 400));
        [$w, $h] = $this->medidas("thumbnails", $r["thumbnail"]);
        $this->assertSame(640, $w);
        $this->assertEqualsWithDelta(128, $h, 1, "alto proporcional: 5:1 sigue siendo 5:1, no se vuelve cuadrada");
        [$w, $h] = $this->medidas("large", \ImageHelper::derivada($r["original"], "large"));
        $this->assertEqualsWithDelta(2000 / 400, $w / $h, 0.02);
    }

    public function testUnaFotoVerticalConservaSuProporcion(): void {
        $r = \ImageHelper::procesar($this->jpegGrande(400, 1600));
        [$w, $h] = $this->medidas("thumbnails", $r["thumbnail"]);
        $this->assertSame(640, $h);
        $this->assertEqualsWithDelta(160, $w, 1);
    }

    public function testUnPngConTransparenciaQuedaSobreFondoBlanco(): void {
        $im = imagecreatetruecolor(100, 100);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        $ruta = Imagenes::guardar("", "png");
        imagepng($im, $ruta);
        $r = \ImageHelper::procesar($ruta);
        $this->assertNotNull($r, \ImageHelper::$error);
        foreach (["thumbnails" => $r["thumbnail"], "medium" => \ImageHelper::derivada($r["original"], "medium"), "large" => \ImageHelper::derivada($r["original"], "large")] as $sub => $n) {
            $d = imagecreatefromjpeg(UPLOAD_PATH . "$sub/$n");
            $px = imagecolorat($d, 50, 50);
            foreach ([16, 8, 0] as $desp) $this->assertGreaterThanOrEqual(250, ($px >> $desp) & 255, "$sub: fondo blanco, no negro");
        }
    }

    public static function avisos(): array {
        return [
            "normal 4:3, grande"        => [1600, 1200, []],
            "cuadrada"                  => [1000, 1000, []],
            "justo en el límite ancho"  => [1500, 1000, []],
            "ancha 2:1"                 => [2000, 1000, ["Proporción muy alargada: se mostrará con bordes."]],
            "alta 5:8, bajo el límite"  => [1000, 1600, ["Proporción muy alargada: se mostrará con bordes."]],
            "alta 5:7, dentro"          => [1000, 1400, []],
            "alargada"                  => [2000, 400, ["Proporción muy alargada: se mostrará con bordes."]],
            "muy alta"                  => [500, 1600, ["Proporción muy alargada: se mostrará con bordes."]],
            "resolución baja"           => [700, 600, ["Resolución baja: se verá borrosa al ampliar."]],
            "799, con aviso"            => [799, 799, ["Resolución baja: se verá borrosa al ampliar."]],
            "justo 800, sin aviso"      => [800, 800, []],
            "ambos avisos"              => [600, 100, ["Proporción muy alargada: se mostrará con bordes.", "Resolución baja: se verá borrosa al ampliar."]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("avisos")]
    public function testLosAvisosDeProporcionYResolucion(int $w, int $h, array $esperado): void {
        $this->assertSame($esperado, \ImageHelper::avisos($w, $h));
    }

    public function testProcesarDevuelveLosAvisosYNoBloquea(): void {
        $r = \ImageHelper::procesar($this->jpegGrande(600, 100));
        $this->assertNotNull($r, "un aviso no rechaza la foto");
        $this->assertCount(2, $r["avisos"]);
        $r = \ImageHelper::procesar($this->jpegGrande(1200, 1000));
        $this->assertSame([], $r["avisos"]);
    }

    public function testSeGuardanCuatroArchivosYDeleteLosBorraTodos(): void {
        $r = \ImageHelper::procesar($this->jpegGrande(1500, 1500));
        $this->assertSame(4, Imagenes::archivosGuardados());
        \ImageHelper::delete($r["original"], $r["thumbnail"]);
        $this->assertSame(0, Imagenes::archivosGuardados(), "se van la original, la de 640, la de 1200 y la de 2400");
    }

    public function testSiFallaUnaDerivadaNoQuedaNingunArchivo(): void {
        // "large" es un archivo en lugar de una carpeta: la tercera derivada no se puede escribir
        @rmdir(UPLOAD_PATH . "large");
        file_put_contents(UPLOAD_PATH . "large", "no soy una carpeta");
        $r = \ImageHelper::procesar($this->jpegGrande(1500, 1500));
        $this->assertNull($r);
        $this->assertNotSame("", \ImageHelper::$error);
        $this->assertSame(0, Imagenes::archivosGuardados(), "ni la original ni las derivadas ya escritas");
        unlink(UPLOAD_PATH . "large");
        mkdir(UPLOAD_PATH . "large");
    }

    public function testRegenerarCreaLasDerivadasSinTocarLaOriginal(): void {
        $r = \ImageHelper::procesar($this->jpegGrande(2600, 1800));
        foreach (["thumbnails", "medium", "large"] as $sub) {
            $n = $sub === "thumbnails" ? $r["thumbnail"] : \ImageHelper::derivada($r["original"], $sub);
            unlink(UPLOAD_PATH . "$sub/$n");
        }
        $antes = hash_file("sha256", UPLOAD_PATH . "parts/" . $r["original"]);
        $this->assertSame(["thumbnails", "medium", "large"], \ImageHelper::regenerar($r["original"]));
        $this->assertSame($antes, hash_file("sha256", UPLOAD_PATH . "parts/" . $r["original"]), "la original queda idéntica");
        $this->assertSame(4, Imagenes::archivosGuardados());
        $this->assertSame(640, $this->medidas("thumbnails", $r["thumbnail"])[0], "la miniatura conserva su nombre");
    }

    public function testRegenerarReemplazaUnaMiniaturaVieja(): void {
        $r = \ImageHelper::procesar($this->jpegGrande(2000, 1500));
        $vieja = imagecreatetruecolor(420, 315);
        imagejpeg($vieja, UPLOAD_PATH . "thumbnails/" . $r["thumbnail"], 80);
        \ImageHelper::regenerar($r["original"]);
        $this->assertSame(640, $this->medidas("thumbnails", $r["thumbnail"])[0]);
    }

    public function testRegenerarRechazaNombresInseguros(): void {
        foreach (["../../x.jpg", "", "x.php", "no-existe.jpg", ".."] as $n) {
            $this->assertNull(\ImageHelper::regenerar($n), $n);
        }
    }

    public function testRegenerarRechazaUnaOriginalQueNoEsImagen(): void {
        if (!is_dir(UPLOAD_PATH . "parts")) mkdir(UPLOAD_PATH . "parts", 0777, true);
        file_put_contents(UPLOAD_PATH . "parts/falsa.jpg", "<?php echo 1;");
        $this->assertNull(\ImageHelper::regenerar("falsa.jpg"));
        $this->assertSame(1, Imagenes::archivosGuardados(), "solo queda la falsa; no se escribe nada");
        unlink(UPLOAD_PATH . "parts/falsa.jpg");
    }

    public function testDerivadaYFuenteResuelvenNombresYCaenALaSiguienteQueExista(): void {
        $this->assertSame("parte_ab_1200.jpg", \ImageHelper::derivada("parte_ab.png", "medium"));
        $this->assertSame("parte_ab_2400.jpg", \ImageHelper::derivada("parte_ab.png", "large"));
        $this->assertNull(\ImageHelper::derivada("../x.png", "medium"));
        $this->assertNull(\ImageHelper::derivada("parte_ab.png", "otra"));
        $this->assertNull(\ImageHelper::derivada(null, "medium"));

        $r = \ImageHelper::procesar($this->jpegGrande(1500, 1500));
        $o = $r["original"]; $t = $r["thumbnail"];
        $this->assertSame("medium/" . \ImageHelper::derivada($o, "medium"), \ImageHelper::fuente($o, $t, "medium"));
        $this->assertSame("thumbnails/$t", \ImageHelper::fuente($o, $t, "thumbnails"));
        $this->assertSame("large/" . \ImageHelper::derivada($o, "large"), \ImageHelper::fuente($o, $t, "large"));
        unlink(UPLOAD_PATH . "large/" . \ImageHelper::derivada($o, "large"));
        $this->assertSame("medium/" . \ImageHelper::derivada($o, "medium"), \ImageHelper::fuente($o, $t, "large"));
        unlink(UPLOAD_PATH . "medium/" . \ImageHelper::derivada($o, "medium"));
        $this->assertSame("parts/$o", \ImageHelper::fuente($o, $t, "medium"));
        unlink(UPLOAD_PATH . "parts/$o");
        $this->assertSame("thumbnails/$t", \ImageHelper::fuente($o, $t, "medium"));
        unlink(UPLOAD_PATH . "thumbnails/$t");
        $this->assertSame("", \ImageHelper::fuente($o, $t, "medium"), "sin archivos no hay ruta");
        $this->assertSame("", \ImageHelper::fuente(null, null));
    }

    public function testExisteReconoceLasCuatroCarpetas(): void {
        $r = \ImageHelper::procesar($this->jpegGrande(900, 900));
        $this->assertTrue(\ImageHelper::existe($r["original"], "parts"));
        $this->assertTrue(\ImageHelper::existe($r["thumbnail"], "thumbnails"));
        $this->assertTrue(\ImageHelper::existe(\ImageHelper::derivada($r["original"], "medium"), "medium"));
        $this->assertTrue(\ImageHelper::existe(\ImageHelper::derivada($r["original"], "large"), "large"));
        $this->assertFalse(\ImageHelper::existe($r["original"], "otra"));
    }

    public function testElAvisoTrasGuardarSeAnotaEnElMensajeYPasaAAmarillo(): void {
        $f = new \ReflectionMethod(\ParteController::class, "mensajeConAvisos");
        $this->assertSame(["Parte creada. ✔", "success"], $f->invoke(null, "Parte creada. ✔", []));
        [$msg, $tipo] = $f->invoke(null, "Parte actualizada.", ["Proporción muy alargada: se mostrará con bordes.", "Resolución baja: se verá borrosa al ampliar."]);
        $this->assertSame("warning", $tipo);
        $this->assertStringStartsWith("Parte actualizada. Aviso de la foto: Proporción muy alargada", $msg);
        $this->assertStringContainsString("Resolución baja", $msg);
    }
}
