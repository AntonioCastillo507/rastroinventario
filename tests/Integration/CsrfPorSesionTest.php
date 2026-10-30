<?php
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatos;
use Tests\Support\Datos;
use Tests\Support\Proceso;

/**
 * Token CSRF por sesión, con los controladores reales en un proceso aparte: se renueva al iniciar sesión, al registrarse,
 * al comprar como invitado y al cambiar la clave; y las rutas de compra, sin token válido, no dejan una página en blanco:
 * guardan lo escrito, avisan y vuelven al formulario sin tocar stock, carrito ni cuentas.
 * Usa commits y borra lo suyo al final por ids y correos exactos.
 */
final class CsrfPorSesionTest extends TestCase {
    private const CLAVE = "Frase-de-prueba-del-token-2026";
    private const VIEJO = "abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789";
    private PDO $db;
    private array $partes = [];
    private array $correos = [];
    private array $uids = [];
    private string $logs;

    protected function setUp(): void {
        parent::setUp();
        $this->db = \Database::get();
        BaseDatos::verificarBase($this->db);
        $this->assertFalse($this->db->inTransaction());
        $this->logs = RASTRO_TEST_TMP . DIRECTORY_SEPARATOR . "csrf_" . uniqid();
        mkdir($this->logs, 0777, true);
    }

    protected function tearDown(): void {
        $emails = $this->correos;
        foreach ($this->uids as $id) {
            $s = $this->db->prepare("SELECT email FROM usuarios WHERE id = ?");
            $s->execute([$id]);
            if ($e = $s->fetchColumn()) $emails[] = $e;
        }
        $ids = [];
        if ($emails || $this->uids) {
            $inE = $emails ? implode(",", array_map([$this->db, "quote"], $emails)) : "''";
            $inU = $this->uids ? implode(",", array_map("intval", $this->uids)) : "0";
            $ids = $this->db->query("SELECT id FROM pedidos WHERE cliente_email IN ($inE) OR usuario_id IN ($inU)")->fetchAll(PDO::FETCH_COLUMN);
            $this->db->exec("DELETE FROM usuarios WHERE email IN ($inE) OR id IN ($inU)");
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
        foreach (glob($this->logs . "/*") ?: [] as $f) @unlink($f);
        @rmdir($this->logs);
        parent::tearDown();
    }

    private function parte(int $stock = 10): int {
        $id = Datos::parte($this->db, 10.00, $stock);
        $this->partes[] = $id;
        return $id;
    }

    /** @return array{0:int,1:string} */
    private function clienteConClave(): array {
        $email = "csrf." . bin2hex(random_bytes(4)) . "@example.invalid";
        $this->correos[] = $email;
        $this->db->prepare("INSERT INTO usuarios (nombre, email, password) VALUES ('Cliente CSRF', ?, ?)")->execute([$email, password_hash(self::CLAVE, PASSWORD_BCRYPT)]);
        $id = (int)$this->db->lastInsertId();
        $this->uids[] = $id;
        $this->db->prepare("INSERT INTO usuario_roles (usuario_id, rol_id) SELECT ?, id FROM roles WHERE nombre = 'Cliente'")->execute([$id]);
        return [$id, $email];
    }

    private function sesionDe(int $uid, string $email, array $mas = []): array {
        return $mas + ["csrf_token" => self::VIEJO, "user" => ["id" => $uid, "nombre" => "Cliente CSRF", "email" => $email], "user_roles" => ["Cliente"]];
    }

    private function accion(string $clase, string $metodo, array $post, array $sesion, bool $csrf = true, array $get = []): array {
        return Proceso::accion(["clase" => $clase, "accion" => $metodo, "csrf" => $csrf, "ip" => "203.0.113.90",
            "post" => $post, "get" => $get, "sesion" => $sesion, "correo" => "ok", "dir_logs" => $this->logs]);
    }

    private function esTokenNuevo(array $r): void {
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $r["sesion"]["csrf_token"]);
        $this->assertNotSame(self::VIEJO, $r["sesion"]["csrf_token"], "el token cambió");
    }

    // ── Se renueva ──

    public function testIniciarSesionRenuevaElToken(): void {
        [, $email] = $this->clienteConClave();
        $r = $this->accion("AuthController", "doLogin", ["email" => $email, "password" => self::CLAVE], ["csrf_token" => self::VIEJO]);
        $this->assertArrayNotHasKey("login_error", $r["sesion"]);
        $this->assertArrayHasKey("user", $r["sesion"]);
        $this->esTokenNuevo($r);
    }

    public function testUnLoginFallidoNoRenuevaElToken(): void {
        [, $email] = $this->clienteConClave();
        $r = $this->accion("AuthController", "doLogin", ["email" => $email, "password" => "clave que no es"], ["csrf_token" => self::VIEJO], false);
        // sin token en el POST no se procesa; con el token de la sesión sí, y el fallo deja el mismo token
        $this->assertSame(403, $r["codigo"]);
        $r = Proceso::accion(["clase" => "AuthController", "accion" => "doLogin", "csrf" => true, "ip" => "203.0.113.91",
            "post" => ["email" => $email, "password" => "clave que no es"], "sesion" => ["csrf_token" => self::VIEJO]]);
        $this->assertSame(self::VIEJO, $r["sesion"]["csrf_token"]);
        $this->assertArrayNotHasKey("user", $r["sesion"]);
    }

    public function testRegistrarseRenuevaElToken(): void {
        $email = "registro." . bin2hex(random_bytes(4)) . "@example.invalid";
        $this->correos[] = $email;
        $r = $this->accion("AuthController", "procesarRegistro", ["nombre" => "Ana Prueba", "email" => $email, "telefono" => "", "password" => self::CLAVE], ["csrf_token" => self::VIEJO]);
        $this->assertArrayHasKey("user", $r["sesion"], json_encode($r["sesion"]));
        $this->esTokenNuevo($r);
    }

    public function testLaCompraDeInvitadoQueAbreSesionRenuevaElToken(): void {
        $parte = $this->parte(5);
        $email = "invitado." . bin2hex(random_bytes(4)) . "@example.invalid";
        $this->correos[] = $email;
        $r = $this->accion("PedidoController", "procesarCompra", ["parte_id" => $parte, "cantidad" => 1, "cliente_nombre" => "Invitado Token", "cliente_telefono" => "555-0100",
            "cliente_email" => $email, ...Datos::dirPost(), "nueva_pass" => self::CLAVE, "cliente_notas" => ""], ["csrf_token" => self::VIEJO]);
        $this->assertArrayHasKey("pedido_exitoso", $r["sesion"], json_encode($r["sesion"]));
        $this->esTokenNuevo($r);
    }

    public function testElCarritoDeInvitadoQueAbreSesionRenuevaElToken(): void {
        $parte = $this->parte(5);
        $email = "invitado." . bin2hex(random_bytes(4)) . "@example.invalid";
        $this->correos[] = $email;
        $r = $this->accion("PedidoController", "procesarCarrito", ["resumen_visto" => Datos::huella($this->db, [$parte => 1]), "cliente_nombre" => "Invitado Token", "cliente_telefono" => "555-0100", "cliente_email" => $email,
            ...Datos::dirPost(), "nueva_pass" => self::CLAVE, "cliente_notas" => ""], ["csrf_token" => self::VIEJO, "cart" => [$parte => ["parte_id" => $parte, "nombre" => "x", "precio" => 10.0, "cantidad" => 1, "max" => 5, "thumbnail" => null, "marca" => ""]]]);
        $this->assertArrayHasKey("pedido_exitoso", $r["sesion"], json_encode($r["sesion"]));
        $this->esTokenNuevo($r);
    }

    public function testCambiarLaClaveDesdeMiPerfilRenuevaElToken(): void {
        [$uid, $email] = $this->clienteConClave();
        $nueva = "Otra-frase-de-prueba-del-perfil-2026";
        $r = $this->accion("PedidoController", "miClave", ["actual" => self::CLAVE, "nueva" => $nueva, "confirmar" => $nueva], $this->sesionDe($uid, $email));
        $this->assertStringContainsString("Contraseña actualizada correctamente.", $r["salida"]);
        $this->esTokenNuevo($r);
    }

    public function testUnCambioDeClaveRechazadoNoRenuevaElToken(): void {
        [$uid, $email] = $this->clienteConClave();
        $r = $this->accion("PedidoController", "miClave", ["actual" => "no es", "nueva" => "Otra-frase-larga-2026", "confirmar" => "Otra-frase-larga-2026"], $this->sesionDe($uid, $email));
        $this->assertStringContainsString("no es correcta", $r["salida"]);
        $this->assertSame(self::VIEJO, $r["sesion"]["csrf_token"]);
    }

    // ── Dos pestañas: el mismo token vale más de una vez ──

    public function testElMismoTokenVale_ParaVariasAccionesSeguidas(): void {
        $parte = $this->parte(5);
        $sesion = ["csrf_token" => self::VIEJO];
        for ($i = 1; $i <= 3; $i++) {
            $r = Proceso::accion(["clase" => "PedidoController", "accion" => "agregarCarrito", "csrf" => true, "ip" => "203.0.113.92", "get" => ["id" => (string)$parte],
                "post" => [], "sesion" => $sesion, "servidor" => ["HTTP_X_REQUESTED_WITH" => "fetch"]]);
            $this->assertSame(200, $r["codigo"], "acción $i");
            $this->assertSame(self::VIEJO, $r["sesion"]["csrf_token"], "el token sigue siendo el mismo");
            $sesion = $r["sesion"];
        }
        $this->assertSame(3, (int)($sesion["cart"][$parte]["cantidad"] ?? $sesion["cart"][$parte]));
    }

    // ── Sin token válido, las rutas de compra vuelven al formulario ──

    public function testElCarritoSinTokenVuelveAlCarritoConLoEscritoYNoTocaNada(): void {
        $parte = $this->parte(5);
        $email = "invitado." . bin2hex(random_bytes(4)) . "@example.invalid";
        $this->correos[] = $email;
        $cart = [$parte => ["parte_id" => $parte, "nombre" => "x", "precio" => 10.0, "cantidad" => 2, "max" => 5, "thumbnail" => null, "marca" => ""]];
        $r = $this->accion("PedidoController", "procesarCarrito", ["cliente_nombre" => "Escrito Aquí", "cliente_telefono" => "555-0100", "cliente_email" => $email,
            ...Datos::dirPost(), "nueva_pass" => self::CLAVE, "cliente_notas" => ""], ["csrf_token" => self::VIEJO, "cart" => $cart], false);
        $this->assertSame("warning", $r["sesion"]["flash"]["type"]);
        $this->assertStringContainsString("caducó", $r["sesion"]["flash"]["msg"]);
        $this->assertSame("Escrito Aquí", $r["sesion"]["form_viejo"]["carrito"]["cliente_nombre"]);
        $this->assertArrayNotHasKey("nueva_pass", $r["sesion"]["form_viejo"]["carrito"], "la clave nunca se guarda");
        $this->assertArrayHasKey("cart", $r["sesion"], "el carrito sigue");
        $this->assertSame(5, Datos::stock($this->db, $parte));
        $this->assertSame(0, (int)$this->db->query("SELECT COUNT(*) FROM usuarios WHERE email = " . $this->db->quote($email))->fetchColumn());
        if (Proceso::veCabeceras()) $this->assertStringContainsString("action=carrito", (string)Proceso::ubicacion($r));
    }

    public function testLaCompraDeInvitadoSinTokenVuelveAlFormularioConLoEscrito(): void {
        $parte = $this->parte(5);
        $email = "invitado." . bin2hex(random_bytes(4)) . "@example.invalid";
        $this->correos[] = $email;
        $r = $this->accion("PedidoController", "procesarCompra", ["parte_id" => $parte, "cantidad" => 2, "cliente_nombre" => "Escrito Aquí", "cliente_telefono" => "555-0100",
            "cliente_email" => $email, ...Datos::dirPost(), "nueva_pass" => self::CLAVE, "cliente_notas" => "nota"], ["csrf_token" => self::VIEJO], false);
        $this->assertSame("warning", $r["sesion"]["flash"]["type"]);
        $this->assertSame("Escrito Aquí", $r["sesion"]["form_viejo"]["compra"]["cliente_nombre"]);
        $this->assertSame("2", $r["sesion"]["form_viejo"]["compra"]["cantidad"]);
        $this->assertArrayNotHasKey("nueva_pass", $r["sesion"]["form_viejo"]["compra"]);
        $this->assertSame(5, Datos::stock($this->db, $parte));
        $this->assertSame(0, (int)$this->db->query("SELECT COUNT(*) FROM usuarios WHERE email = " . $this->db->quote($email))->fetchColumn());
        if (Proceso::veCabeceras()) $this->assertStringContainsString("action=comprar&id=$parte", (string)Proceso::ubicacion($r));
    }

    public function testLaConfirmacionDeUnClienteSinTokenVuelveAlFormularioYNoDescuentaStock(): void {
        [$uid, $email] = $this->clienteConClave();
        $parte = $this->parte(5);
        $r = $this->accion("PedidoController", "confirmarCompra", ["parte_id" => $parte, "cantidad" => 2, ...Datos::dirPost(["calle" => "Calle del cliente 5"]), "cliente_notas" => ""], $this->sesionDe($uid, $email), false);
        $this->assertSame("warning", $r["sesion"]["flash"]["type"]);
        $this->assertSame("Calle del cliente 5", $r["sesion"]["form_viejo"]["compra_conf"]["calle"]);
        $this->assertSame(5, Datos::stock($this->db, $parte));
        $this->assertSame(0, (int)$this->db->query("SELECT COUNT(*) FROM pedidos WHERE usuario_id = $uid")->fetchColumn());
    }

    public function testAnadirSinJavaScriptYSinTokenVuelveALaTarjetaConAviso(): void {
        $parte = $this->parte(5);
        $r = $this->accion("PedidoController", "agregarCarrito", ["volver" => "/rastroinventario/?ver=catalogo"], ["csrf_token" => self::VIEJO], false, ["id" => (string)$parte]);
        $this->assertSame("warning", $r["sesion"]["flash"]["type"]);
        $this->assertArrayNotHasKey("cart", $r["sesion"]);
        if (Proceso::veCabeceras()) $this->assertStringEndsWith("#parte-$parte", (string)Proceso::ubicacion($r));
    }

    public function testUnPanelSigueDando403SinPaginaEnBlancoDeCompra(): void {
        [$uid, $email] = $this->clienteConClave();
        $r = $this->accion("UserController", "miClave", ["actual" => self::CLAVE, "nueva" => "Frase-nueva-del-panel-2026", "confirmar" => "Frase-nueva-del-panel-2026"], $this->sesionDe($uid, $email), false);
        $this->assertSame(403, $r["codigo"]);
        $this->assertStringContainsString("La página caducó", $r["salida"], "página temática, sin texto interno");
        $this->assertStringNotContainsString("CSRF", $r["salida"]);
    }

    public function testElCambioDeClaveDelPanelRenuevaElToken(): void {
        [$uid, $email] = $this->clienteConClave();
        $nueva = "Frase-nueva-del-panel-2026";
        $r = $this->accion("UserController", "miClave", ["actual" => self::CLAVE, "nueva" => $nueva, "confirmar" => $nueva], $this->sesionDe($uid, $email));
        $this->assertStringContainsString("Contraseña actualizada correctamente.", $r["salida"]);
        $this->esTokenNuevo($r);
    }
}
