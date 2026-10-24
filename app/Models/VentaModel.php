<?php
class VentaModel extends BaseModel {
    protected string $table = "vendido_parte";
    /** Con $pag trae esa página; sin $pag, todo. v.id DESC desempata las ventas de un mismo segundo */
    public function findAllWithDetails(?Paginador $pag=null): array {
        $sql = "SELECT v.*,p.nombre AS parte_nombre,a.marca,a.modelo,u.nombre AS vendedor FROM vendido_parte v LEFT JOIN partes p ON p.id=v.parte_id LEFT JOIN autos a ON a.id=p.auto_id LEFT JOIN usuarios u ON u.id=v.usuario_id ORDER BY v.fecha DESC, v.id DESC";
        return $this->query($pag ? $sql.$pag->sql() : $sql);
    }
    public function registrar(array $data): int|false {
        $data["fecha"]                = date("Y-m-d H:i:s");
        $data["precio_venta"] = number_format((float)$data["precio_venta"], 2, '.', '');
        $data["firma_digital"]        = DigitalSignature::signVenta($data);
        return $this->create($data);
    }
    public function totalHoy(): float {
        return (float)$this->db->query("SELECT COALESCE(SUM(precio_venta*cantidad),0) FROM vendido_parte WHERE DATE(fecha)=CURDATE()")->fetchColumn();
    }
    public function countHoy(): int {
        return (int)$this->db->query("SELECT COUNT(*) FROM vendido_parte WHERE DATE(fecha)=CURDATE()")->fetchColumn();
    }
}
