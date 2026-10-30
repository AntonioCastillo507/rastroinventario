<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;

/** Datos de provincias, distritos y corregimientos (database/seed_panama.sql) y su esquema */
final class DivisionPanamaTest extends BaseDatos {
    private function col(string $sql, array $p = []): array {
        $s = $this->db->prepare($sql);
        $s->execute($p);
        return $s->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function seed(): string {
        return (string)file_get_contents(BASE_PATH . "/database/seed_panama.sql");
    }

    public function testConteosDeLaCapaOficial(): void {
        $this->assertSame(13, (int)$this->db->query("SELECT COUNT(*) FROM provincias")->fetchColumn(), "10 provincias + 3 comarcas");
        $this->assertSame(10, (int)$this->db->query("SELECT COUNT(*) FROM provincias WHERE tipo = 'provincia'")->fetchColumn());
        $this->assertSame(3, (int)$this->db->query("SELECT COUNT(*) FROM provincias WHERE tipo = 'comarca'")->fetchColumn());
        $this->assertSame(83, (int)$this->db->query("SELECT COUNT(*) FROM distritos")->fetchColumn());
        $this->assertSame(699, (int)$this->db->query("SELECT COUNT(*) FROM corregimientos")->fetchColumn());
    }

    public function testLaCadenaEstaCompletaYLosCodigosCuadran(): void {
        $this->assertSame([], $this->col("SELECT d.codigo FROM distritos d LEFT JOIN provincias p ON p.codigo = d.provincia_codigo WHERE p.codigo IS NULL"));
        $this->assertSame([], $this->col("SELECT c.codigo FROM corregimientos c LEFT JOIN distritos d ON d.codigo = c.distrito_codigo WHERE d.codigo IS NULL"));
        $this->assertSame([], $this->col("SELECT codigo FROM distritos WHERE codigo NOT LIKE CONCAT(provincia_codigo, '%') OR CHAR_LENGTH(codigo) <> 4"));
        $this->assertSame([], $this->col("SELECT codigo FROM corregimientos WHERE codigo NOT LIKE CONCAT(distrito_codigo, '%') OR CHAR_LENGTH(codigo) <> 6"));
        $this->assertSame([], $this->col("SELECT d.codigo FROM distritos d LEFT JOIN corregimientos c ON c.distrito_codigo = d.codigo WHERE c.codigo IS NULL"), "todo distrito tiene corregimientos");
    }

    public function testLosNombresVienenLimpiosYSinRepetirseDentroDelPadre(): void {
        foreach (["provincias" => "nombre", "distritos" => "nombre", "corregimientos" => "nombre"] as $tabla => $c) {
            $this->assertSame([], $this->col("SELECT codigo FROM `$tabla` WHERE $c <> TRIM($c) OR $c LIKE '%  %' OR $c = ''"), "$tabla: espacios sobrantes");
        }
        $this->assertSame([], $this->col("SELECT nombre FROM distritos GROUP BY provincia_codigo, nombre HAVING COUNT(*) > 1"));
        $this->assertSame([], $this->col("SELECT nombre FROM corregimientos GROUP BY distrito_codigo, nombre HAVING COUNT(*) > 1"));
    }

    public function testConservaEnyesYDieresis(): void {
        $this->assertContains("Ñürüm", $this->col("SELECT nombre FROM distritos"));
        $this->assertContains("Müna", $this->col("SELECT nombre FROM distritos"));
        $this->assertContains("Comarca Ngäbe-Buglé", $this->col("SELECT nombre FROM provincias"));
        $this->assertContains("Panamá Oeste", $this->col("SELECT nombre FROM provincias WHERE codigo = '13'"));
    }

    public function testGunaYalaSeLlamaComoLaLeyYSuDistritoFicticioEsImplicito(): void {
        $this->assertSame(["Comarca Guna Yala"], $this->col("SELECT nombre FROM provincias WHERE codigo = '10'"));
        $this->assertSame(["1001"], $this->col("SELECT codigo FROM distritos WHERE implicito = 1"), "solo el distrito ficticio de Guna Yala");
        $this->assertSame("10", $this->col("SELECT provincia_codigo FROM distritos WHERE codigo = '1001'")[0]);
        $this->assertSame(["Ailigandí", "Narganá", "Puerto Obaldía", "Tubualá"], $this->col("SELECT nombre FROM corregimientos WHERE distrito_codigo = '1001' ORDER BY nombre"));
    }

    public function testNasoTjerDiTieneUnSoloCorregimientoImplicito(): void {
        $this->assertSame(["010501"], $this->col("SELECT codigo FROM corregimientos WHERE implicito = 1"));
        $this->assertSame(["Comarca Naso Tjër Di"], $this->col("SELECT nombre FROM distritos WHERE codigo = '0105'"));
        $this->assertSame(["010501"], $this->col("SELECT codigo FROM corregimientos WHERE distrito_codigo = '0105'"));
    }

    public function testSoloGunaYalaYNasoQuedanSinNivelVisible(): void {
        // Provincias sin ningún distrito visible: el formulario oculta el distrito y no deja una lista vacía
        $this->assertSame(["10"], $this->col(
            "SELECT p.codigo FROM provincias p LEFT JOIN distritos d ON d.provincia_codigo = p.codigo AND d.implicito = 0 WHERE d.codigo IS NULL"));
        // Distritos sin ningún corregimiento visible
        $this->assertSame(["0105"], $this->col(
            "SELECT d.codigo FROM distritos d LEFT JOIN corregimientos c ON c.distrito_codigo = d.codigo AND c.implicito = 0 WHERE c.codigo IS NULL"));
    }

    public function testElSeedDejaLaFuenteYLaFecha(): void {
        $s = $this->seed();
        $this->assertStringContainsString("Instituto Geografico Nacional", $s);
        $this->assertStringContainsString("sigigntg.anati.gob.pa", $s);
        $this->assertStringContainsString("actualizados al 2024", $s);
        $this->assertStringContainsString("Cuadro 10 del Censo 2023", $s);
        $this->assertStringContainsString("13 provincias y comarcas con rango de provincia, 83 distritos, 699 corregimientos", $s);
    }

    public function testLaBaseTieneTodoLoQueTraeElSeed(): void {
        preg_match_all("/^  \\('(\\d{6})', '(\\d{4})', /m", $this->seed(), $m);
        $this->assertCount(699, $m[1]);
        $enBase = $this->col("SELECT codigo FROM corregimientos ORDER BY codigo");
        $enSeed = $m[1];
        sort($enSeed);
        $this->assertSame($enSeed, $enBase);
    }

    public function testElSeedSePuedeCargarDosVecesSinDuplicar(): void {
        $sql = preg_replace("/^--.*$/m", "", $this->seed());
        $antes = (int)$this->db->query("SELECT COUNT(*) FROM corregimientos")->fetchColumn();
        $this->db->exec(str_replace("SET NAMES utf8mb4;", "", $sql));
        $this->assertSame($antes, (int)$this->db->query("SELECT COUNT(*) FROM corregimientos")->fetchColumn());
        $this->assertSame(13, (int)$this->db->query("SELECT COUNT(*) FROM provincias")->fetchColumn());
    }

    public function testLaMigracionYElEsquemaDeclaranLasMismasColumnas(): void {
        $mig = (string)file_get_contents(BASE_PATH . "/database/migraciones/006_direccion_panama.sql");
        $esq = (string)file_get_contents(BASE_PATH . "/database/schema.sql");
        preg_match_all("/m006_agregar\\('COLUMN', '(\\w+)', '(\\w+)'/", $mig, $m, PREG_SET_ORDER);
        $this->assertCount(14, $m, "7 de usuarios y 7 de pedidos");
        foreach ($m as [, $tabla, $col]) {
            $this->assertStringContainsString("`$col`", $esq, "schema.sql no declara $tabla.$col");
            $this->assertSame(1, (int)$this->db->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$tabla' AND COLUMN_NAME = '$col'")->fetchColumn(), "$tabla.$col");
        }
    }

    public function testNoSeTocaLoQueYaHabiaEnLosPedidos(): void {
        // Una fila escrita a la antigua (solo texto libre) sigue siendo válida: los campos nuevos quedan en NULL
        $this->db->prepare("INSERT INTO pedidos (cliente_nombre, cliente_telefono, cliente_email, cliente_direccion) VALUES ('A', '1', 'a@example.invalid', 'Calle 13, Monterrico')")->execute();
        $f = $this->db->query("SELECT * FROM pedidos ORDER BY id DESC LIMIT 1")->fetch();
        $this->assertSame("Calle 13, Monterrico", $f["cliente_direccion"]);
        foreach (["provincia", "distrito", "corregimiento", "calle", "casa", "barriada", "referencia"] as $c) $this->assertNull($f["entrega_$c"]);
    }
}
