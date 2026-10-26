<?php
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatos;
use Tests\Support\Html;
use Tests\Support\Pantallas;
use Tests\Support\Proceso;

/**
 * P7, acceso: login, registro, olvide y restablecer llevan el bloque de marca (logo, nombre, lema), un h1 con el propósito de la
 * pantalla, las mismas hojas de estilo y ningún icono decorativo; y quien ya tiene sesión va a donde le toca.
 */
final class AccesoMarcaTest extends BaseDatos {
    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function pantallas(): array {
        return ["login" => ["login", "Iniciar sesión"], "login con error" => ["login_error", "Iniciar sesión"], "registro" => ["registro", "Crear cuenta"],
            "registro con error" => ["registro_error", "Crear cuenta"], "olvide" => ["olvide", "Recuperar contraseña"], "olvide enviado" => ["olvide_ok", "Recuperar contraseña"],
            "restablecer válido" => ["restablecer_valido", "Nueva contraseña"], "restablecer inválido" => ["restablecer_invalido", "Nueva contraseña"]];
    }

    private function x(string $n): \DOMXPath {
        return Html::xpath(Pantallas::render($this->db, $n));
    }

    #[DataProvider("pantallas")]
    public function testLlevaElBloqueDeMarcaConLogoNombreYLemaEnUnEnlaceAlInicio(string $n, string $h1): void {
        $x = $this->x($n);
        $a = $x->query("//main//div[contains(@class,'marca-acceso')]/a[contains(@class,'marca-acceso-enlace')]");
        $this->assertSame(1, $a->length, $n);
        $this->assertSame(APP_URL . "/", $a->item(0)->getAttribute("href"));
        $this->assertSame(1, $x->query(".//svg[contains(@class,'marca-logo')]", $a->item(0))->length, "logo");
        $this->assertSame(\Marca::get("nombre"), trim($x->query(".//*[contains(@class,'marca-nombre')]", $a->item(0))->item(0)->textContent));
        $this->assertSame(\Marca::get("eslogan"), trim($x->query(".//*[contains(@class,'marca-lema')]", $a->item(0))->item(0)->textContent), "el lema se oculta por CSS bajo 768 px");
    }

    #[DataProvider("pantallas")]
    public function testElH1EsElProposito(string $n, string $h1): void {
        $x = $this->x($n);
        $this->assertSame(1, $x->query("//h1")->length);
        $this->assertSame($h1, trim($x->query("//h1")->item(0)->textContent));
        $this->assertStringContainsString($h1, $x->query("//title")->item(0)->textContent);
    }

    #[DataProvider("pantallas")]
    public function testNingunIconoDecorativoEnCamposNiBotonesDeEnvio(string $n, string $h1): void {
        $x = $this->x($n);
        $this->assertSame(0, $x->query("//*[contains(@class,'input-group-text')]")->length, "$n: sin iconos delante de los campos");
        $this->assertSame(0, $x->query("//button[@type='submit']//i | //a[contains(@class,'btn')]//i")->length, "$n: sin iconos en los botones principales");
        foreach ($x->query("//button[@type='button']") as $b) $this->assertNotSame("", $b->getAttribute("aria-label"), "$n: un botón solo de icono necesita nombre accesible");
    }

    #[DataProvider("pantallas")]
    public function testLasHojasDeEstiloSonLasMismasYEnElMismoOrden(string $n, string $h1): void {
        $x = $this->x($n);
        $hojas = [];
        foreach ($x->query("//head/link[@rel='stylesheet']") as $l) $hojas[] = preg_replace('/\?v=\d+$/', "", basename(parse_url($l->getAttribute("href"), PHP_URL_PATH)));
        $this->assertSame(["bootstrap.min.css", "bootstrap-icons.min.css", "fuentes.css", "tema.css", "style.css", "extra.css", "base.css"], $hojas, $n);
    }

    #[DataProvider("pantallas")]
    public function testLosEnlacesDeApoyoMidenAlMenos44(string $n, string $h1): void {
        $x = $this->x($n);
        $this->assertGreaterThanOrEqual(1, $x->query("//main//a[contains(@class,'acceso-enlace') or contains(@class,'btn')]")->length, $n);
        foreach ($x->query("//main//a[not(contains(@class,'btn')) and not(contains(@class,'acceso-enlace')) and not(contains(@class,'marca-acceso-enlace'))]") as $a) {
            $this->fail("$n: un enlace sin la clase de 44 px: " . trim($a->textContent));
        }
    }

    public function testElBotonDeVerLaContrasenaSigueFuncionalConSuNombre(): void {
        foreach (["login", "registro", "restablecer_valido"] as $n) {
            $x = $this->x($n);
            $b = $x->query("//button[@aria-controls='pf']")->item(0);
            $this->assertNotNull($b, $n);
            $this->assertSame("Mostrar u ocultar la contraseña", $b->getAttribute("aria-label"));
            $this->assertSame("password", $x->query("//input[@id='pf']")->item(0)->getAttribute("type"));
        }
    }

    public function testElLoginConservaElCorreoYLosMensajes(): void {
        $x = $this->x("login_error");
        $this->assertSame("Correo o contraseña incorrectos.", trim($x->query("//div[@role='alert']")->item(0)->textContent));
        $this->assertSame("username", $x->query("//input[@name='email']")->item(0)->getAttribute("autocomplete"));
    }
}
