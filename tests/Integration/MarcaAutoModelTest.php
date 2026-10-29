<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;

/** Marcas de autos: nombre único sin importar mayúsculas ni espacios, renombrado sincronizado y migración 003 */
final class MarcaAutoModelTest extends BaseDatos {
    private \MarcaAutoModel $m;

    protected function setUp(): void {
        parent::setUp();
        $this->m = new \MarcaAutoModel();
    }

    private function auto(string $marca, ?int $marcaId = null): int {
        $this->db->prepare("INSERT INTO autos (marca, marca_id, modelo, anio) VALUES (?, ?, 'M', 2020)")->execute([$marca, $marcaId]);
        return (int)$this->db->lastInsertId();
    }

    private function parte(int $auto, int $cantidad = 5, int $activo = 1): int {
        $this->db->prepare("INSERT INTO partes (nombre, auto_id, precio, cantidad, activo) VALUES ('P', ?, 10, ?, ?)")->execute([$auto, $cantidad, $activo]);
        return (int)$this->db->lastInsertId();
    }

    public function testCrearLimpiaElNombreYGeneraSlug(): void {
        $id = $this->m->crear("  Mazda   Speed  ");
        $f = $this->m->findById($id);
        $this->assertSame("Mazda Speed", $f["nombre"]);
        $this->assertSame("mazda-speed", $f["slug"]);
        $this->assertSame(1, (int)$f["activo"]);
        $this->assertNull($f["logo"]);
    }

    public function testSlugSinAcentosYUnico(): void {
        $this->assertSame("citroen-c-elysee", \MarcaAutoModel::slugDe("Citroën C-Élysée"));
        $this->assertSame("marca", \MarcaAutoModel::slugDe("***"));
        $a = $this->m->crear("A.B");
        $b = $this->m->crear("A-B");
        $this->assertSame("a-b", $this->m->findById($a)["slug"]);
        $this->assertSame("a-b-2", $this->m->findById($b)["slug"]);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("variantesDeHonda")]
    public function testMarcaDuplicadaSeRechaza(string $variante): void {
        $this->m->crear("Honda");
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Ya existe");
        $this->m->crear($variante);
    }

    public static function variantesDeHonda(): array {
        return [["honda"], ["Honda "], ["  HONDA"], ["Honda"]];
    }

    public function testMonogramaConIniciales(): void {
        foreach (["Honda" => "HO", "Mercedes Benz" => "MB", "  alfa   romeo " => "AR", "Land Rover Defender" => "LR", "BMW" => "BM", "X" => "X", "Škoda" => "ŠK", "Citroën C4" => "CC", "***" => "?"] as $nombre => $esperado) {
            $this->assertSame($esperado, \MarcaAutoModel::iniciales($nombre), $nombre);
        }
    }

    public function testNombreVacioOLargoSeRechaza(): void {
        foreach (["", "   ", str_repeat("a", 101)] as $malo) {
            try { $this->m->crear($malo); $this->fail("debió rechazar «$malo»"); }
            catch (\InvalidArgumentException $e) { $this->assertNotSame("", $e->getMessage()); }
        }
    }

    public function testRenombrarActualizaElTextoDeLosAutos(): void {
        $id = $this->m->crear("Dogde");
        $a = $this->auto("Dogde", $id);
        $this->m->renombrar($id, "Dodge");
        $this->assertSame("Dodge", $this->m->findById($id)["nombre"]);
        $this->assertSame("Dodge", $this->db->query("SELECT marca FROM autos WHERE id=$a")->fetchColumn());
    }

    public function testRenombrarNoPuedeChocarConOtraMarca(): void {
        $this->m->crear("Kia");
        $id = $this->m->crear("Kya");
        $this->expectException(\InvalidArgumentException::class);
        $this->m->renombrar($id, "kia ");
    }

    public function testRenombrarSoloCambiaMayusculasDeLaPropiaMarca(): void {
        $id = $this->m->crear("honda");
        $this->m->renombrar($id, "Honda");
        $this->assertSame("Honda", $this->m->findById($id)["nombre"]);
    }

    public function testPortadaSoloMarcasActivasConPiezasALaVenta(): void {
        $con = $this->m->crear("Zz Con");
        $sinStock = $this->m->crear("Zz Sin stock");
        $inactivaPieza = $this->m->crear("Zz Pieza inactiva");
        $desactivada = $this->m->crear("Zz Desactivada");
        $vacia = $this->m->crear("Zz Vacia");
        $this->parte($this->auto("Zz Con", $con));
        $this->parte($this->auto("Zz Con", $con));
        $this->parte($this->auto("Zz Sin stock", $sinStock), 0);
        $this->parte($this->auto("Zz Pieza inactiva", $inactivaPieza), 5, 0);
        $this->parte($this->auto("Zz Desactivada", $desactivada));
        $this->m->activar($desactivada, false);
        $this->auto("Zz Vacia", $vacia);

        $nombres = array_column($this->m->conLogoYPiezas(), "total", "nombre");
        $this->assertSame(2, (int)$nombres["Zz Con"]);
        foreach (["Zz Sin stock", "Zz Pieza inactiva", "Zz Desactivada", "Zz Vacia"] as $fuera) {
            $this->assertArrayNotHasKey($fuera, $nombres, $fuera);
        }
    }

    public function testActivasParaElFormularioNoIncluyeDesactivadas(): void {
        $a = $this->m->crear("Zz Activa");
        $b = $this->m->crear("Zz Apagada");
        $this->m->activar($b, false);
        $ids = array_map("intval", array_column($this->m->activas(), "id"));
        $this->assertContains($a, $ids);
        $this->assertNotContains($b, $ids);
    }

    public function testListarCuentaAutosYPiezasActivas(): void {
        $id = $this->m->crear("Zz Lista");
        $a = $this->auto("Zz Lista", $id);
        $this->auto("Zz Lista", $id);
        $this->parte($a);
        $this->parte($a, 5, 0);
        $f = array_values(array_filter($this->m->listar(), fn($r) => (int)$r["id"] === $id))[0];
        $this->assertSame(2, (int)$f["autos"]);
        $this->assertSame(1, (int)$f["piezas"]);
    }

    public function testGuardarYQuitarLogo(): void {
        $id = $this->m->crear("Zz Logo");
        $this->m->guardarLogo($id, "marca_" . str_repeat("a", 32) . ".png");
        $this->assertSame("marca_" . str_repeat("a", 32) . ".png", $this->m->findById($id)["logo"]);
        $this->m->guardarLogo($id, null);
        $this->assertNull($this->m->findById($id)["logo"]);
    }

    /** Ejecuta solo la parte de datos de la migración 003 (el DDL haría commit y rompería la transacción de la prueba) */
    private function correrDatosMigracion(): void {
        $sql = file_get_contents(dirname(__DIR__, 2) . "/database/migraciones/003_marcas.sql");
        $this->assertStringContainsString("-- DATOS", $sql);
        $datos = substr($sql, strpos($sql, "-- DATOS"));
        $sentencias = array_filter(array_map("trim", explode(";\n", preg_replace('/^--.*$/m', "", $datos))));
        $this->assertCount(2, $sentencias);
        foreach ($sentencias as $s) $this->db->exec($s);
    }

    public function testMigracionFusionaVariantesYDejaUnaMarca(): void {
        $a = $this->auto("Zmig", null);
        $b = $this->auto("zmig ", null);
        $c = $this->auto(" ZMIG", null);
        $d = $this->auto("Otra Zmig", null);
        $this->correrDatosMigracion();

        $filas = $this->db->query("SELECT id, nombre FROM marcas WHERE nombre LIKE '%zmig%' ORDER BY nombre")->fetchAll();
        $this->assertCount(2, $filas);
        $this->assertSame(["Otra Zmig", "Zmig"], array_column($filas, "nombre"));
        $ids = $this->db->query("SELECT id, marca, marca_id FROM autos WHERE id IN ($a,$b,$c,$d) ORDER BY id")->fetchAll();
        $this->assertSame(["Zmig", "Zmig", "Zmig", "Otra Zmig"], array_column($ids, "marca"));
        $this->assertSame((int)$ids[0]["marca_id"], (int)$ids[1]["marca_id"]);
        $this->assertSame((int)$ids[0]["marca_id"], (int)$ids[2]["marca_id"]);
        $this->assertNotSame((int)$ids[0]["marca_id"], (int)$ids[3]["marca_id"]);

        $this->correrDatosMigracion(); // idempotente
        $this->assertSame(2, (int)$this->db->query("SELECT COUNT(*) FROM marcas WHERE nombre LIKE '%zmig%'")->fetchColumn());
    }

    public function testMigracionUsaElNombreDelAutoConMenorId(): void {
        $this->auto("zname ", null);
        $this->auto("ZNAME", null);
        $this->correrDatosMigracion();
        $this->assertSame("zname", $this->db->query("SELECT nombre FROM marcas WHERE nombre LIKE 'zname%'")->fetchColumn());
    }
}
