<?php
class RolModel extends BaseModel {
    protected string $table = "roles";
    public function getPermisos(int $rid): array {
        return $this->query("SELECT * FROM permisos WHERE rol_id=?", [$rid]);
    }
    public function savePermisos(int $rid, array $mods): void {
        $this->db->prepare("DELETE FROM permisos WHERE rol_id=?")->execute([$rid]);
        $s = $this->db->prepare(
            "INSERT INTO permisos (rol_id,modulo,puede_ver,puede_crear,puede_editar,puede_borrar)
             VALUES (?,?,?,?,?,?)"
        );
        foreach ($mods as $mod => $p)
            $s->execute([$rid, $mod,
                isset($p["ver"])    ? 1 : 0,
                isset($p["crear"])  ? 1 : 0,
                isset($p["editar"]) ? 1 : 0,
                isset($p["borrar"]) ? 1 : 0,
            ]);
    }
}
