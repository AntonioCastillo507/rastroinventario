<?php
class ComentarioModel extends BaseModel {
    protected string $table = "comentarios";
    public function findByParte(int $pid, bool $soloPublicados = false): array {
        // Libertad de expresión: todos los comentarios visibles de inmediato
        return $this->query("SELECT * FROM comentarios WHERE parte_id=? ORDER BY creado_en DESC",[$pid]);
    }
    /** Con $pag trae esa página; sin $pag, todo. c.id DESC desempata los comentarios de un mismo segundo */
    public function findAllWithParte(?Paginador $pag=null): array {
        $sql = "SELECT c.*,p.nombre AS parte_nombre FROM comentarios c LEFT JOIN partes p ON p.id=c.parte_id ORDER BY c.creado_en DESC, c.id DESC";
        return $this->query($pag ? $sql.$pag->sql() : $sql);
    }
    public function togglePublicado(int $id): void { $this->exec("UPDATE comentarios SET publicado=IF(publicado=1,0,1) WHERE id=?",[$id]); }
    public function countPendientes(): int { return 0; } // ya no hay pendientes
    public function promedioRating(int $pid): float {
        $s=$this->db->prepare("SELECT AVG(rating) FROM comentarios WHERE parte_id=? AND rating IS NOT NULL");
        $s->execute([$pid]); return round((float)$s->fetchColumn(),1);
    }
    public function countRating(int $pid): int {
        $s=$this->db->prepare("SELECT COUNT(*) FROM comentarios WHERE parte_id=? AND rating IS NOT NULL");
        $s->execute([$pid]); return (int)$s->fetchColumn();
    }
}
