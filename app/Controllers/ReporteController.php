<?php
/**
 * ReporteController — Generación de reportes Excel con PhpSpreadsheet.
 * Requiere: composer require phpoffice/phpspreadsheet
 */
class ReporteController extends BaseController {

    private function checkSpreadsheet(): bool {
        $autoload = BASE_PATH.DIRECTORY_SEPARATOR."vendor".DIRECTORY_SEPARATOR."autoload.php";
        if (!file_exists($autoload)) {
            error_log("[RASTRO] PhpSpreadsheet no instalado: falta vendor/autoload.php (ejecutar composer install, ver INSTRUCCIONES.md)");
            $this->flash("Los reportes no están disponibles por ahora.", "danger");
            $this->redirect(APP_URL."/admin/?mod=reportes");
        }
        require_once $autoload;
        return true;
    }

    private function estiloHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $rango): void {
        $sheet->getStyle($rango)->applyFromArray([
            "font"      => ["bold"=>true,"color"=>["rgb"=>"FFFFFF"],"size"=>11],
            "fill"      => ["fillType"=>\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,"startColor"=>["rgb"=>"1E2026"]],
            "alignment" => ["horizontal"=>\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            "borders"   => ["allBorders"=>["borderStyle"=>\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,"color"=>["rgb"=>"F0B90B"]]],
        ]);
    }

    private function tituloPagina(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $titulo, int $cols): void {
        $colLetra = chr(64 + $cols);
        $sheet->mergeCells("A1:{$colLetra}1");
        $sheet->setCellValue("A1", $titulo);
        $sheet->getStyle("A1")->applyFromArray([
            "font" => ["bold"=>true,"size"=>14,"color"=>["rgb"=>"F0B90B"]],
            "fill" => ["fillType"=>\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,"startColor"=>["rgb"=>"0B0E11"]],
            "alignment" => ["horizontal"=>\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,"vertical"=>\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->mergeCells("A2:{$colLetra}2");
        $sheet->setCellValue("A2", "Rastro del Istmo — Generado el ".date("d/m/Y H:i")." (Panamá)");
        $sheet->getStyle("A2")->applyFromArray([
            "font" => ["italic"=>true,"size"=>9,"color"=>["rgb"=>"848E9C"]],
            "fill" => ["fillType"=>\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,"startColor"=>["rgb"=>"1E2026"]],
            "alignment" => ["horizontal"=>\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ]);
    }

    private function descargar(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, string $filename): void {
        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header("Cache-Control: max-age=0");
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save("php://output");
        exit;
    }

    // ── Vista principal de reportes ───────────────────────────────
    public function index(): void {
        $this->requirePermission("ventas");
        $db = Database::get();
        $totalPartes  = (int)$db->query("SELECT COUNT(*) FROM partes WHERE activo=1")->fetchColumn();
        $totalVentas  = (int)$db->query("SELECT COUNT(*) FROM vendido_parte")->fetchColumn();
        $totalPedidos = (int)$db->query("SELECT COUNT(*) FROM pedidos")->fetchColumn();
        $ingresos     = (float)$db->query("SELECT COALESCE(SUM(precio_venta*cantidad),0) FROM vendido_parte")->fetchColumn();
        $secciones    = $db->query("SELECT id, nombre FROM secciones WHERE activo=1 ORDER BY nombre")->fetchAll();
        $hayComposer  = file_exists(BASE_PATH."/vendor/autoload.php");
        $this->view("admin/reportes/index", compact("totalPartes","totalVentas","totalPedidos","ingresos","secciones","hayComposer"));
    }

    // ── Reporte 1: Inventario filtrado por categoría ──────────────
    public function excelInventario(): void {
        $this->requirePermission("ventas"); $this->checkSpreadsheet();
        $secId = Sanitizer::get("seccion","int");
        $db = Database::get();
        $sql = "SELECT s.nombre AS seccion, p.nombre, p.descripcion, a.marca, a.modelo, a.anio,
                       p.precio, p.cantidad, (p.precio*p.cantidad) AS valor_total
                FROM partes p
                LEFT JOIN secciones s ON s.id=p.seccion_id
                LEFT JOIN autos a ON a.id=p.auto_id
                WHERE p.activo=1";
        $params=[];
        if ($secId){ $sql.=" AND p.seccion_id=?"; $params[]=$secId; }
        $sql.=" ORDER BY s.nombre, p.nombre";
        $st=$db->prepare($sql); $st->execute($params);
        $data=$st->fetchAll();

        $sp = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sh = $sp->getActiveSheet()->setTitle("Inventario");
        $this->tituloPagina($sh, "REPORTE DE INVENTARIO — RASTRO DEL ISTMO", 9);
        $cols=["A"=>"#","B"=>"Sección","C"=>"Nombre de la Pieza","D"=>"Descripción","E"=>"Auto (Marca/Modelo)","F"=>"Año","G"=>"Precio ($)","H"=>"Stock","I"=>"Valor Total ($)"];
        $r=3; foreach($cols as $col=>$h){ $sh->setCellValue($col.$r,$h); $sh->getColumnDimension($col)->setAutoSize(true); }
        $this->estiloHeader($sh,"A3:I3");
        $r=4; $vtotal=0;
        foreach($data as $i=>$p){
            $sh->setCellValue("A$r",$i+1)->setCellValue("B$r",$p["seccion"]??"")->setCellValue("C$r",$p["nombre"])->setCellValue("D$r",$p["descripcion"]??"")->setCellValue("E$r",trim($p["marca"]." ".$p["modelo"]))->setCellValue("F$r",$p["anio"])->setCellValue("G$r",(float)$p["precio"])->setCellValue("H$r",(int)$p["cantidad"])->setCellValue("I$r",(float)$p["valor_total"]);
            $sh->getStyle("G$r:I$r")->getNumberFormat()->setFormatCode("0.00");
            $vtotal+=(float)$p["valor_total"]; $r++;
        }
        $sh->setCellValue("H$r","TOTAL INVENTARIO:")->setCellValue("I$r",$vtotal);
        $sh->getStyle("H$r:I$r")->getFont()->setBold(true);
        $sh->getStyle("I$r")->getNumberFormat()->setFormatCode("0.00");
        $this->descargar($sp, "inventario_".date("Y-m-d").".xlsx");
    }

    // ── Reporte 2: Estadísticas de ventas por mes ─────────────────
    public function excelVentasMes(): void {
        $this->requirePermission("ventas"); $this->checkSpreadsheet();
        $db = Database::get();
        $data = $db->query(
            "SELECT YEAR(v.fecha) AS anio,
                    MONTH(v.fecha) AS mes_num,
                    DATE_FORMAT(v.fecha, '%M %Y') AS mes_nombre,
                    COUNT(*) AS total_ventas,
                    SUM(v.cantidad) AS total_unidades,
                    SUM(v.precio_venta*v.cantidad) AS total_monto
             FROM vendido_parte v
             GROUP BY YEAR(v.fecha),MONTH(v.fecha)
             ORDER BY anio DESC, mes_num DESC"
        )->fetchAll();

        $sp = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sh = $sp->getActiveSheet()->setTitle("Ventas por Mes");
        $this->tituloPagina($sh,"ESTADÍSTICAS DE VENTAS POR MES",6);
        $cols=["A"=>"#","B"=>"Mes/Año","C"=>"Total Ventas","D"=>"Unidades Vendidas","E"=>"Monto Total ($)","F"=>"Promedio por Venta ($)"];
        $r=3; foreach($cols as $col=>$h){ $sh->setCellValue($col.$r,$h); $sh->getColumnDimension($col)->setAutoSize(true); }
        $this->estiloHeader($sh,"A3:F3");
        $r=4; foreach($data as $i=>$row){
            $prom=$row["total_ventas"]>0?round($row["total_monto"]/$row["total_ventas"],2):0;
            $sh->setCellValue("A$r",$i+1)->setCellValue("B$r",$row["mes_nombre"])->setCellValue("C$r",(int)$row["total_ventas"])->setCellValue("D$r",(int)$row["total_unidades"])->setCellValue("E$r",(float)$row["total_monto"])->setCellValue("F$r",$prom);
            $sh->getStyle("E$r:F$r")->getNumberFormat()->setFormatCode("0.00"); $r++;
        }
        $this->descargar($sp,"ventas_por_mes_".date("Y-m-d").".xlsx");
    }

    // ── Reporte 3: Total por categoría (sección) ──────────────────
    public function excelCategorias(): void {
        $this->requirePermission("ventas"); $this->checkSpreadsheet();
        $db = Database::get();
        $data = $db->query(
            "SELECT s.nombre AS seccion,
                    COUNT(p.id) AS total_partes,
                    SUM(p.cantidad) AS total_unidades,
                    SUM(p.precio*p.cantidad) AS valor_inventario,
                    COALESCE(SUM(vp.cantidad),0) AS unidades_vendidas,
                    COALESCE(SUM(vp.precio_venta*vp.cantidad),0) AS monto_vendido
             FROM secciones s
             LEFT JOIN partes p ON p.seccion_id=s.id AND p.activo=1
             LEFT JOIN vendido_parte vp ON vp.parte_id=p.id
             WHERE s.activo=1
             GROUP BY s.id,s.nombre ORDER BY monto_vendido DESC"
        )->fetchAll();

        $sp = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sh = $sp->getActiveSheet()->setTitle("Total por Categoría");
        $this->tituloPagina($sh,"TOTAL POR CATEGORÍA — RASTRO DEL ISTMO",6);
        $cols=["A"=>"Categoría/Sección","B"=>"# Piezas","C"=>"Unidades en Stock","D"=>"Valor Inventario ($)","E"=>"Unidades Vendidas","F"=>"Monto Vendido ($)"];
        $r=3; foreach($cols as $col=>$h){ $sh->setCellValue($col.$r,$h); $sh->getColumnDimension($col)->setAutoSize(true); }
        $this->estiloHeader($sh,"A3:F3");
        $r=4; foreach($data as $row){
            $sh->setCellValue("A$r",$row["seccion"])->setCellValue("B$r",(int)$row["total_partes"])->setCellValue("C$r",(int)$row["total_unidades"])->setCellValue("D$r",(float)$row["valor_inventario"])->setCellValue("E$r",(int)$row["unidades_vendidas"])->setCellValue("F$r",(float)$row["monto_vendido"]);
            $sh->getStyle("D$r:F$r")->getNumberFormat()->setFormatCode("0.00"); $r++;
        }
        $this->descargar($sp,"categorias_".date("Y-m-d").".xlsx");
    }

    // ── Reporte 4: Piezas más vendidas ───────────────────────────
    public function excelMasVendidas(): void {
        $this->requirePermission("ventas"); $this->checkSpreadsheet();
        $db = Database::get();
        $data = $db->query(
            "SELECT p.nombre, a.marca, a.modelo, a.anio, s.nombre AS seccion,
                    SUM(v.cantidad) AS total_vendido,
                    SUM(v.precio_venta*v.cantidad) AS ingreso_total,
                    AVG(v.precio_venta) AS precio_prom,
                    COUNT(*) AS num_transacciones
             FROM vendido_parte v
             LEFT JOIN partes p ON p.id=v.parte_id
             LEFT JOIN autos a ON a.id=p.auto_id
             LEFT JOIN secciones s ON s.id=p.seccion_id
             GROUP BY v.parte_id
             ORDER BY total_vendido DESC
             LIMIT 50"
        )->fetchAll();

        $sp = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sh = $sp->getActiveSheet()->setTitle("Más Vendidas");
        $this->tituloPagina($sh,"PIEZAS MÁS VENDIDAS — RASTRO DEL ISTMO",8);
        $cols=["A"=>"Pos.","B"=>"Nombre de la Pieza","C"=>"Auto","D"=>"Sección","E"=>"Cant. Vendida","F"=>"Ingreso Total ($)","G"=>"Precio Prom. ($)","H"=>"N° Transacciones"];
        $r=3; foreach($cols as $col=>$h){ $sh->setCellValue($col.$r,$h); $sh->getColumnDimension($col)->setAutoSize(true); }
        $this->estiloHeader($sh,"A3:H3");
        $r=4; foreach($data as $i=>$row){
            $sh->setCellValue("A$r",$i+1)->setCellValue("B$r",$row["nombre"]??"")->setCellValue("C$r",trim($row["marca"]." ".$row["modelo"]." ".$row["anio"]))->setCellValue("D$r",$row["seccion"]??"")->setCellValue("E$r",(int)$row["total_vendido"])->setCellValue("F$r",(float)$row["ingreso_total"])->setCellValue("G$r",round((float)$row["precio_prom"],2))->setCellValue("H$r",(int)$row["num_transacciones"]);
            $sh->getStyle("F$r:G$r")->getNumberFormat()->setFormatCode("0.00"); $r++;
        }
        $this->descargar($sp,"mas_vendidas_".date("Y-m-d").".xlsx");
    }
}
