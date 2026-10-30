<?php
namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** DireccionPanama::telefono: celular de 8 dígitos que empieza por 6 o fijo de 7 dígitos, con prefijo y separadores opcionales */
final class TelefonoPanamaTest extends TestCase {
    public static function validos(): array {
        return [
            "celular"                 => ["61234567", "6123-4567"],
            "celular con guion"       => ["6123-4567", "6123-4567"],
            "celular con espacios"    => ["6123 4567", "6123-4567"],
            "celular +507"            => ["+507 6123-4567", "6123-4567"],
            "celular +507 pegado"     => ["+50761234567", "6123-4567"],
            "celular 507"             => ["50761234567", "6123-4567"],
            "celular 00507"           => ["00507 6123 4567", "6123-4567"],
            "celular con parentesis"  => ["(507) 6123-4567", "6123-4567"],
            "fijo"                    => ["2641234", "264-1234"],
            "fijo con guion"          => ["264-1234", "264-1234"],
            "fijo otro primer digito" => ["3891234", "389-1234"],
            "fijo con 5"              => ["5071234", "507-1234"],
            "fijo empieza por 8"      => ["8231234", "823-1234"],
            "fijo +507"               => ["+507 264-1234", "264-1234"],
            "con puntos"              => ["6123.4567", "6123-4567"],
            "con espacios al borde"   => ["  6123-4567  ", "6123-4567"],
        ];
    }

    #[DataProvider("validos")]
    public function testValidos(string $entrada, string $esperado): void {
        $this->assertSame($esperado, \DireccionPanama::telefono($entrada));
    }

    public static function invalidos(): array {
        return array_map(fn($v) => [$v], [
            "", "   ", "abc", "6123-05", "612345670", "7123-4567", "5123-4567", "1234", "123456", "12345678", "+1 6123-4567", "+507 7123-4567",
            "66666666", "6666666", "0000000", "1234567", "9876543", "6123-4567 ext 2", "6123/4567", "61234567; DROP", "<6123>", "+507+6123", "++50761234567",
            "6123-4567-77", "5066123051", "+507 612305", "6\x0054245670",
        ]);
    }

    #[DataProvider("invalidos")]
    public function testInvalidos(string $entrada): void {
        $this->assertNull(\DireccionPanama::telefono($entrada), "debía rechazar «{$entrada}»");
    }

    public function testNoTextoSeRechaza(): void {
        $this->assertNull(\DireccionPanama::telefono(["61234567"]));
        $this->assertNull(\DireccionPanama::telefono(null));
        $this->assertNull(\DireccionPanama::telefono(61234567));
        $this->assertNull(\DireccionPanama::telefono("6123\xC3\x28519"));
    }
}
