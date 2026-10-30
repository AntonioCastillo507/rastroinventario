<?php
class ComentarioModel extends BaseModel {
    protected string $table = "comentarios";
    public function findByParte(int $pid, bool $soloPublicados = false): array {
        // Libertad de expresión: todos los comentarios visibles de inmediato
        return $this->query("SELECT * FROM comentarios WHERE parte_id=? ORDER BY creado_en DESC",[$pid]);
    }
    /** Orden de la lista del panel: lista blanca de columnas y sentido; todo desempata por id */
    private const ORDENES = [
        "fecha_desc"  => "c.creado_en DESC, c.id DESC",
        "fecha_asc"   => "c.creado_en ASC, c.id ASC",
        "rating_desc" => "c.rating IS NULL, c.rating DESC, c.id DESC",
        "rating_asc"  => "c.rating IS NULL, c.rating ASC, c.id DESC",
        "estado_desc" => "c.publicado DESC, c.id DESC",
        "estado_asc"  => "c.publicado ASC, c.id DESC",
    ];
    public const ORDEN_POR_DEFECTO = "fecha_desc";
    /** Un orden pedido que no está en la lista blanca vale el de por defecto (más recientes) */
    public static function ordenValido(string $orden): string { return isset(self::ORDENES[$orden]) ? $orden : self::ORDEN_POR_DEFECTO; }
    /** Con $pag trae esa página; sin $pag, todo. Un solo JOIN trae la pieza con su auto y si sigue activa */
    public function findAllWithParte(?Paginador $pag=null, string $orden=self::ORDEN_POR_DEFECTO): array {
        $sql = "SELECT c.*,p.nombre AS parte_nombre,p.activo AS parte_activo,a.marca,a.modelo,a.anio FROM comentarios c LEFT JOIN partes p ON p.id=c.parte_id LEFT JOIN autos a ON a.id=p.auto_id ORDER BY ".self::ORDENES[self::ordenValido($orden)];
        return $this->query($pag ? $sql.$pag->sql() : $sql);
    }
    /** Reseñas de la portada: solo visibles y de piezas que existen y siguen activas (el enlace llevaría a una pieza que no se ve), la más reciente primero */
    public function paraPortada(int $limite = 12): array {
        return $this->query(
            "SELECT c.id, c.nombre_visitante, c.comentario, c.rating, c.parte_id, p.nombre AS parte
             FROM comentarios c JOIN partes p ON p.id = c.parte_id
             WHERE c.publicado = 1 AND p.activo = 1 AND TRIM(c.comentario) <> ''
             ORDER BY c.creado_en DESC, c.id DESC LIMIT " . max(1, $limite));
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
