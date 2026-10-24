<?php
/**
 * BaseModel — Modelo base con PDO. Implementa CrudInterface.
 */
abstract class BaseModel implements CrudInterface {
    protected PDO    $db;
    protected string $table = "";
    protected string $pk    = "id";

    public function __construct() { $this->db = Database::get(); }

    public function findAll(): array {
        return $this->db->query("SELECT * FROM `{$this->table}` ORDER BY {$this->pk} DESC")->fetchAll();
    }
    /** Total de filas de la tabla (para paginar sin cargar nada) */
    public function contar(): int {
        return (int)$this->db->query("SELECT COUNT(*) FROM `{$this->table}`")->fetchColumn();
    }
    public function findById(int $id): array|false {
        $s = $this->db->prepare("SELECT * FROM `{$this->table}` WHERE {$this->pk}=?");
        $s->execute([$id]); return $s->fetch();
    }
    public function create(array $data): int|false {
        $cols = implode(",", array_keys($data));
        $ph   = implode(",", array_fill(0, count($data), "?"));
        $s    = $this->db->prepare("INSERT INTO `{$this->table}` ($cols) VALUES ($ph)");
        if ($s->execute(array_values($data))) return (int)$this->db->lastInsertId();
        return false;
    }
    public function update(int $id, array $data): bool {
        $set = implode(",", array_map(fn($k) => "$k=?", array_keys($data)));
        $s   = $this->db->prepare("UPDATE `{$this->table}` SET $set WHERE {$this->pk}=?");
        return $s->execute([...array_values($data), $id]);
    }
    public function softDelete(int $id): bool {
        return $this->db->prepare("UPDATE `{$this->table}` SET activo=0 WHERE {$this->pk}=?")->execute([$id]);
    }
    public function hardDelete(int $id): bool {
        return $this->db->prepare("DELETE FROM `{$this->table}` WHERE {$this->pk}=?")->execute([$id]);
    }
    protected function query(string $sql, array $p = []): array {
        $s = $this->db->prepare($sql); $s->execute($p); return $s->fetchAll();
    }
    protected function exec(string $sql, array $p = []): bool {
        return $this->db->prepare($sql)->execute($p);
    }
}
