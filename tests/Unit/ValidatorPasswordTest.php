<?php
namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Política de contraseñas (hallazgo 14a): Validator::password() */
final class ValidatorPasswordTest extends TestCase {
    private function error(mixed $clave, string $email = "", string $nombre = ""): string {
        return (new \Validator())->password("clave", $clave, $email, $nombre)->first();
    }

    public static function aceptadas(): array {
        return [
            "8 caracteres justos"           => ["Qx7!mZ4k"],
            "frase con espacios"            => ["tortuga azul vuela alto 9"],
            "espacios al borde se respetan" => ["  ocho  espacios  "],
            "8 letras con acento"           => ["ñáéíóúüÑ"],        // 8 caracteres y 16 bytes
            "72 bytes ASCII"                => [str_repeat("Aa1!", 18)],
            "72 bytes con acentos"          => [str_repeat("éàüñ", 9)],
            "con emoji"                     => ["café-🚗-rápido"],
        ];
    }

    #[DataProvider("aceptadas")]
    public function testAceptaClavesValidas(string $clave): void {
        $this->assertSame("", $this->error($clave, "persona@example.invalid", "Persona Prueba"));
    }

    public static function rechazadas(): array {
        return [
            "7 caracteres"                  => ["Qx7!mZ4", "al menos 8 caracteres"],
            "7 letras con acento"           => ["ñáéíóúü", "al menos 8 caracteres"],
            "vacía"                         => ["", "al menos 8 caracteres"],
            "73 bytes ASCII"                => [str_repeat("Aa1!", 18) . "x", "demasiado larga"],
            "73 bytes con acentos"          => [str_repeat("éàüñ", 9) . "a", "demasiado larga"],
            "común exacta"                  => ["12345678", "demasiado común"],
            "común con mayúsculas"          => ["Password", "demasiado común"],
            "común en teclado"              => ["QWERTYUI", "demasiado común"],
            "base común con números"        => ["password123!", "demasiado común"],
            "pocos caracteres distintos"    => ["abababab", "demasiado simple"],
            "un solo carácter repetido"     => ["aaaaaaaa", "demasiado simple"],
            "byte nulo"                     => ["abcd\0efghij", "no permitidos"],
            "UTF-8 inválido"                => ["\xff\xfe\xfd\xfcabcdefgh", "no permitidos"],
        ];
    }

    #[DataProvider("rechazadas")]
    public function testRechazaClavesInvalidas(string $clave, string $mensaje): void {
        $this->assertStringContainsString($mensaje, $this->error($clave, "persona@example.invalid", "Persona Prueba"));
    }

    public function testLosLimitesSonLosDeLaPolitica(): void {
        $this->assertSame(8, \Validator::PASS_MIN);
        $this->assertSame(72, \Validator::PASS_MAX);
    }

    public function testRechazaLoQueNoEsTexto(): void {
        foreach ([null, ["Qx7!mZ4k"], 12345678, 1.5, true] as $valor) {
            $this->assertStringContainsString("no permitidos", $this->error($valor), var_export($valor, true));
        }
    }

    public function testRechazaClaveIgualAlCorreoOAlNombre(): void {
        $msg = "ni a tu nombre";
        $this->assertStringContainsString($msg, $this->error("persona@example.invalid", "persona@example.invalid"));
        $this->assertStringContainsString($msg, $this->error("PERSONAEJEMPLO", "personaejemplo@example.invalid"), "parte local del correo, sin mayúsculas");
        $this->assertStringContainsString($msg, $this->error("María Fernanda López", "x@example.invalid", "Maria Fernanda Lopez"), "sin acentos ni mayúsculas");
        $this->assertStringContainsString($msg, $this->error("maria fernanda lopez", "x@example.invalid", "María  Fernanda López"), "sin espacios");
    }

    public function testClaveDistintaDelCorreoYDelNombrePasa(): void {
        $this->assertSame("", $this->error("Qx7!mZ4k", "personaejemplo@example.invalid", "Persona Ejemplo"));
    }

    public function testElErrorQuedaEnElCampoIndicado(): void {
        $v = (new \Validator())->password("pass", "corta");
        $this->assertFalse($v->isValid());
        $this->assertArrayHasKey("pass", $v->getErrors());
    }

    public function testEsComunNoDependeDeAcentosNiMayusculas(): void {
        $this->assertStringContainsString("demasiado común", $this->error("CONTRASEÑA"));
    }
}
