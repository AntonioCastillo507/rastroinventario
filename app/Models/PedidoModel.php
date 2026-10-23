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

    /** Estados a los que se puede pasar desde $estado. Entregado es final. */
    public static function transicionesPermitidas(string $estado): array {
        return match($estado){
            "pendiente"  => ["procesando","entregado","cancelado"],
            "procesando" => ["pendiente","entregado","cancelado"],
            "cancelado"  => ["pendiente","procesando"],
            default      => [],
        };
    }

    /**
     * Cambia el estado con su efecto en stock y ventas, todo en una transacción.
     * Regla: el stock queda apartado mientras el pedido no esté cancelado; cancelar lo
     * devuelve y reabrir lo descuenta otra vez. Las ventas se registran al entregar (una vez).
     * El estado se lee con bloqueo de fila, así dos clics seguidos no restauran el stock dos veces.
     * @return array{ok:bool,msg:string}
     */
    public function cambiarEstado(int $id, string $nuevo): array {
        $propia = !$this->db->inTransaction(); // si ya hay una transacción externa, se usa un savepoint
        $propia ? $this->db->beginTransaction() : $this->db->exec("SAVEPOINT cambiar_estado");
        try {
            $s = $this->db->prepare("SELECT estado, usuario_id FROM pedidos WHERE id=? FOR UPDATE");
            $s->execute([$id]);
            $p = $s->fetch();
            if (!$p) return $this->cerrarEstado($propia, false, "Pedido no encontrado.");

            $anterior = $p["estado"];
            if ($anterior === $nuevo)
                return $this->cerrarEstado($propia, false, "El pedido ya está en estado «{$nuevo}».");
            if (!in_array($nuevo, self::transicionesPermitidas($anterior), true))
                return $this->cerrarEstado($propia, false, $anterior === "entregado"
                    ? "Un pedido entregado ya no se puede cambiar."
                    : "No se puede pasar de «{$anterior}» a «{$nuevo}».");

            $items = $this->query("SELECT parte_id, parte_nombre, precio_unitario, cantidad FROM pedido_items WHERE pedido_id=?", [$id]);
            $pm = new ParteModel();
            $msg = "Estado actualizado.";

            if ($nuevo === "cancelado") {
                $n = 0;
                foreach ($items as $it)
                    if ($it["parte_id"] && $pm->incrementarStock((int)$it["parte_id"], (int)$it["cantidad"])) $n++;
                $msg = "Pedido cancelado. Stock devuelto de $n pieza(s). ✔";
            } elseif ($anterior === "cancelado") {
                foreach ($items as $it) {
                    if ($it["parte_id"] && !$pm->decrementarStock((int)$it["parte_id"], (int)$it["cantidad"]))
                        return $this->cerrarEstado($propia, false, "No hay stock suficiente para reabrir el pedido: ".$it["parte_nombre"].".");
                }
                $msg = "Pedido reabierto. El stock se descontó de nuevo. ✔";
            } elseif ($nuevo === "entregado") {
                $vm = new VentaModel();
                $n = 0;
                foreach ($items as $it) {
                    if (!$it["parte_id"]) continue;
                    if (!$vm->registrar([
                        "parte_id"     => $it["parte_id"],
                        "cantidad"     => $it["cantidad"],
                        "precio_venta" => $it["precio_unitario"],
                        "usuario_id"   => $p["usuario_id"],
                        "fecha"        => date("Y-m-d H:i:s"),
                    ])) throw new RuntimeException("No se pudo registrar la venta.");
                    $n++;
                }
                if ($n > 0) $msg = "Pedido entregado. $n venta(s) registrada(s) automáticamente. ✔";
            }

            $u = $this->db->prepare("UPDATE pedidos SET estado=? WHERE id=? AND estado=?");
            $u->execute([$nuevo, $id, $anterior]);
            if ($u->rowCount() !== 1) throw new RuntimeException("El estado cambió mientras se guardaba.");

            return $this->cerrarEstado($propia, true, $msg);
        } catch (Throwable $e) {
            error_log("[RASTRO] cambiarEstado: ".$e->getMessage());
            $this->cerrarEstado($propia, false, "");
            return ["ok" => false, "msg" => "No se pudo cambiar el estado; no se hizo ningún cambio."];
        }
    }

    /** Confirma (ok) o deshace (fallo) lo hecho por cambiarEstado y devuelve el resultado */
    private function cerrarEstado(bool $propia, bool $ok, string $msg): array {
        if ($ok)          $propia ? $this->db->commit()   : $this->db->exec("RELEASE SAVEPOINT cambiar_estado");
        elseif ($propia)  { if ($this->db->inTransaction()) $this->db->rollBack(); }
        else              $this->db->exec("ROLLBACK TO SAVEPOINT cambiar_estado");
        return ["ok" => $ok, "msg" => $msg];
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
