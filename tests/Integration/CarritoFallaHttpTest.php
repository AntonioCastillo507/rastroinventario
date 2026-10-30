<?php
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatos;
use Tests\Support\ClienteHttp;
use Tests\Support\Datos;
use Tests\Support\Html;
use Tests\Support\ServidorTemporal;

/**
 * La falla de registrar() simulada de punta a punta por HTTP (php -S real, cookies y token reales): el pedido o una de sus líneas
 * falla DENTRO de la transacción, el stock vuelve, la cuenta de invitado no queda, el carrito sigue y el paso 2 repone lo escrito.
 * La falla se simula con dos triggers que solo existen en rastroinventario_test mientras corre esta clase.
 */
final class CarritoFallaHttpTest extends TestCase {
    private const MARCA_PEDIDO = "__FALLA_HTTP_PEDIDO__";
    private const MARCA_ITEM = "__FALLA_HTTP_ITEM__";
    private PDO $db;
    private ServidorTemporal $srv;
    private ClienteHttp $web;
    private array $partes = [];
    private array $correos = [];

    public static function setUpBeforeClass(): void {
        $db = \Database::get();
        BaseDatos::verificarBase($db); // los triggers solo se crean en la base de pruebas
        self::quitarTriggers($db);
        $db->exec("CREATE TRIGGER trg_prueba_http_pedido BEFORE INSERT ON pedidos FOR EACH ROW BEGIN IF NEW.cliente_notas = '" . self::MARCA_PEDIDO . "' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'falla simulada del pedido'; END IF; END");
        $db->exec("CREATE TRIGGER trg_prueba_http_item BEFORE INSERT ON pedido_items FOR EACH ROW BEGIN IF NEW.parte_nombre = '" . self::MARCA_ITEM . "' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'falla simulada de la linea'; END IF; END");
    }

    public static function tearDownAfterClass(): void {
        self::quitarTriggers(\Database::get());
    }

    private static function quitarTriggers(PDO $db): void {
        $db->exec("DROP TRIGGER IF EXISTS trg_prueba_http_pedido");
        $db->exec("DROP TRIGGER IF EXISTS trg_prueba_http_item");
    }

    protected function setUp(): void {
        parent::setUp();
        $this->db = \Database::get();
        BaseDatos::verificarBase($this->db);
        $this->assertFalse($this->db->inTransaction());
        $this->srv = new ServidorTemporal();
        $this->srv->iniciar();
        $this->web = new ClienteHttp($this->srv->base());
    }

    protected function tearDown(): void {
        $this->srv->detener();
        if ($this->correos) {
            $inE = implode(",", array_map([$this->db, "quote"], $this->correos));
            $ids = $this->db->query("SELECT id FROM pedidos WHERE cliente_email IN ($inE)")->fetchAll(PDO::FETCH_COLUMN);
            $this->db->exec("DELETE FROM login_logs WHERE email_intento IN ($inE)");
            $this->db->exec("DELETE FROM usuarios WHERE email IN ($inE)");
            if ($ids) {
                $in = implode(",", array_map("intval", $ids));
                $this->db->exec("DELETE FROM pedido_items WHERE pedido_id IN ($in)");
                $this->db->exec("DELETE FROM pedidos WHERE id IN ($in)");
            }
        }
        if ($this->partes) {
            $in = implode(",", array_map("intval", $this->partes));
            $this->db->exec("DELETE FROM pedido_items WHERE parte_id IN ($in)");
            $this->db->exec("DELETE FROM partes WHERE id IN ($in)");
        }
        parent::tearDown();
    }

    private function parte(int $stock, string $nombre): int {
        $id = Datos::parte($this->db, 10.00, $stock, $nombre);
        $this->partes[] = $id;
        return $id;
    }

    private function anadir(int $parte, int $cantidad): void {
        $this->web->postConToken("/index.php?action=detalle&id=$parte", "/index.php?action=agregar_carrito&id=$parte", ["cantidad" => (string)$cantidad]);
    }

    private function datos(string $notas): array {
        $email = "falla." . bin2hex(random_bytes(4)) . "@example.invalid";
        $this->correos[] = $email;
        return ["cliente_nombre" => "Invitado Falla", "cliente_telefono" => "555-0100", "cliente_email" => $email, ...Datos::dirPost(),
            "cliente_notas" => $notas, "nueva_pass" => "Frase de prueba de la falla simulada 2026"];
    }

    private function assertTodoVolvio(array $r, array $datos, array $stock): void {
        $this->assertSame(302, $r["codigo"]);
        $this->assertStringNotContainsString("compra_exitosa", (string)$r["ubicacion"]);
        $this->assertStringContainsString("action=carrito", (string)$r["ubicacion"]);
        foreach ($stock as $id => $s) $this->assertSame($s, Datos::stock($this->db, $id), "el stock de $id volvió");
        $e = $this->db->quote($datos["cliente_email"]);
        $this->assertSame(0, (int)$this->db->query("SELECT COUNT(*) FROM pedidos WHERE cliente_email = $e")->fetchColumn(), "ni pedido huérfano");
        $this->assertSame(0, (int)$this->db->query("SELECT COUNT(*) FROM usuarios WHERE email = $e")->fetchColumn(), "la cuenta de invitado no queda");
        $this->assertFalse($this->db->inTransaction());
    }

    public function testSiFallaElPedidoTodoVuelveYElPaso2RepoleLoEscrito(): void {
        $a = $this->parte(10, "Pieza falla pedido A"); $b = $this->parte(5, "Pieza falla pedido B");
        $this->anadir($a, 2); $this->anadir($b, 1);
        $datos = $this->datos(self::MARCA_PEDIDO);
        $r = $this->web->confirmarPaso2($datos);
        $this->assertTodoVolvio($r, $datos, [$a => 10, $b => 5]);
        $paso1 = $this->web->get(ClienteHttp::ruta($r["ubicacion"]))["cuerpo"];
        $this->assertStringContainsString("No se pudo registrar el pedido", $paso1, "avisa con un código de referencia");
        $this->assertStringContainsString("Pieza falla pedido A", $paso1, "el carrito sigue");
        $paso2 = $this->web->get("/index.php?action=carrito_datos")["cuerpo"];
        $x = Html::xpath($paso2);
        $this->assertSame("Invitado Falla", $x->query("//input[@name='cliente_nombre']")->item(0)->getAttribute("value"), "lo escrito vuelve");
        $this->assertSame($datos["cliente_email"], $x->query("//input[@name='cliente_email']")->item(0)->getAttribute("value"));
        $this->assertFalse($x->query("//input[@name='nueva_pass']")->item(0)->hasAttribute("value"), "la clave nunca vuelve");
        // y con la falla resuelta, el mismo carrito se compra
        $datos["cliente_notas"] = "";
        $ok = $this->web->confirmarPaso2($datos);
        $this->assertStringContainsString("action=compra_exitosa", (string)$ok["ubicacion"]);
        $this->assertSame([8, 4], [Datos::stock($this->db, $a), Datos::stock($this->db, $b)]);
    }

    public function testSiFallaUnaLineaDeLaSegundaPiezaSeDeshaceElPedidoYElStockDeLasDos(): void {
        $a = $this->parte(10, "Pieza falla línea A"); $b = $this->parte(5, self::MARCA_ITEM);
        $this->anadir($a, 3); $this->anadir($b, 2);
        $datos = $this->datos("");
        $r = $this->web->confirmarPaso2($datos);
        $this->assertTodoVolvio($r, $datos, [$a => 10, $b => 5]);
        $this->assertStringContainsString("No se pudo registrar el pedido", $this->web->get(ClienteHttp::ruta($r["ubicacion"]))["cuerpo"]);
    }
}
