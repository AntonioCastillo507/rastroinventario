<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\GuardaMarca;

/**
 * database/demo.sql: datos de demostración todos inventados (sin datos reales ni de la marca real) y que cargan
 * sobre una base recién instalada. Se carga dentro de la transacción de la prueba y se deshace al terminar.
 */
final class DemoSqlTest extends BaseDatos {
    private function sql(): string {
        return (string)file_get_contents(BASE_PATH . "/database/demo.sql");
    }

    /** @return list<string> sentencias sin comentarios */
    private function sentencias(): array {
        $sinComentarios = preg_replace('/^\s*--.*$/m', "", $this->sql());
        return array_values(array_filter(array_map("trim", preg_split('/;\s*\n/', $sinComentarios)), fn($s) => $s !== ""));
    }

    public function testNoTrae_datosReales_niLosDeLaMarca(): void {
        $sql = $this->sql();
        $patrones = GuardaMarca::patrones(["nombre" => (string)\Marca::get("nombre"), "telefono" => (string)\Marca::get("telefono"), "correo" => (string)\Marca::get("correo")]);
        foreach ($patrones as $etiqueta => $regex) $this->assertDoesNotMatchRegularExpression("~$regex~iu", $sql, "demo.sql lleva $etiqueta de la marca real");
        foreach (["Antonio", "Castillo", "gmail", "hotmail", "yahoo", "outlook"] as $real) $this->assertStringNotContainsStringIgnoringCase($real, $sql, $real);
    }

    public function testTodoCorreoEsExampleInvalidYTodoTelefonoEsDeEjemplo(): void {
        $sql = $this->sql();
        preg_match_all("/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/", $sql, $correos);
        $this->assertNotEmpty($correos[0]);
        foreach ($correos[0] as $c) $this->assertStringEndsWith("@example.invalid", $c, $c);
        preg_match_all("/'(\+?[0-9][0-9 ()-]{5,})'/", $sql, $telefonos);
        $this->assertNotEmpty($telefonos[1]);
        foreach ($telefonos[1] as $t) $this->assertMatchesRegularExpression("/^555-01\d\d$/", $t, "teléfono de ejemplo");
    }

    public function testSeCargaSobreUnaBaseInstaladaYQuedaCoherente(): void {
        $antes = [];
        foreach (["autos", "secciones", "partes", "usuarios", "pedidos", "pedido_items"] as $t) $antes[$t] = (int)$this->db->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        $this->assertSame(0, $antes["autos"] + $antes["secciones"] + $antes["partes"] + $antes["pedidos"], "la base de pruebas parte vacía de datos de tienda");
        foreach ($this->sentencias() as $s) $this->db->exec($s);

        $this->assertSame(4, (int)$this->db->query("SELECT COUNT(*) FROM autos")->fetchColumn());
        $this->assertSame(4, (int)$this->db->query("SELECT COUNT(*) FROM secciones")->fetchColumn());
        $this->assertSame(12, (int)$this->db->query("SELECT COUNT(*) FROM partes")->fetchColumn());
        $this->assertSame(3, (int)$this->db->query("SELECT COUNT(*) FROM pedidos")->fetchColumn());
        $this->assertSame(5, (int)$this->db->query("SELECT COUNT(*) FROM pedido_items")->fetchColumn());
        $this->assertSame($antes["usuarios"] + 3, (int)$this->db->query("SELECT COUNT(*) FROM usuarios")->fetchColumn());

        // los usuarios demo no pueden iniciar sesión ('!' no es un hash) y cada uno tiene su rol
        $demo = $this->db->query("SELECT email, password FROM usuarios WHERE email LIKE '%.demo@example.invalid'")->fetchAll();
        $this->assertCount(3, $demo);
        foreach ($demo as $u) $this->assertSame("!", $u["password"], $u["email"]);
        $roles = $this->db->query("SELECT r.nombre FROM usuario_roles ur JOIN roles r ON r.id = ur.rol_id JOIN usuarios u ON u.id = ur.usuario_id WHERE u.email LIKE '%.demo@example.invalid' ORDER BY r.nombre")->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertSame(["Cliente", "Consultor", "Vendedor"], $roles);

        // sin imágenes: la tienda muestra la pieza sin foto
        $this->assertSame(0, (int)$this->db->query("SELECT COUNT(*) FROM partes WHERE thumbnail IS NOT NULL OR imagen_grande IS NOT NULL")->fetchColumn());

        // cada pedido: total = subtotal + impuesto, y el subtotal es la suma de sus líneas
        foreach ($this->db->query("SELECT id, subtotal, total FROM pedidos")->fetchAll() as $p) {
            $suma = (float)$this->db->query("SELECT SUM(precio_unitario * cantidad) FROM pedido_items WHERE pedido_id = " . (int)$p["id"])->fetchColumn();
            $this->assertEqualsWithDelta($suma, (float)$p["subtotal"], 0.001, "subtotal del pedido " . $p["id"]);
            $this->assertEqualsWithDelta(\Itbms::calcular($suma)["total"], (float)$p["total"], 0.001, "total del pedido " . $p["id"]);
        }
    }

    public function testElEncabezadoDeLaDemoDiceQueEsInventada(): void {
        $this->assertStringContainsString("todos inventados", $this->sql());
        $this->assertStringContainsString("example.invalid", $this->sql());
    }
}
