<?php
class ParteModel extends BaseModel {
    protected string $table = "partes";

    private const FROM_ADMIN = "FROM partes p LEFT JOIN autos a ON a.id=p.auto_id LEFT JOIN secciones s ON s.id=p.seccion_id LEFT JOIN usuarios u ON u.id=p.creado_por";
    private const FROM_PUBLIC = "FROM partes p LEFT JOIN autos a ON a.id=p.auto_id LEFT JOIN secciones s ON s.id=p.seccion_id";

    /** WHERE y parámetros del listado del panel: el mismo para contar y para traer la página */
    private function whereAdmin(string $q, string $filtro, int $seccionId): array {
        $w = " WHERE p.activo=1"; $params = [];
        if ($q) { $w .= " AND (p.nombre LIKE ? OR a.marca LIKE ? OR a.modelo LIKE ?)"; $params = ["%$q%","%$q%","%$q%"]; }
        if ($seccionId) { $w .= " AND p.seccion_id=?"; $params[] = $seccionId; }
        $w .= match($filtro){"stock_bajo"=>" AND p.cantidad<=5 AND p.cantidad>0","agotado"=>" AND p.cantidad=0","con_stock"=>" AND p.cantidad>0","disponible"=>" AND p.cantidad>5",default=>""};
        return [$w, $params];
    }
    /** Cada orden termina en p.id DESC: con empates (mismo precio, mismo stock) las páginas no repiten ni saltan filas */
    private function ordenAdmin(string $orden): string {
        return match($orden){"precio_asc"=>" ORDER BY p.precio ASC, p.id DESC","precio_desc"=>" ORDER BY p.precio DESC, p.id DESC","stock_asc"=>" ORDER BY p.cantidad ASC, p.id DESC","stock_desc"=>" ORDER BY p.cantidad DESC, p.id DESC","nombre"=>" ORDER BY p.nombre ASC, p.id DESC","mas_vendidas"=>" ORDER BY (SELECT COALESCE(SUM(vp.cantidad),0) FROM vendido_parte vp WHERE vp.parte_id=p.id) DESC, p.id DESC",default=>" ORDER BY p.id DESC"};
    }
    public function contarAdmin(string $q="", string $filtro="", int $seccionId=0): int {
        [$w, $params] = $this->whereAdmin($q, $filtro, $seccionId);
        return (int)$this->query("SELECT COUNT(*) AS n ".self::FROM_ADMIN.$w, $params)[0]["n"];
    }
    public function findAllWithDetails(string $q="", string $filtro="", string $orden="recientes", int $seccionId=0, ?Paginador $pag=null): array {
        [$w, $params] = $this->whereAdmin($q, $filtro, $seccionId);
        $sql = "SELECT p.*,a.marca,a.modelo,a.anio,s.nombre AS seccion,u.nombre AS creador ".self::FROM_ADMIN.$w.$this->ordenAdmin($orden);
        if ($pag) $sql .= $pag->sql();
        return $this->query($sql, $params);
    }
    public function findById(int $id): array|false {
        $s=$this->db->prepare("SELECT p.*,a.marca,a.modelo,a.anio,s.nombre AS seccion FROM partes p LEFT JOIN autos a ON a.id=p.auto_id LEFT JOIN secciones s ON s.id=p.seccion_id WHERE p.id=?");
        $s->execute([$id]); return $s->fetch();
    }
    /** Varias piezas con la misma forma que findById, en UNA consulta; el arreglo va indexado por id (las que no existen no aparecen) */
    public function findByIds(array $ids): array {
        $ids = array_values(array_unique(array_filter(array_map("intval", $ids), fn($i)=>$i>0)));
        if (!$ids) return [];
        $in = implode(",", array_fill(0, count($ids), "?"));
        $s=$this->db->prepare("SELECT p.*,a.marca,a.modelo,a.anio,s.nombre AS seccion FROM partes p LEFT JOIN autos a ON a.id=p.auto_id LEFT JOIN secciones s ON s.id=p.seccion_id WHERE p.id IN ($in)");
        $s->execute($ids);
        $filas=[]; foreach ($s->fetchAll() as $f) $filas[(int)$f["id"]]=$f;
        return $filas;
    }

    /** WHERE y parámetros del catálogo público: el mismo para contar y para traer la página */
    private function wherePublic(string $q, int $secId, string $marca, float $pMin, float $pMax): array {
        $w = " WHERE p.activo=1 AND p.cantidad>0"; $params = [];
        if ($q) { $w .= " AND (p.nombre LIKE ? OR a.marca LIKE ? OR a.modelo LIKE ? OR p.descripcion LIKE ?)"; $params = ["%$q%","%$q%","%$q%","%$q%"]; }
        if ($secId) { $w .= " AND p.seccion_id=?"; $params[] = $secId; }
        if ($marca) { $w .= " AND a.marca=?"; $params[] = $marca; }
        if ($pMin>0) { $w .= " AND p.precio>=?"; $params[] = $pMin; }
        if ($pMax>0) { $w .= " AND p.precio<=?"; $params[] = $pMax; }
        return [$w, $params];
    }
    private function ordenPublic(string $orden): string {
        return match($orden){"precio_asc"=>" ORDER BY p.precio ASC, p.id DESC","precio_desc"=>" ORDER BY p.precio DESC, p.id DESC","stock"=>" ORDER BY p.cantidad DESC, p.id DESC","nombre"=>" ORDER BY p.nombre ASC, p.id DESC",default=>" ORDER BY p.id DESC"};
    }
    public function contarPublic(string $q="",int $secId=0,string $marca="",float $pMin=0,float $pMax=0): int {
        [$w, $params] = $this->wherePublic($q, $secId, $marca, $pMin, $pMax);
        return (int)$this->query("SELECT COUNT(*) AS n ".self::FROM_PUBLIC.$w, $params)[0]["n"];
    }
    /** Con $pag trae esa página; sin $pag, todo (o los primeros $limit, como la portada) */
    public function findPublic(string $q="",int $secId=0,int $limit=0,string $marca="",float $pMin=0,float $pMax=0,string $orden="recientes",?Paginador $pag=null): array {
        [$w, $params] = $this->wherePublic($q, $secId, $marca, $pMin, $pMax);
        $sql = "SELECT p.*,a.marca,a.modelo,a.anio,s.nombre AS seccion ".self::FROM_PUBLIC.$w.$this->ordenPublic($orden);
        if ($pag) $sql .= $pag->sql();
        elseif ($limit>0) $sql .= " LIMIT ".(int)$limit;
        return $this->query($sql, $params);
    }
    /**
     * Piezas para el carrusel del hero de la portada: activas, con stock y con foto guardada, al azar y como mucho $limite (tope 4), sin las de las secciones de $excluirSecciones (nombres, de portada.hero_excluir_secciones en marca.php).
     * Que el archivo exista en disco lo revisa la vista.
     */
    public function paraHero(int $limite = 4, array $excluirSecciones = []): array {
        // Con 2 o más destacadas a la venta (activas, con stock y foto) el hero muestra solo esas, en orden estable; sin azar
        $destacadas = $this->destacadasParaHero();
        if (count($destacadas) >= 2) return $destacadas;
        $limite = max(1, min($limite, 4));
        $excluir = array_values(array_filter(array_map("strval", $excluirSecciones), fn($n) => $n !== ""));
        $w = "";
        if ($excluir) $w = " AND (s.nombre IS NULL OR s.nombre NOT IN (" . implode(",", array_fill(0, count($excluir), "?")) . "))";
        return $this->query(
            "SELECT p.*,a.marca,a.modelo,a.anio ".self::FROM_PUBLIC
            ." WHERE p.activo=1 AND p.cantidad>0 AND p.imagen_grande IS NOT NULL AND p.imagen_grande<>''".$w." ORDER BY RAND() LIMIT ".$limite,
            $excluir
        );
    }

    /** Máximo de piezas destacadas a la vez (las que el administrador marca para la portada) */
    public const MAX_DESTACADAS = 6;

    /** Destacadas activas, con stock y foto, por id: el orden no cambia entre visitas */
    public function destacadasParaHero(): array {
        return $this->query(
            "SELECT p.*,a.marca,a.modelo,a.anio ".self::FROM_PUBLIC
            ." WHERE p.activo=1 AND p.destacado=1 AND p.cantidad>0 AND p.imagen_grande IS NOT NULL AND p.imagen_grande<>'' ORDER BY p.id DESC LIMIT ".self::MAX_DESTACADAS
        );
    }
    /** Cuántas piezas activas están destacadas; $exceptoId deja fuera la que se está editando */
    public function contarDestacadas(int $exceptoId = 0): int {
        return (int)$this->query("SELECT COUNT(*) AS n FROM partes WHERE activo=1 AND destacado=1 AND id<>?", [$exceptoId])[0]["n"];
    }

    /**
     * Piezas relacionadas con la de $id: de la misma sección o del mismo auto, activas y con stock, sin la propia.
     * Primero las que coinciden en las dos cosas, y a igualdad las más recientes (desempate por id). Máximo $limite.
     * Sin sección ni auto no hay relación que buscar: lista vacía.
     */
    public function findRelacionadas(int $id, ?int $seccionId, ?int $autoId, int $limite = 4): array {
        $cond = []; $params = []; $puntos = [];
        if ($autoId)    { $cond[] = "p.auto_id=?";    $puntos[] = "(p.auto_id=?)*2";    $params[] = $autoId; }
        if ($seccionId) { $cond[] = "p.seccion_id=?"; $puntos[] = "(p.seccion_id=?)";   $params[] = $seccionId; }
        if (!$cond) return [];
        $sql = "SELECT p.*,a.marca,a.modelo,a.anio,s.nombre AS seccion,(".implode("+", $puntos).") AS relacion ".self::FROM_PUBLIC
             ." WHERE p.activo=1 AND p.cantidad>0 AND p.id<>? AND (".implode(" OR ", $cond).")"
             ." ORDER BY relacion DESC, p.id DESC LIMIT ".max(1, min($limite, 12));
        $s = $this->db->prepare($sql);
        // los marcadores van en el orden en que salen en la consulta: puntos (SELECT), id propio y condiciones (WHERE)
        $s->execute(array_merge($params, [$id], $params));
        return $s->fetchAll();
    }
    public function findFeatured(int $limit=8): array { return $this->findPublic("",0,$limit,"",0,0,"stock"); }
    public function search(string $q): array { return $this->findPublic($q); }
    /** Lee la pieza con bloqueo de fila (dentro de una transacción): activo y stock no cambian hasta el commit */
    public function bloquear(int $id): array|false {
        $s=$this->db->prepare("SELECT id,nombre,activo,cantidad FROM partes WHERE id=? FOR UPDATE");
        $s->execute([$id]); return $s->fetch();
    }
    /** Descuenta stock de forma atómica; false si qty < 1 o no alcanzó el stock (ninguna fila afectada) */
    public function decrementarStock(int $id,int $qty): bool {
        if ($qty<1) return false;
        $s=$this->db->prepare("UPDATE partes SET cantidad=cantidad-? WHERE id=? AND cantidad>=?");
        $s->execute([$qty,$id,$qty]);
        return $s->rowCount()===1;
    }
    /** Devuelve stock (cancelación de pedido); false si qty < 1 o la pieza no existe */
    public function incrementarStock(int $id,int $qty): bool {
        if ($qty<1) return false;
        $s=$this->db->prepare("UPDATE partes SET cantidad=cantidad+? WHERE id=?");
        $s->execute([$qty,$id]);
        return $s->rowCount()===1;
    }
    public function updateFirma(int $id,string $f): void { $this->exec("UPDATE partes SET firma_digital=? WHERE id=?",[$f,$id]); }
    public function countAll(): int { return (int)$this->db->query("SELECT COUNT(*) FROM partes WHERE activo=1")->fetchColumn(); }
    public function countStockBajo(): int { return (int)$this->db->query("SELECT COUNT(*) FROM partes WHERE activo=1 AND cantidad<=5")->fetchColumn(); }
    public function getMarcas(): array { return $this->db->query("SELECT DISTINCT a.marca FROM autos a INNER JOIN partes p ON p.auto_id=a.id WHERE p.activo=1 ORDER BY a.marca")->fetchAll(PDO::FETCH_COLUMN); }
    public function getPrecioRango(): array { $r=$this->db->query("SELECT MIN(precio) AS mn,MAX(precio) AS mx FROM partes WHERE activo=1 AND cantidad>0")->fetch(); return [$r["mn"]??0,$r["mx"]??9999]; }
}
