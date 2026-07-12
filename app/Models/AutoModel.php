<?php
class AutoModel extends BaseModel {
    protected string $table = "autos";
    public function findActive(): array {
        return $this->query("SELECT * FROM autos WHERE activo=1 ORDER BY marca,modelo");
    }
}
