<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guarda: los enlaces de ejemplo de redes y mensajería de marca.php no se publican sin marcar.
 * Una dirección sin cuenta (solo el dominio) debe llevar el comentario "enlace de ejemplo: reemplazar por la cuenta real
 * antes de publicar"; una dirección con cuenta real no puede conservar ese comentario.
 */
final class MarcaRedesEjemploTest extends TestCase {
    public const AVISO = "enlace de ejemplo: reemplazar por la cuenta real antes de publicar";

    /** @return list<string> problemas ("línea N: ...") en el código de marca.php */
    public static function revisar(string $codigo): array {
        $problemas = [];
        foreach (preg_split('/\R/', $codigo) as $n => $linea) {
            if (!preg_match('/"url"\s*=>\s*"([^"]*)"/', $linea, $m) || $m[1] === "") continue;
            $partes = parse_url($m[1]);
            $sinCuenta = trim((string)($partes["path"] ?? ""), "/") === "" && ($partes["query"] ?? "") === "";
            $marcada = stripos($linea, self::AVISO) !== false;
            if ($sinCuenta && !$marcada) $problemas[] = "línea " . ($n + 1) . ": dirección de ejemplo sin el aviso «" . self::AVISO . "»: {$m[1]}";
            if (!$sinCuenta && $marcada) $problemas[] = "línea " . ($n + 1) . ": cuenta real con el aviso de ejemplo todavía puesto: {$m[1]}";
        }
        return $problemas;
    }

    public function testLasDireccionesDeEjemploEstanMarcadas(): void {
        $this->assertSame([], self::revisar((string)file_get_contents(BASE_PATH . "/app/Config/marca.php")));
    }

    public function testDetectaUnaDireccionDeEjemploSinElAviso(): void {
        $this->assertCount(1, self::revisar('["nombre" => "TikTok", "url" => "https://tiktok.com/", "icono" => "tiktok"],'));
    }

    public function testDetectaElAvisoQuedadoEnUnaCuentaReal(): void {
        $this->assertCount(1, self::revisar('["nombre" => "X", "url" => "https://instagram.com/tienda", "icono" => "instagram"], // ' . self::AVISO));
    }

    public function testAceptaDireccionVaciaYCuentaRealSinAviso(): void {
        $this->assertSame([], self::revisar('["nombre" => "X", "url" => "", "icono" => "x"],' . "\n" . '["nombre" => "Y", "url" => "https://wa.me/50761234567", "icono" => "whatsapp"],'));
    }

    public function testElAvisoSeLeeEnLaMarcaDeVerdad(): void {
        $this->assertStringContainsString(self::AVISO, (string)file_get_contents(BASE_PATH . "/app/Config/marca.php"));
    }
}
