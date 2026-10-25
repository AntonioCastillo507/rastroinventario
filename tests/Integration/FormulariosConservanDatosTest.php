<?php
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\BaseDatos;
use Tests\Support\ClienteHttp;
use Tests\Support\Html;
use Tests\Support\ServidorTemporal;

/**
 * Flujo real contra un servidor temporal (php -S) y rastroinventario_test: cuando un formulario vuelve con
 * un error, la persona encuentra lo que escribió (menos la contraseña) y solo una vez.
 * Usa commits (el servidor es otra conexión) y borra lo suyo al final por ids y correos exactos.
 */
final class FormulariosConservanDatosTest extends TestCase {
    private PDO $db;
    private ServidorTemporal $srv;
    private ClienteHttp $web;
    private ?int $parteId = null;
    private string $sufijo;
    private array $correos = [];

    protected function setUp(): void {
        parent::setUp();
        $this->db = \Database::get();
        BaseDatos::verificarBase($this->db);
        $this->assertFalse($this->db->inTransaction());
        $this->sufijo = bin2hex(random_bytes(4));
        $this->db->prepare("INSERT INTO partes (nombre, descripcion, precio, cantidad) VALUES (?, 'Prueba de formularios', 12.50, 5)")->execute(["Pieza formularios " . $this->sufijo]);
        $this->parteId = (int)$this->db->lastInsertId();
        $this->srv = new ServidorTemporal();
        $this->srv->iniciar();
        $this->web = new ClienteHttp($this->srv->base());
    }

    protected function tearDown(): void {
        $this->srv->detener();
        if ($this->correos) {
            $in = implode(",", array_fill(0, count($this->correos), "?"));
            $this->db->prepare("DELETE FROM login_logs WHERE email_intento IN ($in)")->execute($this->correos);
            $this->db->prepare("DELETE FROM usuarios WHERE email IN ($in)")->execute($this->correos);
        }
        if ($this->parteId) $this->db->prepare("DELETE FROM partes WHERE id = ?")->execute([$this->parteId]);
        parent::tearDown();
    }

    private function correo(string $quien): string {
        return $this->correos[] = "$quien.$this->sufijo@example.invalid";
    }

    /** value del primer input con ese name, o null si no tiene */
    private function valor(string $html, string $name): ?string {
        $n = Html::xpath($html)->query("//input[@name='$name']");
        $this->assertSame(1, $n->length, "campo $name");
        return $n->item(0)->hasAttribute("value") ? $n->item(0)->getAttribute("value") : null;
    }

    private function texto(string $html, string $name): string {
        $n = Html::xpath($html)->query("//textarea[@name='$name']");
        $this->assertSame(1, $n->length, "textarea $name");
        return trim($n->item(0)->textContent);
    }

    public function testElLoginConErrorConservaElCorreoPeroNuncaLaContrasenaYSoloUnaVez(): void {
        $email = $this->correo("login");
        $r = $this->web->postConToken("/login.php", "/login.php", ["email" => $email, "password" => "clave-que-no-existe-9"]);
        $this->assertSame(302, $r["codigo"]);
        $this->assertStringEndsWith("/login.php", (string)$r["ubicacion"]);
        $pagina = $this->web->get("/login.php")["cuerpo"];
        $this->assertStringContainsString("Correo o contraseña incorrectos.", $pagina);
        $this->assertSame($email, $this->valor($pagina, "email"));
        $this->assertNull($this->valor($pagina, "password"), "la contraseña no vuelve");
        $this->assertStringNotContainsString("clave-que-no-existe-9", $pagina);
        $this->assertSame("", $this->valor($this->web->get("/login.php")["cuerpo"], "email"), "una sola vez");
    }

    public function testElRegistroConErrorConservaNombreCorreoYTelefono(): void {
        $email = $this->correo("registro");
        $this->web->postConToken("/registro.php", "/registro.php", ["nombre" => "Ana <b>López</b>", "email" => $email, "telefono" => "6000-1234", "password" => "corta"]);
        $pagina = $this->web->get("/registro.php")["cuerpo"];
        $this->assertStringContainsString("La contraseña debe tener al menos", $pagina);
        $this->assertSame("Ana <b>López</b>", $this->valor($pagina, "nombre"), "vuelve escapado, sin etiquetas vivas");
        $this->assertStringNotContainsString("<b>López</b>", $pagina);
        $this->assertSame($email, $this->valor($pagina, "email"));
        $this->assertSame("6000-1234", $this->valor($pagina, "telefono"));
        $this->assertNull($this->valor($pagina, "password"));
        $this->assertStringNotContainsString("corta", Html::xpath($pagina)->query("//input[@name='password']")->item(0)->getAttribute("value"));
    }

    public function testLaCompraDeInvitadoConErrorConservaTodoMenosLaContrasena(): void {
        $email = $this->correo("compra");
        $r = $this->web->postConToken("/index.php?action=comprar&id=$this->parteId", "/index.php?action=procesar_compra", [
            "parte_id" => $this->parteId, "cantidad" => 2, "cliente_nombre" => "Pedro Prueba", "cliente_telefono" => "6111-2222", "cliente_email" => $email,
            "cliente_direccion" => "Calle 5, casa 7\nreferencia: portón azul", "cliente_notas" => "Llamar antes", "nueva_pass" => "corta",
        ]);
        $this->assertSame(302, $r["codigo"]);
        $pagina = $this->web->get(ClienteHttp::ruta($r["ubicacion"]))["cuerpo"];
        $this->assertStringContainsString("La contraseña debe tener al menos", $pagina);
        $this->assertSame("Pedro Prueba", $this->valor($pagina, "cliente_nombre"));
        $this->assertSame("6111-2222", $this->valor($pagina, "cliente_telefono"));
        $this->assertSame($email, $this->valor($pagina, "cliente_email"));
        $this->assertSame("2", $this->valor($pagina, "cantidad"));
        $this->assertSame("Calle 5, casa 7\nreferencia: portón azul", str_replace("\r\n", "\n", $this->texto($pagina, "cliente_direccion")));
        $this->assertSame("Llamar antes", $this->texto($pagina, "cliente_notas"));
        $this->assertNull($this->valor($pagina, "nueva_pass"));
        $this->assertSame("", $this->valor($this->web->get(ClienteHttp::ruta($r["ubicacion"]))["cuerpo"], "cliente_nombre"), "una sola vez");
    }

    public function testElCarritoDeInvitadoConErrorConservaLosCampos(): void {
        $email = $this->correo("carrito");
        $this->web->postConToken("/index.php?action=detalle&id=$this->parteId", "/index.php?action=agregar_carrito&id=$this->parteId", []);
        $r = $this->web->postConToken("/index.php?action=carrito", "/index.php?action=procesar_carrito", [
            "cliente_nombre" => "Rosa Carrito", "cliente_telefono" => "6333-4444", "cliente_email" => $email,
            "cliente_direccion" => "Avenida Central 10", "cliente_notas" => "Dejar con el vigilante", "nueva_pass" => "corta",
        ]);
        $this->assertSame(302, $r["codigo"]);
        $pagina = $this->web->get("/index.php?action=carrito")["cuerpo"];
        $this->assertStringContainsString("La contraseña debe tener al menos", $pagina);
        $this->assertSame("Rosa Carrito", $this->valor($pagina, "cliente_nombre"));
        $this->assertSame("6333-4444", $this->valor($pagina, "cliente_telefono"));
        $this->assertSame($email, $this->valor($pagina, "cliente_email"));
        $this->assertSame("Avenida Central 10", $this->texto($pagina, "cliente_direccion"));
        $this->assertSame("Dejar con el vigilante", $this->texto($pagina, "cliente_notas"));
        $this->assertNull($this->valor($pagina, "nueva_pass"));
    }

    public function testElBotonDeEnvioSeDesactivaLoCargaCadaFormularioPublico(): void {
        // el script se sirve y cada pantalla con formulario POST lo carga (el comportamiento lo prueba EnvioJsTest)
        $js = $this->web->get("/assets/js/envio.js");
        $this->assertSame(200, $js["codigo"]);
        foreach (["/login.php", "/registro.php", "/index.php?action=carrito", "/index.php?action=comprar&id=$this->parteId"] as $ruta) {
            $this->assertStringContainsString("js/envio.js", $this->web->get($ruta)["cuerpo"], $ruta);
        }
    }
}
