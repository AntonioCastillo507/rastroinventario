<?php
class PedidoModel extends BaseModel {
    protected string $table = "pedidos";

    public function findAllWithItems(): array {
        return $this->query(
            "SELECT p.*, COUNT(pi.id) as total_items
             FROM pedidos p LEFT JOIN pedido_items pi ON pi.pedido_id=p.id
             GROUP BY p.id ORDER BY p.creado_en DESC"
        );
    }

    public function findWithItems(int $id): array|false {
        $p = $this->findById($id);
        if (!$p) return false;
        $p["items"] = $this->query(
            "SELECT pi.*, pa.thumbnail FROM pedido_items pi
             LEFT JOIN partes pa ON pa.id=pi.parte_id WHERE pi.pedido_id=?",[$id]
        );
        return $p;
    }

    public function registrar(array $data, array $items): int|false {
        $subtotal = array_sum(array_map(fn($i) => $i["precio_unitario"]*$i["cantidad"], $items));
        $data["subtotal"] = $subtotal;
        $data["total"]    = $subtotal;
        $data["firma_digital"] = hash_hmac("sha256",
            $data["cliente_email"]."|".$subtotal."|".date("Y-m-d H:i:s"), HMAC_KEY);
        $id = $this->create($data);
        if (!$id) return false;
        $s = $this->db->prepare(
            "INSERT INTO pedido_items (pedido_id,parte_id,parte_nombre,precio_unitario,cantidad) VALUES (?,?,?,?,?)"
        );
        foreach ($items as $i)
            $s->execute([$id,$i["parte_id"],$i["parte_nombre"],$i["precio_unitario"],$i["cantidad"]]);
        return $id;
    }

    public function updateEstado(int $id, string $estado): void {
        $this->exec("UPDATE pedidos SET estado=? WHERE id=?",[$estado,$id]);
    }

    public function findByUsuario(int $uid): array {
        return $this->query(
            "SELECT p.*, COUNT(pi.id) as total_items FROM pedidos p
             LEFT JOIN pedido_items pi ON pi.pedido_id=p.id
             WHERE p.usuario_id=? GROUP BY p.id ORDER BY p.creado_en DESC",[$uid]
        );
    }

    public function countTotal(): int {
        return (int)$this->db->query("SELECT COUNT(*) FROM pedidos")->fetchColumn();
    }
    public function countPendientes(): int {
        return (int)$this->db->query("SELECT COUNT(*) FROM pedidos WHERE estado=\"pendiente\"")->fetchColumn();
    }
    public function totalVendido(): float {
        return (float)$this->db->query("SELECT COALESCE(SUM(total),0) FROM pedidos WHERE estado!=\"cancelado\"")->fetchColumn();
    }
}
