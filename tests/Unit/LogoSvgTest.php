<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\RasterSvg;

/**
 * assets/img/logo.svg es el logo.png rehecho como vector de un solo color (bin/vectorizar_logo.php).
 * La prueba lo rasteriza y lo compara píxel a píxel con el PNG: la diferencia debe quedar bajo el umbral.
 * Si algún día no pasa, el plan de reserva es usar el PNG como máscara CSS (mask-image) en lugar del SVG.
 */
final class LogoSvgTest extends TestCase {
    /** Diferencia máxima aceptada: suma de |cobertura SVG − opacidad PNG| entre la opacidad total del PNG */
    private const UMBRAL_DIFERENCIA = 0.015;
    /** IoU mínimo de las dos figuras binarizadas al 50 % (los bordes suavizados del PNG la dejan cerca de 0,96) */
    private const UMBRAL_IOU = 0.95;

    private string $svg;
    private string $png;

    protected function setUp(): void {
        parent::setUp();
        $this->svg = (string)file_get_contents(BASE_PATH . "/assets/img/logo.svg");
        $this->png = BASE_PATH . "/assets/img/logo.png";
    }

    /** @return array{0:float,1:float,2:float} [diferencia relativa, iou, opacidad total del PNG] */
    private function comparar(string $svg, int $ox, int $oy): array {
        $op = RasterSvg::opacidadPng($this->png);
        $h = count($op); $w = count($op[0]);
        $cov = RasterSvg::cobertura($svg, $w, $h, $ox, $oy);
        $dif = 0.0; $total = 0.0; $inter = 0; $union = 0;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $dif += abs($cov[$y][$x] - $op[$y][$x]);
                $total += $op[$y][$x];
                $a = $op[$y][$x] >= 0.5; $b = $cov[$y][$x] >= 0.5;
                if ($a && $b) $inter++;
                if ($a || $b) $union++;
            }
        }
        return [$dif / $total, $inter / max(1, $union), $total];
    }

    public function testElSvgSeParece_AlPngPixelAPixel(): void {
        $this->assertMatchesRegularExpression('/data-origen="(\d+) (\d+)"/', $this->svg, "el SVG dice dónde cae dentro del PNG");
        preg_match('/data-origen="(\d+) (\d+)"/', $this->svg, $m);
        [$dif, $iou, $total] = $this->comparar($this->svg, (int)$m[1], (int)$m[2]);
        $this->assertGreaterThan(1000, $total, "el PNG tiene figura");
        $this->assertLessThan(self::UMBRAL_DIFERENCIA, $dif, sprintf("diferencia %.2f %% del área del logo", $dif * 100));
        $this->assertGreaterThan(self::UMBRAL_IOU, $iou, sprintf("IoU %.3f", $iou));
    }

    public function testLaPruebaDetectaUnSvgDistorsionado(): void {
        preg_match('/data-origen="(\d+) (\d+)"/', $this->svg, $m);
        // se estira un vértice 3 px: tiene que dejar de parecerse
        $malo = preg_replace('/M8\.86 54\.5L/', 'M8.86 58.5L', $this->svg, 1, $n);
        $this->assertSame(1, $n);
        [$dif] = $this->comparar($malo, (int)$m[1], (int)$m[2]);
        [$bueno] = $this->comparar($this->svg, (int)$m[1], (int)$m[2]);
        $this->assertGreaterThan($bueno * 1.5, $dif, "mover un vértice cambia la diferencia");
        // y un SVG corrido un píxel entero tampoco pasa el umbral
        [$corrido] = $this->comparar($this->svg, (int)$m[1] + 2, (int)$m[2]);
        $this->assertGreaterThan(self::UMBRAL_DIFERENCIA, $corrido);
    }

    public function testElSvgEsDeUnSoloColorYChico(): void {
        $this->assertLessThanOrEqual(2048, strlen($this->svg), "≤ 2 KB");
        $this->assertSame(1, preg_match_all('/<path\b/', $this->svg), "un solo trazado");
        $this->assertStringContainsString('fill="currentColor"', $this->svg);
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,6}|rgb\(|<style|<text|<image|<script|\bon[a-z]+\s*=/i', $this->svg, "sin colores propios, texto ni scripts");
        $this->assertMatchesRegularExpression('/viewBox="0 0 [\d.]+ [\d.]+"/', $this->svg);
    }

    public function testElSvgOcupaCasTodoSuLienzoParaQueSeVeaDelTamanoPedido(): void {
        preg_match('/viewBox="0 0 ([\d.]+) ([\d.]+)"/', $this->svg, $m);
        $polis = RasterSvg::poligonos($this->svg);
        $xs = $ys = [];
        foreach ($polis as $p) foreach ($p as [$x, $y]) { $xs[] = $x; $ys[] = $y; }
        $this->assertLessThan(3.0, min($xs), "margen izquierdo del viewBox");
        $this->assertLessThan(3.0, min($ys), "margen superior");
        $this->assertLessThan(3.0, (float)$m[1] - max($xs), "margen derecho");
        $this->assertLessThan(3.0, (float)$m[2] - max($ys), "margen inferior");
    }

    public function testElPngSigueExistiendoComoRespaldo(): void {
        $this->assertFileExists($this->png);
        $this->assertSame([192, 128], array_slice(getimagesize($this->png), 0, 2));
    }
}
