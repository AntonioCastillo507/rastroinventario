<?php
/**
 * GeoPanamaModel — Provincias, distritos y corregimientos de Panamá (datos de database/seed_panama.sql).
 * Las listas para el formulario solo traen las unidades visibles; las implícitas (el distrito ficticio de Guna Yala y el
 * área única de Naso Tjër Di) no se muestran: resolver() las completa solo.
 */
class GeoPanamaModel extends BaseModel {
    protected string $table = "provincias";
    protected string $pk    = "codigo";

    /** @return list<array{codigo:string,nombre:string,tipo:string}> */
    public function provincias(): array {
        return $this->query("SELECT codigo, nombre, tipo FROM provincias ORDER BY nombre");
    }

    /** Distritos visibles de una provincia o comarca (vacío si no tiene, como Guna Yala) @return list<array{codigo:string,nombre:string}> */
    public function distritos(string $provincia): array {
        if (!preg_match('/^\d{2}\z/', $provincia)) return [];
        return $this->query("SELECT codigo, nombre FROM distritos WHERE provincia_codigo = ? AND implicito = 0 ORDER BY nombre", [$provincia]);
    }

    /** Corregimientos visibles de un distrito (vacío si no tiene, como Naso Tjër Di) @return list<array{codigo:string,nombre:string}> */
    public function corregimientos(string $distrito): array {
        if (!preg_match('/^\d{4}\z/', $distrito)) return [];
        return $this->query("SELECT codigo, nombre FROM corregimientos WHERE distrito_codigo = ? AND implicito = 0 ORDER BY nombre", [$distrito]);
    }

    /** Código del distrito implícito de una provincia que no tiene distritos visibles (Guna Yala: 1001), o null. Sirve para listar sus corregimientos */
    public function distritoImplicito(string $provincia): ?string {
        return preg_match('/^\d{2}\z/', $provincia) ? $this->implicitoUnico("distritos", "provincia_codigo", $provincia) : null;
    }

    /** El hijo implícito único de un padre, o null si el padre tiene hijos visibles o ninguno */
    private function implicitoUnico(string $tabla, string $columnaPadre, string $padre): ?string {
        $filas = $this->query("SELECT codigo, implicito FROM `$tabla` WHERE `$columnaPadre` = ?", [$padre]);
        return count($filas) === 1 && (int)$filas[0]["implicito"] === 1 ? (string)$filas[0]["codigo"] : null;
    }

    /**
     * Comprueba que provincia, distrito y corregimiento existan y encajen (el distrito es de esa provincia y el corregimiento de
     * ese distrito). Un distrito o corregimiento vacío se completa solo cuando la unidad padre no tiene más que el implícito.
     * Los nombres salen siempre de la base. Un nombre es null cuando la unidad es implícita: no se le muestra ni se copia al pedido.
     * @return array{provincia:string,distrito:string,corregimiento:string,provincia_nombre:string,distrito_nombre:?string,corregimiento_nombre:?string}|array{error:string}
     *         con error = provincia | distrito | corregimiento | combinacion
     */
    public function resolver(string $provincia, string $distrito, string $corregimiento): array {
        $provincia = trim($provincia); $distrito = trim($distrito); $corregimiento = trim($corregimiento);
        if ($provincia === "") return ["error" => "provincia"];
        if (!preg_match('/^\d{2}\z/', $provincia)) return ["error" => "combinacion"];

        if ($distrito === "") {
            $distrito = $this->implicitoUnico("distritos", "provincia_codigo", $provincia) ?? "";
            if ($distrito === "") return ["error" => "distrito"];
        } elseif (!preg_match('/^\d{4}\z/', $distrito)) return ["error" => "combinacion"];

        if ($corregimiento === "") {
            $corregimiento = $this->implicitoUnico("corregimientos", "distrito_codigo", $distrito) ?? "";
            if ($corregimiento === "") return ["error" => "corregimiento"];
        } elseif (!preg_match('/^\d{6}\z/', $corregimiento)) return ["error" => "combinacion"];

        $f = $this->query(
            "SELECT p.nombre AS pn, d.nombre AS dn, d.implicito AS di, c.nombre AS cn, c.implicito AS ci
             FROM corregimientos c
             JOIN distritos d ON d.codigo = c.distrito_codigo
             JOIN provincias p ON p.codigo = d.provincia_codigo
             WHERE c.codigo = ? AND d.codigo = ? AND p.codigo = ?",
            [$corregimiento, $distrito, $provincia]
        )[0] ?? null;
        if (!$f) return ["error" => "combinacion"];

        return [
            "provincia" => $provincia, "distrito" => $distrito, "corregimiento" => $corregimiento,
            "provincia_nombre"     => $f["pn"],
            "distrito_nombre"      => (int)$f["di"] === 1 ? null : $f["dn"],
            "corregimiento_nombre" => (int)$f["ci"] === 1 ? null : $f["cn"],
        ];
    }
}
