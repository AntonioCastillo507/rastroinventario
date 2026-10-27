<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/**
 * S6b.1: detalle del pedido como página de pedido (cabecera con acciones, línea de estado, piezas y cliente).
 */
final class PedidoDetalleRedisenoTest extends BaseDatos {
    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
    }

    private function pantalla(?string $estado = null, ?string $notas = null): \DOMXPath {
        Pantallas::render($this->db, "admin_pedido_detalle");
        $id = (int)$this->db->query("SELECT MAX(id) FROM pedidos")->fetchColumn();
        if ($estado !== null) $this->db->prepare("UPDATE pedidos SET estado = ? WHERE id = ?")->execute([$estado, $id]);
        if ($notas !== null) $this->db->prepare("UPDATE pedidos SET cliente_notas = ? WHERE id = ?")->execute([$notas, $id]);
        return Html::xpath(Pantallas::render($this->db, "admin_pedido_detalle"));
    }

    public function testCabeceraConTituloEstadoVolverYAcciones(): void {
        $x = $this->pantalla();
        $this->assertSame(1, Html::contar($x, "//h1[starts-with(normalize-space(.), 'Pedido #')]"));
        $this->assertSame(1, Html::contar($x, "//main//a[contains(@class,'enlace-volver')][contains(@href,'mod=pedidos')]/i[contains(@class,'bi-arrow-left')]"));
        $this->assertSame(1, Html::contar($x, "//span[contains(@class,'pedido-estado')][contains(@class,'chip-warning')][normalize-space(.)='Pendiente']"));
        $this->assertSame(1, Html::contar($x, "//div[contains(@class,'pedido-acciones')]//form[contains(@action,'action=cambiarEstado')]"));
        $this->assertSame(1, Html::contar($x, "//div[contains(@class,'pedido-acciones')]//a[contains(@href,'mod=factura')]"), "una sola factura");
        $this->assertSame(1, Html::contar($x, "//a[contains(@href,'mod=factura')]"));
    }

    public function testElCambioDeEstadoConservaElModalYSusTransiciones(): void {
        $x = $this->pantalla();
        $f = $x->query("//form[contains(@action,'action=cambiarEstado')]")->item(0);
        $this->assertSame("estado", $f->getAttribute("data-confirmar-campo"));
        $this->assertSame(1, Html::contar($x, "//form[contains(@action,'action=cambiarEstado')]//input[@name='csrf_token']"));
        $op = [];
        foreach ($x->query("//select[@name='estado']/option[@value!='']") as $o) $op[] = $o->getAttribute("value");
        $this->assertEqualsCanonicalizing(\PedidoModel::transicionesPermitidas("pendiente"), $op);
    }

    public function testDosColumnasConPiezasYResumenAlLadoDelCliente(): void {
        $x = $this->pantalla();
        $this->assertSame(1, Html::contar($x, "//div[contains(@class,'pedido-grid')]/div[contains(@class,'pedido-principal')]//h2[normalize-space(.)='Piezas']"));
        $this->assertSame(1, Html::contar($x, "//div[contains(@class,'pedido-grid')]/aside[contains(@class,'pedido-lateral')]//h2[normalize-space(.)='Cliente']"));
        $this->assertSame(1, Html::contar($x, "//li[contains(@class,'pedido-linea-item')]"));
        $this->assertSame(1, Html::contar($x, "//div[contains(@class,'pedido-linea-texto')]//span[contains(@class,'num')][starts-with(normalize-space(.),'$')]"), "precio unitario");
        $this->assertSame(1, Html::contar($x, "//li[contains(@class,'pedido-linea-item')]//span[contains(@class,'pedido-linea-subtotal')][contains(@class,'num')]"));
        $this->assertStringContainsString("25.50", $x->query("//span[contains(@class,'pedido-linea-subtotal')]")->item(0)->textContent);
        $this->assertSame(3, Html::contar($x, "//dl[contains(@class,'pedido-resumen')]/div"), "subtotal, impuesto y total");
        $this->assertStringContainsString("Total", $x->query("//div[contains(@class,'pedido-resumen-total')]")->item(0)->textContent);
    }

    public function testMarcaYModeloDeLaPiezaSalenEnLaLinea(): void {
        $x = $this->pantalla();
        $marca = (string)$this->db->query("SELECT a.marca FROM partes p JOIN autos a ON a.id = p.auto_id WHERE p.nombre = 'Pieza de pantalla' LIMIT 1")->fetchColumn();
        $this->assertNotSame("", $marca);
        $this->assertStringContainsString($marca, $x->query("//li[contains(@class,'pedido-linea-item')]")->item(0)->textContent);
    }

    public function testLaMiniaturaSoloSaleSiElArchivoExiste(): void {
        $x = $this->pantalla();
        $this->assertSame(0, Html::contar($x, "//li[contains(@class,'pedido-linea-item')]//*[contains(@class,'foto-baldosa')]"), "la pieza de prueba no tiene archivo en disco: sin miniatura ni marcador");
        $this->assertSame(0, Html::contar($x, "//li[contains(@class,'pedido-linea-item')]//img"));
    }

    public function testClienteConEnlacesMailtoYTelYDireccion(): void {
        $x = $this->pantalla();
        $this->assertSame(1, Html::contar($x, "//aside//a[starts-with(@href,'mailto:')]"));
        $this->assertSame(1, Html::contar($x, "//aside//a[starts-with(@href,'tel:')]"));
        $this->assertSame(1, Html::contar($x, "//aside//dt[normalize-space(.)='Dirección de entrega']"));
    }

    public function testElMensajeDelClienteSoloSaleSiLoHay(): void {
        $this->assertSame(0, Html::contar($this->pantalla(), "//h2[normalize-space(.)='Mensaje del cliente']"));
        $x = $this->pantalla(null, "Dejar con el vigilante <b>");
        $this->assertSame(1, Html::contar($x, "//aside//h2[normalize-space(.)='Mensaje del cliente']"));
        $this->assertStringContainsString("Dejar con el vigilante <b>", $x->query("//p[contains(@class,'pedido-mensaje')]")->item(0)->textContent);
        $this->assertSame(0, Html::contar($x, "//p[contains(@class,'pedido-mensaje')]//b"), "escapado");
    }

    public function testLineaDeTiempoMarcaElPasoActualYLosAnteriores(): void {
        $x = $this->pantalla("procesando");
        $this->assertSame(3, Html::contar($x, "//ol[contains(@class,'pedido-linea')]/li"));
        $this->assertSame(2, Html::contar($x, "//ol[contains(@class,'pedido-linea')]/li[contains(@class,'hecho')]"));
        $this->assertSame(1, Html::contar($x, "//ol[contains(@class,'pedido-linea')]/li[@aria-current='step'][contains(.,'Procesando')]"));
    }

    public function testCanceladoVaAparteYElEntregadoQuedaBloqueado(): void {
        $x = $this->pantalla("cancelado");
        $this->assertSame(4, Html::contar($x, "//ol[contains(@class,'pedido-linea')]/li"));
        $this->assertSame(0, Html::contar($x, "//ol[contains(@class,'pedido-linea')]/li[contains(@class,'hecho')]"));
        $this->assertSame(1, Html::contar($x, "//ol[contains(@class,'pedido-linea')]/li[contains(@class,'cancelado')][@aria-current='step']"));
        $this->assertSame(1, Html::contar($x, "//span[contains(@class,'pedido-estado')][contains(@class,'chip-danger')]"));
        $e = $this->pantalla("entregado");
        $this->assertSame(0, Html::contar($e, "//form[contains(@action,'action=cambiarEstado')]"), "entregado no se cambia");
        $this->assertSame(3, Html::contar($e, "//ol[contains(@class,'pedido-linea')]/li[contains(@class,'hecho')]"));
        $this->assertSame(1, Html::contar($e, "//a[contains(@href,'mod=factura')]"), "la factura sigue disponible");
    }

    public function testElCssDelPedidoUsaTokensYTocaEn44(): void {
        $css = (string)file_get_contents(BASE_PATH . "/assets/css/panel.css");
        $this->assertMatchesRegularExpression('/\.pedido-grid \{[^}]*grid-template-columns: minmax\(0, 1fr\)/', $css, "apilado en móvil");
        $this->assertMatchesRegularExpression('/min-width: 992px\) \{ \.pedido-grid \{ grid-template-columns: minmax\(0, 1fr\) 360px/', $css, "dos columnas en escritorio");
        $this->assertMatchesRegularExpression('/\.pedido-acciones \.btn \{[^}]*min-height: 44px/', $css);
        $this->assertMatchesRegularExpression('/\.pedido-datos a \{[^}]*min-height: 44px/', $css);
        $bloque = substr($css, (int)strpos($css, "Detalle del pedido (S6b.1)"));
        $this->assertSame(0, preg_match('/#[0-9a-fA-F]{3,6}\b/', $bloque), "sin colores sueltos");
    }
}
