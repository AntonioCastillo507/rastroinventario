<?php
/**
 * FacturaController — Genera facturas electrónicas en PDF con TCPDF.
 * Firma digital via OpenSSL (TCPDF Example 052 / PDF/A).
 * Incluye cálculo del 7% de ITBMS (Impuesto de Transferencia de Bienes Muebles).
 * Requiere: composer require tecnickcom/tcpdf
 */
class FacturaController extends BaseController {

    private function loadTcpdf(): void {
        $path = BASE_PATH.DIRECTORY_SEPARATOR."vendor".DIRECTORY_SEPARATOR."tecnickcom"
               .DIRECTORY_SEPARATOR."tcpdf".DIRECTORY_SEPARATOR."tcpdf.php";
        if (!file_exists($path)) {
            error_log("[RASTRO] TCPDF no instalado: falta vendor/ (ejecutar composer install, ver INSTRUCCIONES.md)");
            http_response_code(503);
            die("El servicio de facturas no está disponible por ahora.");
        }
        require_once BASE_PATH."/vendor/autoload.php";
    }

    /** Genera factura para admin (desde panel de pedidos) */
    public function generar(): void {
        $this->requirePermission("pedidos");
        $id = Sanitizer::get("id","int");
        $pedido = (new PedidoModel())->findWithItems($id);
        if (!$pedido) { http_response_code(404); die("Pedido no encontrado."); }
        $this->generarPDF($pedido);
    }

    /** Genera factura para cliente (desde compra exitosa — valida que sea su pedido) */
    public function generarPublico(): void {
        $this->requireAuth();
        $id = Sanitizer::get("pedido","int");
        $pedido = (new PedidoModel())->findWithItems($id);
        // Mismo mensaje si no existe o no es suyo, para no revelar qué pedidos hay
        if (!$pedido || !$this->puedeVerPedido($pedido)) { http_response_code(404); die("Pedido no encontrado."); }
        $this->generarPDF($pedido);
    }

    /** Dueño del pedido, o personal con permiso de ver pedidos */
    private function puedeVerPedido(array $pedido): bool {
        $uid = (int)$_SESSION["user"]["id"];
        if (!empty($pedido["usuario_id"]) && (int)$pedido["usuario_id"] === $uid) return true;
        return (new UserModel())->hasPermission($uid, "pedidos", "puede_ver");
    }

    private function generarPDF(array $pedido): void {
        $this->loadTcpdf();

        // ── Cálculos de ITBMS ───────────────────────────────────
        // Lo que se cobró está guardado en el pedido: itbms = total - subtotal
        ["subtotal" => $subtotal, "itbms" => $itbms, "total" => $total] = Itbms::dePedido($pedido);
        $numFactura = str_pad($pedido["id"], 6, "0", STR_PAD_LEFT);
        $fecha      = date("d/m/Y H:i");

        // ── Firma RSA del contenido de la factura ────────────────
        $rsa   = new RsaCrypto();
        $datosAFirmar = "FACTURA:{$numFactura}|PEDIDO:{$pedido["id"]}|TOTAL:{$total}|FECHA:{$fecha}|CLIENTE:{$pedido["cliente_email"]}";
        $firma = $rsa->firmar($datosAFirmar);
        $firmaCorta = $firma ? substr($firma, 0, 40)."..." : "N/D";

        // ── Crear PDF ────────────────────────────────────────────
        $pdf = new TCPDF("P", "mm", "A4", true, "UTF-8", false);
        $pdf->SetCreator("Rastro del Istmo");
        $pdf->SetAuthor("Rastro del Istmo - Sistema de Inventario");
        $pdf->SetTitle("Factura #{$numFactura}");
        $pdf->SetSubject("Factura Electrónica de Venta");
        $pdf->SetKeywords("factura, ITBMS, rastro, Panama");
        $pdf->setHeaderFont(["helvetica","",10]);
        $pdf->setFooterFont(["helvetica","",8]);
        $pdf->SetDefaultMonospacedFont("courier");
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(true, 25);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // ── Firma digital con certificado OpenSSL ─────────────────
        $certContent = $rsa->getCertContent();
        $keyContent  = $rsa->getKeyContent();
        if ($certContent && $keyContent) {
            $info = [
                "Name"        => "Rastro del Istmo",
                "Location"    => "Ciudad de Panama, Panama",
                "Reason"      => "Factura Electronica de Venta - ITBMS 7%",
                "ContactInfo" => "admin@rastrodelistmo.com",
            ];
            $pdf->setSignature($certContent, $keyContent, $rsa->getKeyPassphrase(), "", 2, $info);
        }

        $pdf->AddPage();

        // ── ENCABEZADO ───────────────────────────────────────────
        $pdf->SetFillColor(11, 14, 17);  // --bg-0
        $pdf->Rect(0, 0, 210, 38, "F");
        $pdf->SetTextColor(240, 185, 11); // --accent dorado
        $pdf->SetFont("helvetica","B",20);
        $pdf->SetXY(15, 10);
        $pdf->Cell(0, 10, "RASTRO DEL ISTMO", 0, 1, "L");
        $pdf->SetFont("helvetica","",9);
        $pdf->SetTextColor(132, 142, 156);
        $pdf->SetXY(15, 22);
        $pdf->Cell(0, 5, "Ciudad de Panama, Republica de Panama  |  +507 6542-0519  |  chapimaster507@gmail.com", 0, 1, "L");

        $pdf->SetTextColor(240, 185, 11);
        $pdf->SetFont("helvetica","B",14);
        $pdf->SetXY(130, 10);
        $pdf->Cell(65, 8, "FACTURA ELECTRONICA", 0, 1, "R");
        $pdf->SetFont("helvetica","B",11);
        $pdf->SetTextColor(255,255,255);
        $pdf->SetXY(130, 20);
        $pdf->Cell(65, 6, "No. {$numFactura}", 0, 1, "R");
        $pdf->SetFont("helvetica","",9);
        $pdf->SetTextColor(132,142,156);
        $pdf->SetXY(130, 28);
        $pdf->Cell(65, 5, "Fecha: {$fecha} (Panama)", 0, 1, "R");

        $pdf->SetY(44);
        $pdf->SetFillColor(30, 32, 38);
        $pdf->SetTextColor(240, 185, 11);
        $pdf->SetFont("helvetica","B",10);
        $pdf->SetDrawColor(240, 185, 11);
        $pdf->SetLineWidth(0.3);

        // ── DATOS DEL CLIENTE ────────────────────────────────────
        $pdf->SetFillColor(30, 32, 38);
        $pdf->Rect(15, 44, 180, 6, "F");
        $pdf->SetXY(15, 45);
        $pdf->SetTextColor(240,185,11);
        $pdf->SetFont("helvetica","B",10);
        $pdf->Cell(180, 5, "DATOS DEL CLIENTE", 0, 1, "L");
        $pdf->SetFont("helvetica","",9);
        $pdf->SetTextColor(50,50,50);
        $campos = [
            "Nombre"   => $pedido["cliente_nombre"],
            "Correo"   => $pedido["cliente_email"],
            "Telefono" => $pedido["cliente_telefono"],
            "Direccion"=> $pedido["cliente_direccion"],
        ];
        $pdf->SetXY(15, 53);
        foreach ($campos as $label => $valor) {
            $pdf->SetFont("helvetica","B",9); $pdf->Cell(30, 5, $label.":", 0, 0, "L");
            $pdf->SetFont("helvetica","",9);  $pdf->Cell(150, 5, $valor, 0, 1, "L");
        }

        // ── TABLA DE ITEMS ───────────────────────────────────────
        $pdf->SetY($pdf->GetY() + 6);
        $pdf->SetFillColor(11,14,17);
        $pdf->SetTextColor(255,255,255);
        $pdf->SetFont("helvetica","B",9);
        $pdf->SetFillColor(30,32,38);
        $pdf->Cell(90, 7, "DESCRIPCION", 1, 0, "C", true);
        $pdf->Cell(20, 7, "CANT.", 1, 0, "C", true);
        $pdf->Cell(35, 7, "PRECIO UNIT. ($)", 1, 0, "C", true);
        $pdf->Cell(35, 7, "SUBTOTAL ($)", 1, 1, "C", true);

        $pdf->SetFont("helvetica","",9);
        $pdf->SetTextColor(30,30,30);
        $alt = false;
        foreach ($pedido["items"] as $item) {
            $sub = number_format($item["precio_unitario"] * $item["cantidad"], 2);
            $fill = $alt ? [240,242,245] : [255,255,255];
            $pdf->SetFillColor(...$fill);
            $pdf->Cell(90, 6, $item["parte_nombre"], 1, 0, "L", true);
            $pdf->Cell(20, 6, $item["cantidad"],      1, 0, "C", true);
            $pdf->Cell(35, 6, number_format($item["precio_unitario"],2), 1, 0, "R", true);
            $pdf->Cell(35, 6, $sub, 1, 1, "R", true);
            $alt = !$alt;
        }

        // ── TOTALES ──────────────────────────────────────────────
        $pdf->SetY($pdf->GetY() + 4);
        $pdf->SetFont("helvetica","",10); $pdf->SetTextColor(50,50,50);
        $pdf->SetX(120);
        $pdf->Cell(55, 6, "Subtotal:", "TB", 0, "R");
        $pdf->Cell(25, 6, "$ ".number_format($subtotal,2), "TB", 1, "R");
        $pdf->SetX(120);
        $pdf->Cell(55, 6, "ITBMS (7%):", "B", 0, "R");
        $pdf->Cell(25, 6, "$ ".number_format($itbms,2), "B", 1, "R");
        $pdf->SetFont("helvetica","B",11);
        $pdf->SetFillColor(240,185,11);
        $pdf->SetTextColor(0,0,0);
        $pdf->SetX(120);
        $pdf->Cell(55, 8, "TOTAL A PAGAR:", "B", 0, "R", true);
        $pdf->Cell(25, 8, "$ ".number_format($total,2), "B", 1, "R", true);

        // ── FIRMA DIGITAL ────────────────────────────────────────
        $pdf->SetY($pdf->GetY() + 10);
        $pdf->SetFont("helvetica","",8);
        $pdf->SetTextColor(100,100,100);
        $pdf->MultiCell(180, 4,
            "FIRMA DIGITAL (HMAC-SHA256 + RSA-OpenSSL)\n".
            "Pedido #{$numFactura} | Total: \$".number_format($total,2)." incl. ITBMS 7%\n".
            "Hash: ".$firmaCorta."\n".
            "Este documento tiene validez legal como comprobante de compra. Rastro del Istmo - Panama.", 1, "L");
        $pdf->SetFont("helvetica","I",7);
        $pdf->SetY($pdf->GetY() + 4);
        $pdf->Cell(180, 4, "Nota: El ITBMS corresponde al Impuesto de Transferencia de Bienes Muebles y Servicios (Ley 75 de 1976, Panama).", 0, 1, "C");

        // ── Campo de firma visible TCPDF ─────────────────────────
        if ($certContent && $keyContent) {
            $pdf->addEmptySignatureAppearance(15, $pdf->GetY() + 5, 80, 25);
        }

        $pdf->Output("factura_{$numFactura}.pdf", "D"); // D = descarga directa
        exit;
    }
}
