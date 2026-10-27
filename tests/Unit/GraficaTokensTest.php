<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tokens de gráfica de tema.css (6 tonos categóricos + gris de «Otras») y Chart.js autoalojado.
 */
final class GraficaTokensTest extends TestCase {
    private static function vars(string $modo): array {
        return \TemaGenerador::leerVariables((string)file_get_contents(BASE_PATH . "/assets/css/tema.css"), $modo);
    }

    public static function modos(): array {
        return ["oscuro" => ["dark"], "claro" => ["light"]];
    }

    /** @dataProvider modos */
    #[\PHPUnit\Framework\Attributes\DataProvider("modos")]
    public function testLosSeisTonosYOtrasSuperan3a1SobreLaTarjetaYSonDistintos(string $modo): void {
        $v = self::vars($modo);
        $tonos = [];
        foreach ([1, 2, 3, 4, 5, 6] as $n) {
            $this->assertArrayHasKey("chart-$n", $v, "falta --chart-$n en $modo");
            $tonos[] = $v["chart-$n"];
        }
        $this->assertCount(6, array_unique($tonos), "los 6 tonos deben ser distintos");
        $this->assertArrayHasKey("chart-otras", $v);
        foreach ([...$tonos, $v["chart-otras"]] as $hex) {
            $this->assertGreaterThanOrEqual(3.0, \TemaGenerador::contraste($hex, $v["surface"]), "$hex sobre la tarjeta en $modo");
        }
        $this->assertNotContains($v["chart-otras"], $tonos, "«Otras» no repite un tono de categoría");
    }

    public function testElPrimerTonoEsElAcentoDelTemaYOtrasEsElGrisSecundario(): void {
        foreach (["dark", "light"] as $modo) {
            $v = self::vars($modo);
            $this->assertSame($v["link"], $v["chart-1"]);
            $this->assertSame($v["text-muted"], $v["chart-otras"]);
        }
    }

    public function testChartJsAutoalojadoConSuLicencia(): void {
        $js = BASE_PATH . "/assets/js/vendor/chart.umd.min.js";
        $this->assertFileExists($js);
        $this->assertStringContainsString("Chart.js v4.4.7", (string)file_get_contents($js, false, null, 0, 200));
        $lic = (string)file_get_contents(BASE_PATH . "/assets/js/vendor/chart.LICENSE.md");
        $this->assertStringContainsString("MIT License", $lic);
    }
}
