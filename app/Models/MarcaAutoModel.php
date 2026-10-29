<?php
/**
 * MarcaAutoModel — marcas de autos (Honda, Toyota...), con su logo. No confundir con la clase Marca,
 * que es la identidad de la tienda. autos.marca (texto) se guarda siempre con el nombre de esta tabla,
 * así las consultas que leen a.marca siguen igual y no puede haber dos escrituras de la misma marca.
 */
class MarcaAutoModel extends BaseModel {
    protected string $table = "marcas";
    public const NOMBRE_MAX = 100;

    /** Nombre sin espacios de más (extremos y dobles); "" si no queda nada */
    public static function limpiarNombre(string $n): string {
        return trim((string)preg_replace('/\s+/u', " ", $n));
    }

    /** Texto para URLs: minúsculas sin acentos, letras y números separados por guiones */
    public static function slugDe(string $n): string {
        $n = strtr(mb_strtolower(self::limpiarNombre($n), "UTF-8"), [
            "á" => "a", "à" => "a", "ä" => "a", "â" => "a", "é" => "e", "è" => "e", "ë" => "e", "ê" => "e",
            "í" => "i", "ì" => "i", "ï" => "i", "î" => "i", "ó" => "o", "ò" => "o", "ö" => "o", "ô" => "o",
            "ú" => "u", "ù" => "u", "ü" => "u", "û" => "u", "ñ" => "n", "ç" => "c",
        ]);
        $s = trim((string)preg_replace('/[^a-z0-9]+/', "-", $n), "-");
        return $s === "" ? "marca" : $s;
    }

    /** Todas las marcas con sus autos y sus piezas activas, para la lista del panel */
    public function listar(): array {
        return $this->query(
            "SELECT m.*, COUNT(DISTINCT a.id) AS autos, COUNT(DISTINCT p.id) AS piezas
             FROM marcas m LEFT JOIN autos a ON a.marca_id=m.id LEFT JOIN partes p ON p.auto_id=a.id AND p.activo=1
             GROUP BY m.id ORDER BY m.nombre"
        );
    }

    /** Marcas activas para el desplegable del formulario de autos */
    public function activas(): array {
        return $this->query("SELECT id,nombre FROM marcas WHERE activo=1 ORDER BY nombre");
    }

    public function porNombre(string $nombre): array|false {
        $s = $this->db->prepare("SELECT * FROM marcas WHERE nombre=?");
        $s->execute([self::limpiarNombre($nombre)]);
        return $s->fetch();
    }

    /** Valida el nombre y comprueba que ninguna otra marca lo use (sin importar mayúsculas ni espacios) */
    private function validar(string $nombre, int $propia = 0): string {
        $n = self::limpiarNombre($nombre);
        if ($n === "") throw new InvalidArgumentException("Escribe el nombre de la marca.");
        if (mb_strlen($n, "UTF-8") > self::NOMBRE_MAX) throw new InvalidArgumentException("El nombre no puede pasar de " . self::NOMBRE_MAX . " caracteres.");
        $s = $this->db->prepare("SELECT id FROM marcas WHERE nombre=? AND id<>?");
        $s->execute([$n, $propia]);
        if ($s->fetchColumn()) throw new InvalidArgumentException("Ya existe una marca llamada «" . $n . "».");
        return $n;
    }

    private function slugLibre(string $nombre, int $propia = 0): string {
        $base = self::slugDe($nombre);
        $slug = $base;
        $s = $this->db->prepare("SELECT 1 FROM marcas WHERE slug=? AND id<>?");
        for ($i = 2; ; $i++) {
            $s->execute([$slug, $propia]);
            if (!$s->fetchColumn()) return $slug;
            $slug = $base . "-" . $i;
        }
    }

    /** Crea la marca y devuelve su id; InvalidArgumentException con el motivo si el nombre no sirve */
    public function crear(string $nombre): int {
        $n = $this->validar($nombre);
        try {
            $this->db->prepare("INSERT INTO marcas (nombre,slug) VALUES (?,?)")->execute([$n, $this->slugLibre($n)]);
        } catch (PDOException $e) {
            if ($e->getCode() === "23000") throw new InvalidArgumentException("Ya existe una marca llamada «" . $n . "».");
            throw $e;
        }
        return (int)$this->db->lastInsertId();
    }

    /** Cambia el nombre y actualiza el texto autos.marca en la misma transacción */
    public function renombrar(int $id, string $nombre): void {
        $n = $this->validar($nombre, $id);
        $propia = $this->db->inTransaction();
        if (!$propia) $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE marcas SET nombre=?, slug=? WHERE id=?")->execute([$n, $this->slugLibre($n, $id), $id]);
            $this->db->prepare("UPDATE autos SET marca=? WHERE marca_id=?")->execute([$n, $id]);
            if (!$propia) $this->db->commit();
        } catch (Throwable $e) {
            if (!$propia && $this->db->inTransaction()) $this->db->rollBack();
            if ($e instanceof PDOException && $e->getCode() === "23000") throw new InvalidArgumentException("Ya existe una marca llamada «" . $n . "».");
            throw $e;
        }
    }

    public function guardarLogo(int $id, ?string $archivo): void {
        $this->db->prepare("UPDATE marcas SET logo=? WHERE id=?")->execute([$archivo, $id]);
    }

    public function activar(int $id, bool $activa): void {
        $this->db->prepare("UPDATE marcas SET activo=? WHERE id=?")->execute([$activa ? 1 : 0, $id]);
    }

    /**
     * Marcas de la portada: activas y con al menos una pieza a la venta (el mismo criterio que el catálogo público).
     * La que más piezas tiene va primero.
     */
    public function conLogoYPiezas(): array {
        return $this->query(
            "SELECT m.id, m.nombre, m.logo, COUNT(p.id) AS total
             FROM marcas m JOIN autos a ON a.marca_id=m.id JOIN partes p ON p.auto_id=a.id
             WHERE m.activo=1 AND p.activo=1 AND p.cantidad>0
             GROUP BY m.id ORDER BY total DESC, m.nombre ASC"
        );
    }
}
