<?php
class SeccionModel extends BaseModel {
    protected string $table = "secciones";
    public function findActive(): array {
        return $this->query("SELECT * FROM secciones WHERE activo=1 ORDER BY nombre");
    }
}
