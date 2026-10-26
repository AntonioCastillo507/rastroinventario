<?php
namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Carrito (sesión): solo pieza y cantidad, normalización de la forma antigua, deshacer con caducidad y huella. Sin base de datos. */
final class CarritoTest extends TestCase {
    protected function setUp(): void { parent::setUp(); $_SESSION = []; }
    protected function tearDown(): void { $_SESSION = []; parent::tearDown(); }

    public function testLaFormaNuevaEsPiezaYCantidad(): void {
        \Carrito::poner(7, 3);
        \Carrito::poner(9, 1);
        $this->assertSame([7 => 3, 9 => 1], \Carrito::leer());
        $this->assertSame(3, \Carrito::cantidad(7));
        $this->assertSame(0, \Carrito::cantidad(8));
        $this->assertSame([7 => 3, 9 => 1], $_SESSION["cart"]);
    }

    public function testLaFormaAntiguaConPreciosSeNormalizaYElPrecioViejoSeDescarta(): void {
        $_SESSION["cart"] = [5 => ["parte_id" => 5, "nombre" => "Faro", "precio" => 0.01, "cantidad" => 2, "max" => 9, "thumbnail" => null, "marca" => "x"]];
        $this->assertSame([5 => 2], \Carrito::leer());
        $this->assertSame([5 => 2], $_SESSION["cart"], "queda guardado sin precio ni nombre");
    }

    /** @return array<string,array{0:mixed}> */
    public static function carritosRaros(): array {
        return ["texto" => ["hola"], "cantidad cero" => [[3 => 0]], "negativa" => [[3 => -2]], "id cero" => [[0 => 4]], "no numérica" => [[3 => "abc"]],
            "arreglo sin cantidad" => [[3 => ["nombre" => "x"]]], "nulo" => [null]];
    }

    #[DataProvider("carritosRaros")]
    public function testUnCarritoRaroQuedaVacio(mixed $raro): void {
        $_SESSION["cart"] = $raro;
        $this->assertSame([], \Carrito::leer());
        $this->assertArrayNotHasKey("cart", $_SESSION);
    }

    public function testQuitarYVaciar(): void {
        \Carrito::poner(1, 2); \Carrito::poner(2, 2);
        \Carrito::quitar(1);
        $this->assertSame([2 => 2], \Carrito::leer());
        \Carrito::poner(2, 0);
        $this->assertArrayNotHasKey("cart", $_SESSION);
        \Carrito::poner(3, 1); \Carrito::recordarQuitada(3, 1, "Faro");
        \Carrito::vaciar();
        $this->assertArrayNotHasKey("cart", $_SESSION);
        $this->assertNull(\Carrito::quitadaReciente());
    }

    public function testDeshacerGuardaUnNivelYCaduca(): void {
        \Carrito::recordarQuitada(4, 2, "Faro de prueba");
        $this->assertSame(["id" => 4, "cantidad" => 2, "nombre" => "Faro de prueba"], \Carrito::quitadaReciente());
        \Carrito::recordarQuitada(6, 1, "Otra"); // reemplaza: solo se deshace lo último
        $this->assertSame(6, \Carrito::quitadaReciente()["id"]);
        $_SESSION["cart_deshacer"]["hasta"] = time() - 1;
        $this->assertNull(\Carrito::quitadaReciente());
        $this->assertArrayNotHasKey("cart_deshacer", $_SESSION, "lo caducado se borra");
        \Carrito::recordarQuitada(6, 1, "Otra");
        \Carrito::olvidarQuitada();
        $this->assertNull(\Carrito::quitadaReciente());
    }

    public function testLaHuellaDependeDeLaPiezaLaCantidadYElPrecioPeroNoDelOrden(): void {
        $a = [["id" => 1, "cantidad" => 2, "precio" => 10.0], ["id" => 2, "cantidad" => 1, "precio" => 5.5]];
        $base = \Carrito::huella($a);
        $this->assertSame($base, \Carrito::huella(array_reverse($a)));
        $this->assertNotSame($base, \Carrito::huella([["id" => 1, "cantidad" => 3, "precio" => 10.0], $a[1]]), "cantidad");
        $this->assertNotSame($base, \Carrito::huella([["id" => 1, "cantidad" => 2, "precio" => 10.01], $a[1]]), "un centavo de precio");
        $this->assertNotSame($base, \Carrito::huella([["id" => 3, "cantidad" => 2, "precio" => 10.0], $a[1]]), "otra pieza");
        $this->assertNotSame($base, \Carrito::huella([$a[0]]), "una línea menos");
        $this->assertSame(64, strlen($base));
    }
}
