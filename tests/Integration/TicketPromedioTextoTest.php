<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/** S6b.3: texto de la tarjeta «Ticket promedio» del dashboard. */
final class TicketPromedioTextoTest extends TestCase {
    public function testLaNotaDelTicketPromedioHablaDelClienteYNoDeLaVentaMedia(): void {
        $src = (string)file_get_contents(BASE_PATH . "/app/Controllers/DashboardController.php");
        $this->assertStringContainsString('"nota" => "Lo que gasta cada cliente por pedido"', $src);
        $this->assertStringNotContainsString("Venta media por pedido", $src);
    }
}
