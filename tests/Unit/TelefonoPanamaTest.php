<?php
namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** DireccionPanama::telefono: celular de 8 dígitos que empieza por 6 o fijo de 7 dígitos, con prefijo y separadores opcionales */
final class TelefonoPanamaTest extends TestCase {
    public static function validos(): array {
        return [
            "celular"                 => ["65420519", "6542-0519"],
            "celular con guion"       => ["6542-0519", "6542-0519"],
            "celular con espacios"    => ["6542 0519", "6542-0519"],
            "celular +507"            => ["+507 6542-0519", "6542-0519"],
            "celular +507 pegado"     => ["+50765420519", "6542-0519"],
            "celular 507"             => ["50765420519", "6542-0519"],
            "celular 00507"           => ["00507 6542 0519", "6542-0519"],
            "celular con parentesis"  => ["(507) 6542-0519", "6542-0519"],
            "fijo"                    => ["2641234", "264-1234"],
            "fijo con guion"          => ["264-1234", "264-1234"],
            "fijo otro primer digito" => ["3891234", "389-1234"],
            "fijo con 5"              => ["5071234", "507-1234"],
            "fijo empieza por 8"      => ["8231234", "823-1234"],
            "fijo +507"               => ["+507 264-1234", "264-1234"],
            "con puntos"              => ["6542.0519", "6542-0519"],
            "con espacios al borde"   => ["  6542-0519  ", "6542-0519"],
        ];
    }

    #[DataProvider("validos")]
    public function testValidos(string $entrada, string $esperado): void {
        $this->assertSame($esperado, \DireccionPanama::telefono($entrada));
    }

    public static function invalidos(): array {
        return array_map(fn($v) => [$v], [
            "", "   ", "abc", "6542-05", "654205190", "7542-0519", "5542-0519", "1234", "123456", "12345678", "+1 6542-0519", "+507 7542-0519",
            "66666666", "6666666", "0000000", "1234567", "9876543", "6542-0519 ext 2", "6542/0519", "65420519; DROP", "<6542>", "+507+6542", "++50765420519",
            "6542-0519-77", "5066542051", "+507 654205", "6\x0054205190",
        ]);
    }

    #[DataProvider("invalidos")]
    public function testInvalidos(string $entrada): void {
        $this->assertNull(\DireccionPanama::telefono($entrada), "debía rechazar «{$entrada}»");
    }

    public function testNoTextoSeRechaza(): void {
        $this->assertNull(\DireccionPanama::telefono(["65420519"]));
        $this->assertNull(\DireccionPanama::telefono(null));
        $this->assertNull(\DireccionPanama::telefono(65420519));
        $this->assertNull(\DireccionPanama::telefono("6542\xC3\x28519"));
    }
}
