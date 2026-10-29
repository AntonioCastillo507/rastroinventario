<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * uploads/marcas/.htaccess contra el Apache real (php -S ignora los .htaccess). Crea archivos de prueba en la carpeta de
 * logos del proyecto, los pide por HTTP y los borra siempre al terminar. Si Apache no responde, la prueba se salta con aviso.
 */
final class UploadsMarcasApacheTest extends TestCase {
    private const BASE = "http://localhost/rastroinventario/uploads/marcas/";
    /** @var list<string> */
    private array $creados = [];
    private string $dir;

    protected function setUp(): void {
        parent::setUp();
        $this->dir = BASE_PATH . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "marcas" . DIRECTORY_SEPARATOR;
        if (!is_file($this->dir . ".htaccess")) $this->fail("falta uploads/marcas/.htaccess");
        if ($this->pedir("sonda-inexistente.png") === null) $this->markTestSkipped("Apache no responde en localhost: no se pudo probar el .htaccess (NO cubierto).");
    }

    protected function tearDown(): void {
        foreach ($this->creados as $f) @unlink($this->dir . $f);
        parent::tearDown();
    }

    /** @return array{codigo:int, cabeceras:string, tipo:string}|null */
    private function pedir(string $nombre): ?array {
        $ch = curl_init(self::BASE . $nombre);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 5, CURLOPT_CONNECTTIMEOUT => 2]);
        $r = curl_exec($ch);
        if ($r === false) return null;
        return [
            "codigo" => (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
            "cabeceras" => substr((string)$r, 0, (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE)),
            "tipo" => (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE),
        ];
    }

    private function crear(string $nombre, string $contenido = "x"): string {
        file_put_contents($this->dir . $nombre, $contenido);
        $this->creados[] = $nombre;
        return $nombre;
    }

    private function hex(): string { return bin2hex(random_bytes(16)); }

    public function testSvgSeSirveConSusCabeceras(): void {
        $n = $this->crear("marca_" . $this->hex() . ".svg", '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><path d="M0 0h10v10z"/></svg>');
        $r = $this->pedir($n);
        $this->assertSame(200, $r["codigo"]);
        $this->assertStringStartsWith("image/svg+xml", $r["tipo"]);
        $this->assertMatchesRegularExpression("/^Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox\s*$/mi", $r["cabeceras"]);
        $this->assertMatchesRegularExpression('/^X-Content-Type-Options: nosniff\s*$/mi', $r["cabeceras"]);
    }

    public function testPngYWebpSeSirvenConNosniff(): void {
        foreach (["png" => "image/png", "webp" => "image/webp"] as $ext => $tipo) {
            $r = $this->pedir($this->crear("marca_" . $this->hex() . ".$ext"));
            $this->assertSame(200, $r["codigo"], $ext);
            $this->assertStringStartsWith($tipo, $r["tipo"], $ext);
            $this->assertMatchesRegularExpression('/^X-Content-Type-Options: nosniff\s*$/mi', $r["cabeceras"], $ext);
        }
    }

    public function testUnPhpEnLaCarpetaDa403SinEjecutarse(): void {
        $h = $this->hex();
        foreach (["marca_$h.php", "marca_$h.php.png", "marca_$h.phtml", "cualquier.php"] as $n) {
            $this->crear($n, "<?php echo 'EJECUTADO'; ?>");
            $this->assertSame(403, $this->pedir($n)["codigo"], $n);
        }
    }

    public function testNombresFueraDelPatronDan403(): void {
        $h = $this->hex();
        foreach (["otro.png", "otro.svg", "otro.jpg", "otro.gif", "marca_$h.gif", "marca_$h.jpg", "marca_{$h}0.png", "marca_" . substr($h, 1) . ".png", ".htpasswd_prueba"] as $n) {
            $this->crear($n);
            $this->assertSame(403, $this->pedir($n)["codigo"], $n);
        }
    }

    public function testNoHayListadoNiSeLeeElHtaccess(): void {
        $this->assertSame(403, $this->pedir("")["codigo"]);
        $this->assertSame(403, $this->pedir(".htaccess")["codigo"]);
    }
}
