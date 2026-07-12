<?php
class LogModel extends BaseModel {
    protected string $table = "login_logs";
    public function registrar(array $d): void {
        $this->db->prepare(
            "INSERT INTO login_logs (usuario_id,email_intento,ip,exitoso) VALUES (?,?,?,?)"
        )->execute([$d["usuario_id"] ?? null, $d["email"], $d["ip"], $d["exitoso"] ? 1 : 0]);
    }
    public function recientes(int $limit = 50): array {
        return $this->query(
            "SELECT ll.*, u.nombre FROM login_logs ll
             LEFT JOIN usuarios u ON u.id=ll.usuario_id
             ORDER BY ll.fecha DESC LIMIT ?", [$limit]
        );
    }
}
