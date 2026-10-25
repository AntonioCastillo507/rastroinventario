<?php
namespace Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BaseDatos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/**
 * Cada pantalla (tienda, acceso y panel) sale de la marca: se cambian los valores en la prueba y tienen
 * que verse en el título, la cabecera, el pie y los textos; y ya no puede quedar ni rastro de los reales.
 */
final class MarcaPantallasTest extends BaseDatos {
    private array $real;
    private const NUEVA = [
        "nombre" => "Taller Cambiado Zeta", "eslogan" => "Lema Cambiado", "descripcion" => "Descripcion cambiada del negocio.",
        "telefono" => "+999 8888-7777", "correo" => "cambiado@example.invalid", "direccion" => "Calle Cambiada 123",
        "pais" => "Paisandu", "pie" => "Pie cambiado {nombre} {pais}",
        "horario" => [["dia" => "Dia Cambiado", "horas" => "1:00 AM – 2:00 AM", "abierto" => true]],
        "portada" => ["titulo_pagina" => "Titulo de portada cambiado", "etiqueta" => "Etiqueta cambiada", "titulo" => "Titulo cambiado", "titulo_resalte" => "resalte cambiado", "subtitulo" => "Subtitulo cambiado", "buscador" => "Buscador cambiado"],
        "impuesto" => ["nombre" => "IVAX", "tasa" => 0.12],
    ];

    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
        $this->real = \Marca::todo();
        $nueva = array_replace_recursive($this->real, self::NUEVA);
        $nueva["horario"] = self::NUEVA["horario"]; // una lista se reemplaza entera, no por posición
        \Marca::usarParaPruebas($nueva);
    }

    protected function tearDown(): void {
        \Marca::usarParaPruebas(null);
        parent::tearDown();
    }

    /** @return array<string,array{0:string}> */
    public static function pantallas(): array {
        $o = [];
        foreach (Pantallas::nombres() as $n) $o[$n] = [$n];
        return $o;
    }

    /** @return array<string,array{0:string}> */
    public static function deLaTienda(): array {
        $o = [];
        foreach (Pantallas::publicas() as $n) $o[$n] = [$n];
        return $o;
    }

    #[DataProvider("pantallas")]
    public function testElTituloLlevaElNombreDeLaMarcaYNoElReal(string $pantalla): void {
        $html = Pantallas::render($this->db, $pantalla);
        $titulo = trim(Html::xpath($html)->query("//title")->item(0)->textContent);
        $this->assertStringContainsString("Taller Cambiado Zeta", $titulo, $pantalla);
        $this->assertStringNotContainsStringIgnoringCase((string)$this->real["nombre"], $titulo, $pantalla);
    }

    #[DataProvider("pantallas")]
    public function testNingunaPantallaMuestraLosDatosReales(string $pantalla): void {
        $html = Pantallas::render($this->db, $pantalla);
        $plano = html_entity_decode(strip_tags($html), ENT_QUOTES, "UTF-8");
        foreach (["nombre", "telefono", "correo"] as $k) {
            $this->assertStringNotContainsStringIgnoringCase((string)$this->real[$k], $plano, "$pantalla muestra $k real");
        }
        foreach (["telefono", "correo"] as $k) $this->assertStringNotContainsString((string)$this->real[$k], $html, "$pantalla deja $k real en el HTML (enlaces, atributos)");
    }

    #[DataProvider("deLaTienda")]
    public function testLaCabeceraYElPieDeLaTiendaUsanLaMarca(string $pantalla): void {
        $x = Html::xpath(Pantallas::render($this->db, $pantalla));
        $marca = preg_replace('/\s+/u', " ", trim($x->query("//a[contains(@class,'navbar-brand')]")->item(0)->textContent));
        $this->assertStringContainsString("Taller Cambiado Zeta", $marca, $pantalla);
        $this->assertStringContainsString("Lema Cambiado", $marca, $pantalla);
        $pie = preg_replace('/\s+/u', " ", trim($x->query("//footer")->item(0)->textContent));
        foreach (["Taller Cambiado Zeta", "Descripcion cambiada del negocio.", "+999 8888-7777", "cambiado@example.invalid", "Calle Cambiada 123", "Dia Cambiado: 1:00 AM – 2:00 AM", "Pie cambiado Taller Cambiado Zeta Paisandu"] as $texto) {
            $this->assertStringContainsString($texto, $pie, "$pantalla: pie sin «{$texto}»");
        }
    }

    public function testLaPortadaUsaLosTextosDeLaMarca(): void {
        $html = Pantallas::render($this->db, "home");
        $plano = preg_replace('/\s+/u', " ", html_entity_decode(strip_tags(str_replace("<br>", " ", $html)), ENT_QUOTES, "UTF-8"));
        foreach (["Etiqueta cambiada", "Titulo cambiado resalte cambiado", "Subtitulo cambiado"] as $t) $this->assertStringContainsString($t, $plano);
        $this->assertStringContainsString('placeholder="Buscador cambiado"', $html);
        $this->assertStringContainsString("<title>Titulo de portada cambiado — Taller Cambiado Zeta</title>", $html);
    }

    public function testLasPantallasDeAccesoUsanElNombre(): void {
        foreach (Pantallas::deAcceso() as $n) {
            $html = Pantallas::render($this->db, $n);
            $this->assertMatchesRegularExpression('~<title>[^<]*— Taller Cambiado Zeta</title>~', $html, $n);
        }
        $x = Html::xpath(Pantallas::render($this->db, "login"));
        $this->assertSame("Taller Cambiado Zeta", trim($x->query("//h1")->item(0)->textContent));
    }

    public function testElPanelUsaLaMarcaEnLaCabeceraYElPie(): void {
        foreach (["admin_dashboard", "admin_partes"] as $n) {
            $x = Html::xpath(Pantallas::render($this->db, $n));
            $this->assertStringContainsString("Taller Cambiado Zeta", trim($x->query("//a[contains(@class,'navbar-brand')]")->item(0)->textContent), $n);
            $this->assertSame("Pie cambiado Taller Cambiado Zeta Paisandu", trim($x->query("//footer")->item(0)->textContent), $n);
        }
    }

    public function testElImpuestoDelCarritoYDeLosPedidosUsaElNombreYElPorcentajeDeLaMarca(): void {
        foreach (["carrito_cliente", "comprar_cliente", "compra_exitosa", "admin_pedido_detalle"] as $n) {
            $h = Pantallas::render($this->db, $n);
            $this->assertStringNotContainsString("ITBMS", $h, $n);
            $this->assertDoesNotMatchRegularExpression('/(?<![\d.])7%/', $h, $n); // un 7% suelto; 67% del filtro del logo no cuenta
        }
        $this->assertStringContainsString("IVAX (12%)", Pantallas::render($this->db, "carrito_cliente"));
        $this->assertStringContainsString("IVAX (12%)", Pantallas::render($this->db, "compra_exitosa"));
        $this->assertStringContainsString("IVAX (12%)", Pantallas::render($this->db, "admin_pedido_detalle"));
        $this->assertStringContainsString("sin IVAX", Pantallas::render($this->db, "comprar_cliente"));
    }

    public function testSinCambiosLasPantallasMuestranLosValoresDelArchivo(): void {
        \Marca::usarParaPruebas(null);
        $x = Html::xpath(Pantallas::render($this->db, "home"));
        $this->assertStringContainsString((string)$this->real["nombre"], trim($x->query("//title")->item(0)->textContent));
        $pie = $x->query("//footer")->item(0)->textContent;
        foreach (["telefono", "correo", "direccion"] as $k) $this->assertStringContainsString((string)$this->real[$k], $pie, $k);
    }
    #[DataProvider("deLaTienda")]
    public function testElBloqueDeMarcaSeparaNombreYLemaYUsaElLogoSvg(string $pantalla): void {
        $x = Html::xpath(Pantallas::render($this->db, $pantalla));
        $a = $x->query("//nav//a[contains(@class,'marca')]")->item(0);
        $q = new \DOMXPath($a->ownerDocument);
        $this->assertSame("Taller Cambiado Zeta", trim($q->query(".//span[contains(@class,'marca-nombre')]", $a)->item(0)->textContent), $pantalla);
        $this->assertSame("Lema Cambiado", trim($q->query(".//span[contains(@class,'marca-lema')]", $a)->item(0)->textContent), $pantalla);
        $this->assertSame(1, $q->query(".//svg[contains(@class,'marca-logo')]", $a)->length, "$pantalla: el logo es el SVG de un color");
    }

    public function testElAccesoYElPanelTambienLlevanElLogoDeLaMarca(): void {
        foreach (["login", "registro", "olvide", "restablecer_valido", "admin_dashboard"] as $n) {
            $x = Html::xpath(Pantallas::render($this->db, $n));
            $this->assertSame(1, $x->query("//svg[contains(@class,'marca-logo')]")->length, "$n: logo de la marca");
        }
    }
}
