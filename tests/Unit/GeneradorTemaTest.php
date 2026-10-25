<?php
namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** TemaGenerador y bin/generar_tema.php: derivación del color principal, validación del contraste y script de consola. */
final class GeneradorTemaTest extends TestCase {
    private const COLORES = ["dorado" => "#FCD535", "naranja" => "#F97316", "azul" => "#2563EB"];

    /** @return array<string,array{0:string}> */
    public static function colores(): array {
        $o = [];
        foreach (self::COLORES as $n => $c) $o[$n] = [$c];
        return $o;
    }

    /** @return array{0:int,1:string,2:string} código, salida estándar, salida de error */
    private static function ejecutar(array $args): array {
        $p = proc_open(array_merge([PHP_BINARY, BASE_PATH . "/bin/generar_tema.php"], $args), [1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        return [proc_close($p), $out, $err];
    }

    private static function temporal(string $nombre): string {
        return RASTRO_TEST_TMP . DIRECTORY_SEPARATOR . $nombre;
    }

    public function testContrasteEsLaFormulaWcag(): void {
        $this->assertEqualsWithDelta(21.0, \TemaGenerador::contraste("#000000", "#ffffff"), 0.001);
        $this->assertEqualsWithDelta(1.0, \TemaGenerador::contraste("#808080", "#808080"), 0.001);
        $this->assertEqualsWithDelta(\TemaGenerador::contraste("#fcd535", "#181a20"), \TemaGenerador::contraste("#181a20", "#fcd535"), 1e-9);
    }

    public function testNormalizarAceptaFormasCortasYRechazaBasura(): void {
        $this->assertSame("#aabbcc", \TemaGenerador::normalizar("ABC"));
        $this->assertSame("#fcd535", \TemaGenerador::normalizar(" #FCD535 "));
        foreach (["", "#12", "rojo", "#GGGGGG", "#1234567", "fcd53"] as $malo) {
            try {
                \TemaGenerador::normalizar($malo);
                $this->fail("Debió rechazar '$malo'");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testConElDoradoLosTokensSonLosAprobados(): void {
        $r = \TemaGenerador::derivar("#fcd535");
        $this->assertSame(\TemaGenerador::BASE, $r["paleta"]);
        $this->assertSame([], $r["avisos"]);
        $this->assertSame([], \TemaGenerador::validar($r["paleta"]));
    }

    #[DataProvider("colores")]
    public function testLosTresColoresDePruebaPasanElContrasteEnAmbosModos(string $color): void {
        $r = \TemaGenerador::generar($color);
        $this->assertSame([], \TemaGenerador::validar($r["paleta"]));
        // el CSS escrito, leído de vuelta, también cumple
        $porModo = ["dark" => \TemaGenerador::leerVariables($r["css"], "dark"), "light" => \TemaGenerador::leerVariables($r["css"], "light")];
        $this->assertSame([], \TemaGenerador::validarVariables($porModo));
        $this->assertSame(strtolower($color), $r["paleta"]["primary"]);
        $this->assertStringContainsString("--color-primary: " . strtolower($color) . ";", $r["css"]);
        // el principal, tal cual, es el relleno del botón principal en los dos modos
        foreach ($porModo as $v) $this->assertSame(strtolower($color), $v["btn-primary-bg"]);
    }

    #[DataProvider("colores")]
    public function testElTextoDelBotonYLosDerivadosCumplen(string $color): void {
        $p = \TemaGenerador::derivar($color)["paleta"];
        $this->assertGreaterThanOrEqual(4.5, \TemaGenerador::contraste($p["on-primary"], $p["primary"]));
        $this->assertGreaterThanOrEqual(4.5, \TemaGenerador::contraste($p["on-primary"], $p["primary-active"]));
        foreach (["surface-soft-light", "canvas-light", "surface-strong-light"] as $f) {
            $this->assertGreaterThanOrEqual(4.5, \TemaGenerador::contraste($p["link-on-light"], $p[$f]), "enlace sobre $f");
        }
        foreach (["canvas-dark", "surface-card-dark", "surface-elevated-dark"] as $f) {
            $this->assertGreaterThanOrEqual(4.5, \TemaGenerador::contraste($p["primary-text-on-dark"], $p[$f]), "principal como texto sobre $f");
        }
    }

    public function testElNaranjaCorrigeElPrimaryActiveYLoAvisa(): void {
        $r = \TemaGenerador::derivar("#F97316");
        $this->assertSame("#db5d06", $r["paleta"]["primary-active"], "El valor corregido que documenta DESIGN.md");
        $this->assertNotEmpty(array_filter($r["avisos"], fn($a) => str_contains($a, "primary-active")));
        $this->assertNotSame("#f97316", $r["paleta"]["link-on-light"], "El naranja no llega a 4,5:1 como texto sobre claro");
    }

    public function testElAzulAclaraElTextoSobreOscuroYCambiaElFoco(): void {
        $r = \TemaGenerador::derivar("#2563EB");
        $p = $r["paleta"];
        $this->assertNotSame("#2563eb", $p["primary-text-on-dark"]);
        $this->assertSame("#ffffff", $p["focus-ring-dark"]);
        $this->assertSame($p["ink"], $p["focus-ring-light"]);
        $this->assertNotEmpty(array_filter($r["avisos"], fn($a) => str_contains($a, "focus-ring")));
        $this->assertNotEmpty(array_filter($r["avisos"], fn($a) => str_contains($a, "primary-text-on-dark")));
        // el naranja y el dorado conservan el foco azul
        $this->assertSame("#3b82f6", \TemaGenerador::derivar("#F97316")["paleta"]["focus-ring-dark"]);
    }

    public function testElTextoSobreUnPrincipalOscuroEsBlanco(): void {
        $p = \TemaGenerador::derivar("#1D4ED8")["paleta"];
        $this->assertSame("#ffffff", $p["on-primary"]);
        $this->assertSame([], \TemaGenerador::validar($p));
    }

    public function testElTemaCssDelProyectoEsLaSalidaDelGeneradorConElDorado(): void {
        $this->assertSame(
            \TemaGenerador::generar("#FCD535")["css"],
            (string)file_get_contents(BASE_PATH . "/assets/css/tema.css"),
            "tema.css se editó a mano o está desactualizado: php bin/generar_tema.php #FCD535"
        );
    }

    #[DataProvider("colores")]
    public function testElScriptEscribeUnTemaValido(string $color): void {
        $salida = self::temporal("tema_" . ltrim($color, "#") . ".css");
        [$codigo, $out] = self::ejecutar([$color, "--salida=$salida"]);
        $this->assertSame(0, $codigo, $out);
        $this->assertFileExists($salida);
        $this->assertStringContainsString("Contraste correcto", $out);
        [$codigo2, $out2] = self::ejecutar(["--validar", $salida]);
        $this->assertSame(0, $codigo2, $out2);
        $this->assertSame(\TemaGenerador::generar($color)["css"], file_get_contents($salida));
    }

    public function testElScriptAvisaDeLasCorrecciones(): void {
        [$codigo, $out] = self::ejecutar(["#F97316", "--salida=" . self::temporal("tema_aviso.css")]);
        $this->assertSame(0, $codigo);
        $this->assertStringContainsString("Correcciones", $out);
        $this->assertStringContainsString("#db5d06", $out);
    }

    public function testElScriptRechazaUnColorNoValidoSinEscribirNada(): void {
        $salida = self::temporal("tema_malo.css");
        [$codigo, , $err] = self::ejecutar(["rojo", "--salida=$salida"]);
        $this->assertSame(1, $codigo);
        $this->assertStringContainsString("Color no válido", $err);
        $this->assertFileDoesNotExist($salida);
        [$c2] = self::ejecutar([]);
        $this->assertSame(1, $c2);
        [$c3] = self::ejecutar(["--inventada"]);
        $this->assertSame(1, $c3);
    }

    public function testValidarDetectaUnParBajoElMinimo(): void {
        $css = (string)file_get_contents(BASE_PATH . "/assets/css/tema.css");
        $roto = self::temporal("tema_roto.css");
        // el texto secundario claro pasa a un gris que no llega a 4,5:1
        file_put_contents($roto, str_replace("--color-muted-on-light: #5e6673;", "--color-muted-on-light: #a0a7b3;", $css));
        [$codigo, , $err] = self::ejecutar(["--validar", $roto]);
        $this->assertSame(2, $codigo);
        $this->assertStringContainsString("light: text-muted", $err);
        // y un archivo que no es un tema se informa sin romperse
        $basura = self::temporal("tema_basura.css");
        file_put_contents($basura, "body{color:red}");
        [$c2, , $e2] = self::ejecutar(["--validar", $basura]);
        $this->assertSame(2, $c2);
        $this->assertStringContainsString("No pude leer", $e2);
    }

    public function testElScriptSoloCorreEnConsola(): void {
        $fuente = (string)file_get_contents(BASE_PATH . "/bin/generar_tema.php");
        $this->assertMatchesRegularExpression('/if \(PHP_SAPI !== "cli"\) \{\s*http_response_code\(404\);\s*exit;/', $fuente);
        $this->assertLessThan(strpos($fuente, "require_once"), strpos($fuente, "PHP_SAPI"), "La guarda va antes de cargar nada");
    }

    public function testApacheBloqueaLaCarpetaBin(): void {
        $this->assertMatchesRegularExpression("~RedirectMatch 403 \^/rastroinventario/\([^)]*\|bin\|~", (string)file_get_contents(BASE_PATH . "/.htaccess"));
    }
}
