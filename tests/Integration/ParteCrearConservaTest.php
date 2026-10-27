<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\Datos;
use Tests\Support\Html;

/** Crear una parte con un error devuelve el formulario NUEVO con lo escrito (antes se perdía todo). */
final class ParteCrearConservaTest extends BaseDatos {
    private function crearConPost(array $post): \DOMXPath {
        $uid = Datos::usuario($this->db);
        $this->db->prepare("INSERT INTO usuario_roles (usuario_id, rol_id) SELECT ?, id FROM roles WHERE nombre = 'Administrador'")->execute([$uid]);
        $_SESSION["user"] = ["id" => $uid, "nombre" => "Admin de prueba", "email" => "admin@example.invalid"];
        $_SESSION["user_roles"] = ["Administrador"];
        $_SERVER["REQUEST_METHOD"] = "POST";
        $_GET = ["mod" => "partes", "action" => "crear"];
        $_POST = $post + ["csrf_token" => \CsrfToken::generate()];
        ob_start();
        try {
            (new \ParteController())->crear();
        } finally {
            $html = (string)ob_get_clean();
            $_SERVER["REQUEST_METHOD"] = "GET";
            $_GET = $_POST = [];
            unset($_SESSION["user"], $_SESSION["user_roles"], $_SESSION["flash"]);
        }
        return Html::xpath($html);
    }

    public function testConUnPrecioInvalidoVuelveElFormularioNuevoConLoEscrito(): void {
        $auto = (int)$this->db->query("SELECT id FROM autos LIMIT 1")->fetchColumn();
        $x = $this->crearConPost(["nombre" => "Faro <i>trasero</i>", "descripcion" => "Para Civic 2018", "precio" => "0", "cantidad" => "7", "auto_id" => (string)$auto, "seccion_id" => ""]);
        $this->assertSame("Agregar parte al inventario", trim($x->query("//h1")->item(0)->textContent), "sigue siendo el formulario de crear");
        $this->assertSame(1, $x->query("//button[contains(.,'Guardar parte')]")->length);
        $this->assertSame(0, $x->query("//button[contains(.,'Guardar Cambios')]")->length, "no pasa a modo edición");
        $this->assertStringContainsString("alert", $x->query("//*[@role='alert']")->item(0)?->getAttribute("class") ?? "");
        $this->assertSame("Faro <i>trasero</i>", $x->query("//input[@name='nombre']")->item(0)->getAttribute("value"));
        $this->assertSame("Para Civic 2018", trim($x->query("//textarea[@name='descripcion']")->item(0)->textContent));
        $this->assertSame("0", $x->query("//input[@name='precio']")->item(0)->getAttribute("value"));
        $this->assertSame("7", $x->query("//input[@name='cantidad']")->item(0)->getAttribute("value"));
        if ($auto) $this->assertSame(1, $x->query("//select[@name='auto_id']/option[@selected][@value='$auto']")->length, "el auto elegido sigue elegido");
        $this->assertSame(0, $x->query("//i[text()='trasero']")->length, "el texto vuelve escapado");
    }

    public function testConElNombreVacioVuelvenPrecioYCantidad(): void {
        $x = $this->crearConPost(["nombre" => "", "precio" => "5", "cantidad" => "1"]); // nombre vacío: error, pero precio y cantidad vuelven
        $this->assertSame("", $x->query("//input[@name='nombre']")->item(0)->getAttribute("value"));
        $this->assertSame("5", $x->query("//input[@name='precio']")->item(0)->getAttribute("value"));
    }
}
