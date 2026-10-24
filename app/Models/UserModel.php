<?php
class UserModel extends BaseModel {
    public function updatePassword(int $id, string $hash): void {
        $this->db->prepare("UPDATE usuarios SET password=? WHERE id=?")->execute([$hash, $id]);
    }
    protected string $table = "usuarios";
    public function findByEmail(string $email): array|false {
        $s=$this->db->prepare("SELECT * FROM usuarios WHERE email=?"); $s->execute([$email]); return $s->fetch();
    }
    public function incrementIntentos(int $id): void { $this->db->prepare("UPDATE usuarios SET intentos_fallidos=intentos_fallidos+1 WHERE id=?")->execute([$id]); }
    public function bloquear(int $id): void { $this->db->prepare("UPDATE usuarios SET bloqueado=1 WHERE id=?")->execute([$id]); }
    public function resetIntentos(int $id): void { $this->db->prepare("UPDATE usuarios SET intentos_fallidos=0,bloqueado=0 WHERE id=?")->execute([$id]); }
    public function toggleActivo(int $id): void { $this->db->prepare("UPDATE usuarios SET activo=IF(activo=1,0,1) WHERE id=?")->execute([$id]); }
    /** Con $pag trae esa página; sin $pag, todo (u.id es único: el orden ya es estable) */
    public function findAllWithRoles(?Paginador $pag=null): array {
        $sql = "SELECT u.*,GROUP_CONCAT(r.nombre SEPARATOR \", \") AS roles FROM usuarios u LEFT JOIN usuario_roles ur ON ur.usuario_id=u.id LEFT JOIN roles r ON r.id=ur.rol_id GROUP BY u.id ORDER BY u.id DESC";
        return $this->query($pag ? $sql.$pag->sql() : $sql);
    }
    public function getRoles(int $uid): array { return $this->query("SELECT r.* FROM roles r INNER JOIN usuario_roles ur ON ur.rol_id=r.id WHERE ur.usuario_id=?",[$uid]); }
    public function setRoles(int $uid, array $rids): void {
        $this->db->prepare("DELETE FROM usuario_roles WHERE usuario_id=?")->execute([$uid]);
        $s=$this->db->prepare("INSERT INTO usuario_roles (usuario_id,rol_id) VALUES (?,?)");
        foreach ($rids as $r) $s->execute([$uid,(int)$r]);
    }
    public function hasPermission(int $uid,string $modulo,string $accion="puede_ver"): bool {
        $s=$this->db->prepare("SELECT MAX(p.$accion) FROM permisos p INNER JOIN usuario_roles ur ON ur.rol_id=p.rol_id WHERE ur.usuario_id=? AND p.modulo=?");
        $s->execute([$uid,$modulo]); return (bool)$s->fetchColumn();
    }
    public function updateProfile(int $id, array $data): void {
        $allowed=["nombre","telefono","direccion"];
        $upd=array_intersect_key($data,array_flip($allowed));
        if (!empty($upd)) $this->update($id,$upd);
    }
    public function assignClienteRole(int $uid): void {
        $rid=$this->db->query("SELECT id FROM roles WHERE nombre='Cliente'")->fetchColumn();
        if ($rid) $this->db->exec("INSERT IGNORE INTO usuario_roles VALUES ($uid,$rid)");
    }
}
