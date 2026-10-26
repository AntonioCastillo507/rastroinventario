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
 * Flujo completo del carrito por HTTP contra un php -S real sobre rastroinventario_test, con cookies y token reales:
 * añadir → cambiar cantidades → paso 2 → pedido. Se comprueba que el total pintado es el que queda guardado, el stock exacto,
 * el ITBMS, la cancelación y que volver atrás o pedir lo mismo dos veces no repite el pedido. Correos solo @example.invalid.
 * Usa commits (el servidor es otra conexión) y borra lo suyo al final por ids y correos exactos.
 */
final class CarritoFlujoHttpTest extends TestCase {
    private const CLAVE = "Frase de prueba del flujo completo 2026";
    private PDO $db;
    private ServidorTemporal $srv;
    private array $partes = [];
    private array $correos = [];

    protected function setUp(): void {
        parent::setUp();
        $this->db = \Database::get();
        BaseDatos::verificarBase($this->db);
        $this->assertFalse($this->db->inTransaction());
        $this->srv = new ServidorTemporal();
        $this->srv->iniciar();
    }

    protected function tearDown(): void {
        $this->srv->detener();
        $ids = [];
        if ($this->correos) {
            $inE = implode(",", array_map([$this->db, "quote"], $this->correos));
            $ids = $this->db->query("SELECT id FROM pedidos WHERE cliente_email IN ($inE)")->fetchAll(PDO::FETCH_COLUMN);
            $this->db->exec("DELETE FROM login_logs WHERE email_intento IN ($inE)");
            $this->db->exec("DELETE FROM usuarios WHERE email IN ($inE)");
        }
        if ($ids) {
            $in = implode(",", array_map("intval", $ids));
            $this->db->exec("DELETE FROM pedido_items WHERE pedido_id IN ($in)");
            $this->db->exec("DELETE FROM pedidos WHERE id IN ($in)");
        }
        if ($this->partes) {
            $in = implode(",", array_map("intval", $this->partes));
            $this->db->exec("DELETE FROM pedido_items WHERE parte_id IN ($in)");
            $this->db->exec("DELETE FROM partes WHERE id IN ($in)");
        }
        parent::tearDown();
    }

    private function parte(int $stock, float $precio, string $nombre): int {
        $id = Datos::parte($this->db, $precio, $stock, $nombre);
        $this->partes[] = $id;
        return $id;
    }

    private function cliente(): ClienteHttp {
        return new ClienteHttp($this->srv->base());
    }

    private function anadir(ClienteHttp $web, int $parte, int $cantidad = 1): void {
        $r = $web->postConToken("/index.php?action=detalle&id=$parte", "/index.php?action=agregar_carrito&id=$parte", ["cantidad" => (string)$cantidad]);
        $this->assertSame(302, $r["codigo"]);
    }

    private function cambiar(ClienteHttp $web, int $parte, int $cantidad): array {
        return $web->postConToken("/index.php?action=carrito", "/index.php?action=actualizar_carrito&id=$parte", ["cantidad" => (string)$cantidad]);
    }

    /** Monto que la pantalla pinta en data-resumen="..." (sin el $) */
    private function monto(string $html, string $clave): float {
        $n = Html::xpath($html)->query("//*[@data-resumen='$clave']")->item(0);
        $this->assertNotNull($n, "falta el monto $clave");
        return (float)str_replace([",", "$"], "", trim($n->textContent));
    }

    private function datosInvitado(string $quien): array {
        $email = "flujo.$quien." . bin2hex(random_bytes(4)) . "@example.invalid";
        $this->correos[] = $email;
        return ["cliente_nombre" => "Invitado $quien", "cliente_telefono" => "555-0100", "cliente_email" => $email, "cliente_direccion" => "Calle Inventada 7",
            "cliente_notas" => "", "nueva_pass" => self::CLAVE];
    }

    private function pedidoDe(string $email): ?array {
        $s = $this->db->prepare("SELECT * FROM pedidos WHERE cliente_email = ? ORDER BY id");
        $s->execute([$email]);
        return $s->fetch() ?: null;
    }

    private function pedidosDe(string $email): int {
        $s = $this->db->prepare("SELECT COUNT(*) FROM pedidos WHERE cliente_email = ?");
        $s->execute([$email]);
        return (int)$s->fetchColumn();
    }

    public function testElPedidoGuardaExactamenteLoQueSeVioConStockElImpuestoYSuCancelacion(): void {
        $a = $this->parte(10, 12.50, "Faro del flujo"); $b = $this->parte(4, 3.25, "Moldura del flujo"); $c = $this->parte(7, 100.10, "Capó del flujo");
        $web = $this->cliente();
        $this->anadir($web, $c); $this->anadir($web, $a); $this->anadir($web, $b);
        $this->assertSame(302, $this->cambiar($web, $a, 3)["codigo"]);
        $this->assertSame(302, $this->cambiar($web, $b, 99)["codigo"], "99 se recorta al stock real (4)");

        $p1 = $web->get("/index.php?action=carrito")["cuerpo"];
        $this->assertStringContainsString("Solo quedan 4", $p1, "el aviso de ajuste sale tras la redirección");
        $esperado = \Itbms::calcular(3 * 12.50 + 4 * 3.25 + 1 * 100.10);
        $this->assertEqualsWithDelta($esperado["total"], $this->monto($p1, "total"), 0.001, "paso 1");

        $p2 = $web->get("/index.php?action=carrito_datos")["cuerpo"];
        $totalVisto = $this->monto($p2, "total");
        $this->assertEqualsWithDelta($esperado["total"], $totalVisto, 0.001, "paso 2");

        $datos = $this->datosInvitado("normal");
        $r = $web->confirmarPaso2($datos);
        $this->assertSame(302, $r["codigo"]);
        $this->assertStringContainsString("action=compra_exitosa", (string)$r["ubicacion"]);
        $exito = $web->get(ClienteHttp::ruta($r["ubicacion"]))["cuerpo"];
        $this->assertStringContainsString("Pedido registrado exitosamente", $exito);

        $ped = $this->pedidoDe($datos["cliente_email"]);
        $this->assertNotNull($ped);
        $this->assertEqualsWithDelta($totalVisto, (float)$ped["total"], 0.001, "lo guardado es lo que se vio en el paso 2");
        $this->assertEqualsWithDelta($esperado["subtotal"], (float)$ped["subtotal"], 0.001);
        $this->assertEqualsWithDelta($esperado["itbms"], (float)$ped["total"] - (float)$ped["subtotal"], 0.001, "ITBMS 7 %");
        $this->assertSame([7 - 1, 10 - 3, 4 - 4], [Datos::stock($this->db, $c), Datos::stock($this->db, $a), Datos::stock($this->db, $b)], "stock exacto");
        $lineas = $this->db->query("SELECT parte_id, parte_nombre, precio_unitario, cantidad FROM pedido_items WHERE pedido_id = " . (int)$ped["id"] . " ORDER BY id")->fetchAll();
        $this->assertCount(3, $lineas);
        $orden = array_map("intval", array_column($lineas, "parte_id")); $asc = $orden; sort($asc);
        $this->assertSame($asc, $orden, "las líneas se registran por id ascendente (mismo orden de bloqueo siempre)");
        $porId = []; foreach ($lineas as $l) $porId[(int)$l["parte_id"]] = $l;
        $this->assertEqualsWithDelta(12.50, (float)$porId[$a]["precio_unitario"], 0.001);
        $this->assertSame(3, (int)$porId[$a]["cantidad"]);
        $this->assertSame("Moldura del flujo", $porId[$b]["parte_nombre"]);
        $this->assertSame(4, (int)$porId[$b]["cantidad"], "recortada al stock");
        $this->assertSame("pendiente", $ped["estado"]);

        // el carrito quedó vacío: volver atrás y pedir lo mismo otra vez no repite el pedido
        $otra = $web->postConToken("/index.php?action=carrito", "/index.php?action=procesar_carrito", $datos);
        $this->assertSame(302, $otra["codigo"]);
        $this->assertStringContainsString("action=carrito", (string)$otra["ubicacion"]);
        $this->assertSame(1, $this->pedidosDe($datos["cliente_email"]));
        $this->assertSame(302, $web->get("/index.php?action=carrito_datos")["codigo"], "el paso 2 con el carrito vacío no se muestra");

        // cancelar devuelve el stock exacto
        $r = (new \PedidoModel())->cambiarEstado((int)$ped["id"], "cancelado");
        $this->assertSame([7, 10, 4], [Datos::stock($this->db, $c), Datos::stock($this->db, $a), Datos::stock($this->db, $b)], "stock inicial otra vez");
    }

    public function testLaUltimaUnidadSoloLaCompraUnoYElOtroVuelveAlCarritoSinPedido(): void {
        $u = $this->parte(1, 50.00, "Única del flujo");
        $uno = $this->cliente(); $dos = $this->cliente();
        $this->anadir($uno, $u); $this->anadir($dos, $u);
        $paso2Dos = $dos->get("/index.php?action=carrito_datos")["cuerpo"]; // el segundo ya abrió el paso 2 con la pieza
        $this->assertStringContainsString("action=compra_exitosa", (string)$uno->confirmarPaso2($d1 = $this->datosInvitado("uno"))["ubicacion"]);
        $r2 = $dos->post("/index.php?action=procesar_carrito", ($d2 = $this->datosInvitado("dos")) + ["csrf_token" => ClienteHttp::token($paso2Dos), "resumen_visto" => ClienteHttp::oculto($paso2Dos, "resumen_visto")]);
        $this->assertSame(302, $r2["codigo"]);
        $this->assertStringNotContainsString("compra_exitosa", (string)$r2["ubicacion"]);
        $this->assertSame(1, $this->pedidosDe($d1["cliente_email"]));
        $this->assertSame(0, $this->pedidosDe($d2["cliente_email"]));
        $this->assertSame(0, (int)$this->db->query("SELECT COUNT(*) FROM usuarios WHERE email = " . $this->db->quote($d2["cliente_email"]))->fetchColumn(), "la cuenta del segundo no se crea");
        $this->assertSame(0, Datos::stock($this->db, $u), "ni sobrante ni negativo");
        $this->assertStringContainsString("action=carrito_datos", (string)$r2["ubicacion"], "vuelve al paso 2 porque lo que vio ya no es lo que hay");
        $sigue = $dos->get(ClienteHttp::ruta($r2["ubicacion"]));
        $this->assertSame(302, $sigue["codigo"], "el paso 2 ya no tiene nada que pedir");
        $this->assertStringContainsString("«Única del flujo» ya no está disponible", $dos->get(ClienteHttp::ruta($sigue["ubicacion"]))["cuerpo"], "el segundo ve por qué");
    }

    public function testUnaPiezaDesactivadaEntreLosPasosNoSeCompraYSeDiceCual(): void {
        $a = $this->parte(5, 10.00, "Pieza que sigue"); $b = $this->parte(5, 20.00, "Pieza que se apaga");
        $web = $this->cliente();
        $this->anadir($web, $a); $this->anadir($web, $b);
        $abierto = $web->get("/index.php?action=carrito_datos")["cuerpo"]; // ya abrió el paso 2 con las dos piezas
        $this->db->exec("UPDATE partes SET activo = 0 WHERE id = $b");
        $datos = $this->datosInvitado("apagada");
        $r = $web->post("/index.php?action=procesar_carrito", $datos + ["csrf_token" => ClienteHttp::token($abierto), "resumen_visto" => ClienteHttp::oculto($abierto, "resumen_visto")]);
        $this->assertStringContainsString("action=carrito_datos", (string)$r["ubicacion"]);
        $this->assertSame(0, $this->pedidosDe($datos["cliente_email"]));
        $this->assertSame([5, 5], [Datos::stock($this->db, $a), Datos::stock($this->db, $b)], "no se descontó nada");
        $nuevo = $web->get(ClienteHttp::ruta($r["ubicacion"]));
        $this->assertSame(200, $nuevo["codigo"], "el paso 2 se vuelve a pintar con lo que queda");
        $this->assertStringContainsString("«Pieza que se apaga» ya no está disponible", $nuevo["cuerpo"], "se dice cuál pieza");
        $this->assertEqualsWithDelta(\Itbms::calcular(10.00)["total"], $this->monto($nuevo["cuerpo"], "total"), 0.001, "el resumen ya solo suma la que sigue");
        $this->assertStringContainsString("Tu pedido cambió", $nuevo["cuerpo"], "el aviso de qué pasó");
    }

    public function testElPrecioQueCambiaEntreElCarritoYElPedidoSeCobraConElDeLaBase(): void {
        $a = $this->parte(5, 10.00, "Pieza que sube");
        $web = $this->cliente();
        $this->anadir($web, $a, 2);
        $this->db->exec("UPDATE partes SET precio = 15.00 WHERE id = $a");
        $p2 = $web->get("/index.php?action=carrito_datos")["cuerpo"];
        $this->assertEqualsWithDelta(\Itbms::calcular(30.00)["total"], $this->monto($p2, "total"), 0.001, "el paso 2 ya muestra el precio nuevo");
        $datos = $this->datosInvitado("precio");
        $web->confirmarPaso2($datos);
        $this->assertEqualsWithDelta(\Itbms::calcular(30.00)["total"], (float)$this->pedidoDe($datos["cliente_email"])["total"], 0.001);
    }

    public function testLosErroresDelFormularioVuelvenAlPaso2ConLoEscritoYElCarritoIntacto(): void {
        $a = $this->parte(5, 10.00, "Pieza del error");
        $web = $this->cliente();
        $this->anadir($web, $a, 2);
        $datos = $this->datosInvitado("error");
        $r = $web->confirmarPaso2(["nueva_pass" => "corta"] + $datos);
        $this->assertStringContainsString("action=carrito_datos", (string)$r["ubicacion"]);
        $pagina = $web->get(ClienteHttp::ruta($r["ubicacion"]))["cuerpo"];
        $this->assertStringContainsString("La contraseña debe tener al menos", $pagina);
        $this->assertSame($datos["cliente_nombre"], Html::xpath($pagina)->query("//input[@name='cliente_nombre']")->item(0)->getAttribute("value"));
        $this->assertSame(5, Datos::stock($this->db, $a));
        $this->assertSame(0, $this->pedidosDe($datos["cliente_email"]));
        $this->assertStringContainsString("Pieza del error", $web->get("/index.php?action=carrito")["cuerpo"], "el carrito sigue");
    }

    public function testDosPestanasUnaCambiaElCarritoYLaOtraSigueFuncionandoConElMismoToken(): void {
        $a = $this->parte(9, 10.00, "Pieza de dos pestañas");
        $web = $this->cliente();
        $this->anadir($web, $a);
        $token = ClienteHttp::token($web->get("/index.php?action=carrito")["cuerpo"]); // la "pestaña B" guarda su formulario
        $this->cambiar($web, $a, 4);                                                   // la "pestaña A" cambia la cantidad
        $r = $web->post("/index.php?action=actualizar_carrito&id=$a", ["cantidad" => "5", "csrf_token" => $token]); // B sigue valiendo
        $this->assertSame(302, $r["codigo"]);
        $this->assertStringContainsString("action=carrito", (string)$r["ubicacion"]);
        $this->assertEqualsWithDelta(\Itbms::calcular(50.00)["total"], $this->monto($web->get("/index.php?action=carrito")["cuerpo"], "total"), 0.001);
    }
}
