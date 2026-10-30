<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\Datos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/**
 * Carrito en dos pasos: lo que pintan el paso 1 ("Carrito") y el paso 2 ("Tus datos"), con las pantallas reales renderizadas
 * en el proceso de la prueba. Los totales salen de Itbms con los precios de la base; en los formularios no viaja dinero.
 */
final class CarritoPantallasTest extends BaseDatos {
    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
    }

    protected function tearDown(): void {
        $_GET = [];
        $_SESSION = array_diff_key($_SESSION, array_flip(["cart", "cart_deshacer", "user", "user_roles", "flash", "carrito_error", "form_viejo"]));
        unset($_SERVER["REQUEST_METHOD"]);
        parent::tearDown();
    }

    private function pantalla(string $nombre): \DOMXPath {
        return Html::xpath(Pantallas::render($this->db, $nombre));
    }

    private function texto(\DOMXPath $x, string $consulta): string {
        $n = $x->query($consulta)->item(0);
        return $n ? trim(preg_replace('/\s+/', " ", $n->textContent)) : "";
    }

    /** Carrito con las cantidades pedidas, pintado por el controlador real */
    private function carritoCon(array $cart, string $accion = "verCarrito"): \DOMXPath {
        Pantallas::render($this->db, "carrito_vacio"); // crea los datos de apoyo y prepara $_GET y la petición
        $_SESSION["cart"] = $cart;
        ob_start();
        try { (new \PedidoController())->$accion(); } finally { $html = (string)ob_get_clean(); }
        return Html::xpath($html);
    }

    // ── Paso 1 ──

    public function testElPaso1LlevaElIndicadorDePasosConElPrimeroActual(): void {
        $x = $this->pantalla("carrito_invitado");
        $this->assertSame(1, $x->query("//nav[@aria-label='Pasos de la compra']//li[@aria-current='step']")->length);
        $this->assertStringContainsString("Carrito", $this->texto($x, "//li[@aria-current='step']"));
        $this->assertSame(2, $x->query("//ol[contains(@class,'pasos')]/li")->length);
        $this->assertSame(1, $x->query("//h1")->length);
        $this->assertSame("Carrito", $this->texto($x, "//h1"));
    }

    public function testCadaLineaTieneMenosYMasConLaCantidadYaCalculadaYNombreAccesible(): void {
        $x = $this->pantalla("carrito_invitado"); // 2 unidades, stock 12
        $this->assertSame(1, $x->query("//li[@data-linea]")->length);
        $menos = $x->query("//button[@data-accion='menos']")->item(0);
        $mas = $x->query("//button[@data-accion='mas']")->item(0);
        $this->assertSame("cantidad", $menos->getAttribute("name"));
        $this->assertSame("1", $menos->getAttribute("value"));
        $this->assertSame("3", $mas->getAttribute("value"));
        $this->assertFalse($menos->hasAttribute("disabled"));
        $this->assertFalse($mas->hasAttribute("disabled"));
        $this->assertStringContainsString("Pieza de pantalla", $menos->getAttribute("aria-label"));
        $this->assertStringContainsString("Pieza de pantalla", $mas->getAttribute("aria-label"));
        $this->assertSame("2", $this->texto($x, "//*[@data-linea-cantidad]"));
        $this->assertSame("group", $x->query("//div[contains(@class,'cantidad-control')]")->item(0)->getAttribute("role"));
    }

    public function testMenosSeDesactivaEnUnoYMasEnElStock(): void {
        $id = Datos::parte($this->db, 9.99, 1, "Pieza de una unidad");
        $x = $this->carritoCon([$id => 1]);
        $this->assertTrue($x->query("//button[@data-accion='menos']")->item(0)->hasAttribute("disabled"));
        $this->assertTrue($x->query("//button[@data-accion='mas']")->item(0)->hasAttribute("disabled"));
        $id2 = Datos::parte($this->db, 9.99, 5, "Pieza de cinco");
        $x = $this->carritoCon([$id2 => 5]);
        $this->assertFalse($x->query("//button[@data-accion='menos']")->item(0)->hasAttribute("disabled"));
        $this->assertTrue($x->query("//button[@data-accion='mas']")->item(0)->hasAttribute("disabled"));
        $this->assertSame("5", $x->query("//button[@data-accion='mas']")->item(0)->getAttribute("value"), "nunca más del stock");
    }

    public function testCadaFormularioDeLaListaEsPostConTokenYNoHayConfirmNiOnclick(): void {
        $pagina = Pantallas::render($this->db, "carrito_invitado");
        $x = Html::xpath($pagina);
        $forms = $x->query("//form[@data-carrito-form]");
        $this->assertSame(2, $forms->length, "cantidad y quitar");
        foreach ($forms as $f) {
            $this->assertSame("post", strtolower($f->getAttribute("method")));
            $this->assertSame(1, $x->query(".//input[@name='csrf_token']", $f)->length);
        }
        $this->assertStringNotContainsString("confirm(", $pagina, "quitar se deshace, no se confirma");
        $this->assertSame(0, $x->query("//*[@data-carrito]//*[@onclick]")->length);
    }

    public function testElResumenSumaElImpuestoConLosPreciosDeLaBase(): void {
        $x = $this->pantalla("carrito_invitado"); // 2 × 25.50
        $esperado = \Itbms::calcular(51.00);
        $this->assertSame('$' . number_format($esperado["subtotal"], 2), $this->texto($x, "//*[@data-resumen='subtotal']"));
        $this->assertSame('$' . number_format($esperado["itbms"], 2), $this->texto($x, "//*[@data-resumen='itbms']"));
        $this->assertSame('$' . number_format($esperado["total"], 2), $this->texto($x, "//*[@data-resumen='total']"));
        $this->assertSame('$51.00', $this->texto($x, "//*[@data-linea-subtotal]"));
        $this->assertStringContainsString("text-precio", $x->query("//*[@data-resumen='total']")->item(0)->getAttribute("class"));
        $this->assertStringContainsString("num", $x->query("//*[@data-resumen='total']")->item(0)->getAttribute("class"));
    }

    public function testElBotonPrincipalLlevaAlPaso2YNoHayFormularioDeDatosEnElPaso1(): void {
        $x = $this->pantalla("carrito_invitado");
        $enlace = $x->query("//a[contains(@class,'carrito-continuar')]")->item(0);
        $this->assertStringEndsWith("/?action=carrito_datos", $enlace->getAttribute("href"));
        $this->assertSame("Continuar con tus datos", trim($enlace->textContent));
        $this->assertSame(0, $x->query("//input[@name='nueva_pass' or @name='cliente_direccion']")->length, "los siete campos ya no comparten pantalla con el resumen");
    }

    public function testElCarritoVacioNoMuestraResumenNiLista(): void {
        $x = $this->pantalla("carrito_vacio");
        $this->assertStringContainsString("Tu carrito está vacío", $this->texto($x, "//main"));
        $this->assertSame(0, $x->query("//li[@data-linea]")->length);
        $this->assertSame(0, $x->query("//*[@data-resumen]")->length);
        $this->assertSame(1, $x->query("//a[contains(@href,'ver=catalogo')][contains(@class,'btn-gold')]")->length);
    }

    public function testLaPiezaQueSeDesactivoSeQuitaConAvisoVisibleConSuNombre(): void {
        $x = $this->pantalla("carrito_avisos");
        $aviso = $this->texto($x, "//div[@id='carrito-avisos']//div[@role='alert'][contains(@class,'alert-warning')]");
        $this->assertStringContainsString("«Pieza desactivada de pantalla» ya no está disponible", $aviso);
        $this->assertSame(1, $x->query("//li[@data-linea]")->length, "queda la pieza viva");
    }

    public function testElAvisoDeDeshacerSePintaDesdeLaSesionConSuFormularioPost(): void {
        $x = $this->pantalla("carrito_deshacer");
        $caja = $x->query("//*[@data-deshacer]")->item(0);
        $this->assertNotNull($caja);
        $this->assertStringContainsString("Quitaste «Pieza de pantalla»", $caja->textContent);
        $f = $x->query(".//form", $caja)->item(0);
        $this->assertStringContainsString("action=restaurar_carrito", $f->getAttribute("action"));
        $this->assertSame(1, $x->query(".//input[@name='csrf_token']", $f)->length);
        $this->assertSame("Deshacer", trim($x->query(".//button", $f)->item(0)->textContent));
        $this->assertSame("status", $caja->getAttribute("role"));
    }

    public function testLaRegionDeEstadoParaLectoresDePantallaExiste(): void {
        $x = $this->pantalla("carrito_invitado");
        $r = $x->query("//*[@id='carrito-estado']")->item(0);
        $this->assertSame("polite", $r->getAttribute("aria-live"));
    }

    // ── Paso 2 ──

    public function testElPaso2MarcaElSegundoPasoYVuelveAlCarritoConUnEnlace(): void {
        $x = $this->pantalla("carrito_datos_invitado");
        $this->assertStringContainsString("Tus datos", $this->texto($x, "//li[@aria-current='step']"));
        $this->assertSame("Tus datos", $this->texto($x, "//h1"));
        $this->assertStringEndsWith("/?action=carrito", $x->query("//a[normalize-space()='Volver al carrito']")->item(0)->getAttribute("href"));
    }

    public function testElInvitadoVeEntregaYCuentaYElFormularioNoLlevaDinero(): void {
        $x = $this->pantalla("carrito_datos_invitado");
        $f = $x->query("//form[@id='form-datos']")->item(0);
        $this->assertStringEndsWith("/?action=procesar_carrito", $f->getAttribute("action"));
        $this->assertSame("post", strtolower($f->getAttribute("method")));
        foreach (["csrf_token", "cliente_nombre", "cliente_telefono", "cliente_email", "nueva_pass"] as $campo) {
            $this->assertSame(1, $x->query(".//input[@name='$campo']", $f)->length, $campo);
        }
        $this->assertSame(1, $x->query(".//textarea[@name='cliente_notas']", $f)->length, "cliente_notas");
        foreach (["calle", "casa", "barriada", "referencia"] as $campo) $this->assertSame(1, $x->query(".//input[@name='$campo']", $f)->length, $campo);
        foreach (["provincia", "distrito", "corregimiento"] as $campo) $this->assertSame(1, $x->query(".//select[@name='$campo']", $f)->length, $campo);
        $this->assertSame(0, $x->query(".//*[@name='cliente_direccion']", $f)->length, "ya no hay un texto libre de dirección");
        $this->assertSame(0, $x->query(".//input[contains(@name,'precio') or contains(@name,'total') or contains(@name,'subtotal') or contains(@name,'itbms') or @name='cantidad']", $f)->length, "ni precios ni totales ni cantidades en el formulario");
        $this->assertSame("new-password", $x->query(".//input[@name='nueva_pass']", $f)->item(0)->getAttribute("autocomplete"));
        $legendas = array_map(fn($n) => trim($n->textContent), iterator_to_array($x->query("//form[@id='form-datos']//legend")));
        $this->assertSame(["Entrega", "Dirección en Panamá", "Tu cuenta"], $legendas, "la cuenta es un bloque aparte: F2 lo cambia sin tocar la entrega");
    }

    public function testElClienteConSesionNoEscribeSusDatosNiVeLaContrasena(): void {
        $x = $this->pantalla("carrito_datos_cliente");
        $this->assertSame(0, $x->query("//input[@name='nueva_pass' or @name='cliente_nombre' or @name='cliente_email']")->length);
        $this->assertStringContainsString("Compras como", $this->texto($x, "//form[@id='form-datos']"));
        $this->assertSame("Calle inventada 1", $x->query("//input[@name='calle']")->item(0)->getAttribute("value"), "la dirección del perfil viene puesta");
        $this->assertNotNull($x->query("//input[@name='guardar_direccion']")->item(0), "casilla para guardar la dirección");
        $this->assertNotNull($x->query("//input[@name='cliente_telefono']")->item(0), "el cliente confirma su teléfono de contacto");
        $this->assertSame(["Entrega", "Dirección en Panamá"], array_map(fn($n) => trim($n->textContent), iterator_to_array($x->query("//form[@id='form-datos']//legend"))));
    }

    public function testUnErrorConservaLoEscritoYNuncaLaContrasena(): void {
        $x = $this->pantalla("carrito_datos_error");
        $this->assertStringContainsString("La contraseña debe tener al menos", $this->texto($x, "//div[@role='alert']"));
        $this->assertSame("Rosa Escrita", $x->query("//input[@name='cliente_nombre']")->item(0)->getAttribute("value"));
        $this->assertSame("rosa@example.invalid", $x->query("//input[@name='cliente_email']")->item(0)->getAttribute("value"));
        $this->assertSame("Calle de lo escrito 5", $x->query("//input[@name='calle']")->item(0)->getAttribute("value"));
        $this->assertFalse($x->query("//input[@name='nueva_pass']")->item(0)->hasAttribute("value"));
    }

    public function testElResumenDelPaso2ListaLasLineasYTraeLosMismosTotalesQueElPaso1(): void {
        $p1 = $this->pantalla("carrito_invitado");
        $p2 = $this->pantalla("carrito_datos_invitado");
        $this->assertSame(1, $p2->query("//aside//li[contains(@class,'resumen-linea')]")->length);
        $this->assertStringContainsString("Pieza de pantalla", $this->texto($p2, "//aside//li"));
        foreach (["subtotal", "itbms", "total"] as $k) {
            $this->assertSame($this->texto($p1, "//*[@data-resumen='$k']"), $this->texto($p2, "//*[@data-resumen='$k']"), $k);
        }
    }

    // ── Paso 2: resumen_visto, barra inferior y hoja del resumen ──

    public function testElFormularioLlevaLaHuellaDeLoQueSePinta(): void {
        $id = Datos::parte($this->db, 9.99, 5, "Pieza de la huella");
        $x = $this->carritoCon([$id => 2], "datosCarrito");
        $h = $x->query("//form[@id='form-datos']//input[@type='hidden'][@name='resumen_visto']")->item(0);
        $this->assertNotNull($h);
        $this->assertSame(\Carrito::huella([["id" => $id, "cantidad" => 2, "precio" => 9.99]]), $h->getAttribute("value"));
        $otra = $this->carritoCon([$id => 3], "datosCarrito")->query("//input[@name='resumen_visto']")->item(0)->getAttribute("value");
        $this->assertNotSame($h->getAttribute("value"), $otra, "otra cantidad, otra huella");
        $this->db->exec("UPDATE partes SET precio = 10.00 WHERE id = $id");
        $precio = $this->carritoCon([$id => 2], "datosCarrito")->query("//input[@name='resumen_visto']")->item(0)->getAttribute("value");
        $this->assertNotSame($h->getAttribute("value"), $precio, "otro precio, otra huella");
    }

    public function testElResumenEsUnaHojaInferiorEnMovilYUnaBarraLateralEnEscritorioConUnSoloHtml(): void {
        $x = $this->pantalla("carrito_datos_invitado");
        $hoja = $x->query("//aside[@id='resumen-hoja']")->item(0);
        $this->assertNotNull($hoja);
        $clases = explode(" ", $hoja->getAttribute("class"));
        foreach (["offcanvas-lg", "offcanvas-bottom"] as $c) $this->assertContains($c, $clases);
        $this->assertSame("-1", $hoja->getAttribute("tabindex"));
        $this->assertSame("resumen-titulo", $hoja->getAttribute("aria-labelledby"));
        $this->assertSame(1, $x->query("//*[@id='resumen-titulo']")->length);
        $this->assertSame(1, $x->query("//aside[@id='resumen-hoja']//*[@data-resumen='total']")->length, "los totales existen una sola vez");
        $cerrar = $x->query(".//button[contains(@class,'btn-close')]", $hoja)->item(0);
        $this->assertSame("offcanvas", $cerrar->getAttribute("data-bs-dismiss"));
        $this->assertSame("Cerrar el resumen", $cerrar->getAttribute("aria-label"));
    }

    public function testLaBarraInferiorMuestraElTotalYAbreLaHojaConUnBotonSecundario(): void {
        $x = $this->pantalla("carrito_datos_invitado");
        $barra = $x->query("//div[contains(@class,'barra-resumen')]")->item(0);
        $this->assertNotNull($barra);
        $this->assertSame($this->texto($x, "//aside//*[@data-resumen='total']"), $this->texto($x, "//div[contains(@class,'barra-resumen')]//*[contains(@class,'precio')]"), "mismo total que el resumen");
        $b = $x->query(".//button", $barra)->item(0);
        $this->assertSame("button", $b->getAttribute("type"));
        $this->assertSame("offcanvas", $b->getAttribute("data-bs-toggle"));
        $this->assertSame("#resumen-hoja", $b->getAttribute("data-bs-target"));
        $this->assertSame("resumen-hoja", $b->getAttribute("aria-controls"));
        $this->assertSame("Ver resumen", trim($b->textContent));
        $this->assertStringNotContainsString("btn-gold", $b->getAttribute("class"), "el botón principal es solo \"Confirmar pedido\"");
    }

    public function testConfirmarPedidoEsElUnicoBotonPrincipalYLlevaElTotal(): void {
        $x = $this->pantalla("carrito_datos_invitado");
        $this->assertSame(1, $x->query("//main//*[contains(@class,'btn-gold')]")->length);
        $conf = $x->query("//form[@id='form-datos']//button[@type='submit']")->item(0);
        $total = $this->texto($x, "//aside//*[@data-resumen='total']");
        $this->assertStringContainsString("Confirmar pedido", $conf->textContent);
        $this->assertStringContainsString($total, trim(preg_replace('/\s+/', " ", $conf->textContent)), "sin abrir la hoja igual se sabe cuánto se paga");
        $this->assertSame(0, $x->query("//div[contains(@class,'barra-resumen')]//button[@type='submit']")->length, "la barra no envía el formulario");
    }

    public function testLosIdsDelPaso2NoSeRepiten(): void {
        $x = $this->pantalla("carrito_datos_invitado");
        $ids = [];
        foreach ($x->query("//*[@id]") as $n) $ids[] = $n->getAttribute("id");
        $this->assertSame(array_values(array_unique($ids)), $ids, "ids duplicados: " . implode(",", array_diff_assoc($ids, array_unique($ids))));
    }
}
