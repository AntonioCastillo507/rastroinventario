<?php
class ParteModel extends BaseModel {
    protected string $table = "partes";

    public function findAllWithDetails(string $q="", string $filtro="", string $orden="recientes", int $seccionId=0): array {
        $sql="SELECT p.*,a.marca,a.modelo,a.anio,s.nombre AS seccion,u.nombre AS creador
              FROM partes p LEFT JOIN autos a ON a.id=p.auto_id LEFT JOIN secciones s ON s.id=p.seccion_id LEFT JOIN usuarios u ON u.id=p.creado_por WHERE p.activo=1";
        $params=[];
        if ($q){$sql.=" AND (p.nombre LIKE ? OR a.marca LIKE ? OR a.modelo LIKE ?)";$params=["%$q%","%$q%","%$q%"];}
        if ($seccionId){$sql.=" AND p.seccion_id=?";$params[]=$seccionId;}
        $sql.=match($filtro){"stock_bajo"=>" AND p.cantidad<=5 AND p.cantidad>0","agotado"=>" AND p.cantidad=0","con_stock"=>" AND p.cantidad>0",default=>""};
        $sql.=match($orden){"precio_asc"=>" ORDER BY p.precio ASC","precio_desc"=>" ORDER BY p.precio DESC","stock_asc"=>" ORDER BY p.cantidad ASC","stock_desc"=>" ORDER BY p.cantidad DESC","nombre"=>" ORDER BY p.nombre ASC","mas_vendidas"=>" ORDER BY (SELECT COALESCE(SUM(vp.cantidad),0) FROM vendido_parte vp WHERE vp.parte_id=p.id) DESC",default=>" ORDER BY p.id DESC"};
        return $this->query($sql,$params);
    }
    public function findById(int $id): array|false {
        $s=$this->db->prepare("SELECT p.*,a.marca,a.modelo,a.anio,s.nombre AS seccion FROM partes p LEFT JOIN autos a ON a.id=p.auto_id LEFT JOIN secciones s ON s.id=p.seccion_id WHERE p.id=?");
        $s->execute([$id]); return $s->fetch();
    }
    public function findPublic(string $q="",int $secId=0,int $limit=0,string $marca="",float $pMin=0,float $pMax=0,string $orden="recientes"): array {
        $sql="SELECT p.*,a.marca,a.modelo,a.anio,s.nombre AS seccion FROM partes p LEFT JOIN autos a ON a.id=p.auto_id LEFT JOIN secciones s ON s.id=p.seccion_id WHERE p.activo=1 AND p.cantidad>0";
        $params=[];
        if ($q){$sql.=" AND (p.nombre LIKE ? OR a.marca LIKE ? OR a.modelo LIKE ? OR p.descripcion LIKE ?)";$params=["%$q%","%$q%","%$q%","%$q%"];}
        if ($secId){$sql.=" AND p.seccion_id=?";$params[]=$secId;}
        if ($marca){$sql.=" AND a.marca=?";$params[]=$marca;}
        if ($pMin>0){$sql.=" AND p.precio>=?";$params[]=$pMin;}
        if ($pMax>0){$sql.=" AND p.precio<=?";$params[]=$pMax;}
        $sql.=match($orden){"precio_asc"=>" ORDER BY p.precio ASC","precio_desc"=>" ORDER BY p.precio DESC","stock"=>" ORDER BY p.cantidad DESC","nombre"=>" ORDER BY p.nombre ASC",default=>" ORDER BY p.id DESC"};
        if ($limit>0) $sql.=" LIMIT $limit";
        return $this->query($sql,$params);
    }
    public function findFeatured(int $limit=8): array { return $this->findPublic("",0,$limit,"",0,0,"stock"); }
    public function search(string $q): array { return $this->findPublic($q); }
    public function decrementarStock(int $id,int $qty): bool { return $this->exec("UPDATE partes SET cantidad=cantidad-? WHERE id=? AND cantidad>=?",[$qty,$id,$qty]); }
    public function updateFirma(int $id,string $f): void { $this->exec("UPDATE partes SET firma_digital=? WHERE id=?",[$f,$id]); }
    public function countAll(): int { return (int)$this->db->query("SELECT COUNT(*) FROM partes WHERE activo=1")->fetchColumn(); }
    public function countStockBajo(): int { return (int)$this->db->query("SELECT COUNT(*) FROM partes WHERE activo=1 AND cantidad<=5")->fetchColumn(); }
    public function getMarcas(): array { return $this->db->query("SELECT DISTINCT a.marca FROM autos a INNER JOIN partes p ON p.auto_id=a.id WHERE p.activo=1 ORDER BY a.marca")->fetchAll(PDO::FETCH_COLUMN); }
    public function getPrecioRango(): array { $r=$this->db->query("SELECT MIN(precio) AS mn,MAX(precio) AS mx FROM partes WHERE activo=1 AND cantidad>0")->fetch(); return [$r["mn"]??0,$r["mx"]??9999]; }
}
