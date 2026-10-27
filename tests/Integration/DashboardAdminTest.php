<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/** Dashboard del Administrador: contenido, rango, estados vacíos y widgets por permiso. */
final class DashboardAdminTest extends BaseDatos {
    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
    }

    private function pantalla(?string $r = null): \DOMXPath {
        return Html::xpath(Pantallas::render($this->db, $r === null ? "admin_dashboard" : "admin_dashboard_r" . $r));
    }

    private function datos(\DOMXPath $x): array {
        $n = $x->query('//script[@id="dash-datos"]')->item(0);
        $this->assertNotNull($n, "falta el bloque de datos de las gráficas");
        $j = json_decode($n->textContent, true);
        $this->assertIsArray($j, "el bloque de datos no es JSON válido");
        return $j;
    }

    private function ventaDeHoy(string $categoria, string $marca, string $pieza, float $precio, int $cant): void {
        $this->db->prepare("INSERT INTO secciones (nombre) VALUES (?)")->execute([$categoria]);
        $sec = (int)$this->db->lastInsertId();
        $this->db->prepare("INSERT INTO autos (marca, modelo, anio) VALUES (?, 'M', 2020)")->execute([$marca]);
        $auto = (int)$this->db->lastInsertId();
        $this->db->prepare("INSERT INTO partes (nombre, auto_id, seccion_id, precio, cantidad) VALUES (?, ?, ?, ?, 3)")->execute([$pieza, $auto, $sec, $precio]);
        $parte = (int)$this->db->lastInsertId();
        $this->db->prepare("INSERT INTO pedidos (cliente_nombre, cliente_telefono, cliente_email, cliente_direccion, estado, subtotal, total, creado_en)
            VALUES ('C', '1', 'c@example.invalid', 'd', 'entregado', ?, ?, NOW())")->execute([$precio * $cant, $precio * $cant * 1.07]);
        $ped = (int)$this->db->lastInsertId();
        $this->db->prepare("INSERT INTO pedido_items (pedido_id, parte_id, parte_nombre, precio_unitario, cantidad) VALUES (?, ?, ?, ?, ?)")->execute([$ped, $parte, $pieza, $precio, $cant]);
    }

    public function testNoRepiteElTituloYLasAccionesVanJuntoAlSubtitulo(): void {
        $x = $this->pantalla();
        $this->assertStringNotContainsString("Panel de Control", $x->document->textContent);
        $this->assertSame(1, Html::contar($x, "//h1"), "un solo h1 (oculto): el título visible es el del encabezado");
        $this->assertSame(1, Html::contar($x, '//h1[contains(@class,"visually-hidden")]'));
        $this->assertGreaterThan(0, Html::contar($x, '//*[contains(@class,"dash-cabeza")]//*[contains(@class,"dash-sub")]'));
        $this->assertSame(1, Html::contar($x, '//*[contains(@class,"dash-acciones")]//a[contains(@href,"mod=ventas") and contains(@href,"action=crear")]'));
        $this->assertSame(1, Html::contar($x, '//*[contains(@class,"dash-acciones")]//a[contains(@href,"mod=partes") and contains(@href,"action=crear")]'));
        $this->assertSame(0, Html::contar($x, '//*[contains(@class,"quick-link")]'), "las acciones rápidas ya no son una columna");
        $this->assertSame(0, Html::contar($x, '//*[contains(text(),"Bitácora")]'), "la bitácora salió del dashboard");
    }

    public function testElRangoPorDefectoSaleDeMarcaYUnValorRaroLoUsa(): void {
        $esperado = (int)\Marca::get("dashboard.rango_defecto", 365);
        foreach ([null, "999"] as $r) {
            $x = $this->pantalla($r);
            $activo = $x->query('//*[contains(@class,"dash-rango-op") and @aria-current="true"]');
            $this->assertSame(1, $activo->length, "un solo rango activo con r=" . var_export($r, true));
            $this->assertStringContainsString("r=$esperado", $activo->item(0)->getAttribute("href"));
        }
        $this->assertSame(5, Html::contar($this->pantalla(), '//*[contains(@class,"dash-rango-op")]'));
        $x = $this->pantalla("30");
        $this->assertStringContainsString("r=30", $x->query('//*[@aria-current="true" and contains(@class,"dash-rango-op")]')->item(0)->getAttribute("href"));
    }

    public function testCambiarLaClaveDeMarcaCambiaElRangoPorDefecto(): void {
        \Marca::usarParaPruebas(["dashboard" => ["rango_defecto" => 30]]);
        try {
            $x = $this->pantalla();
            $activo = $x->query('//*[contains(@class,"dash-rango-op") and @aria-current="true"]');
            $this->assertSame(1, $activo->length);
            $this->assertStringContainsString("r=30", $activo->item(0)->getAttribute("href"));
        } finally {
            \Marca::usarParaPruebas(null);
        }
    }

    public function testSinVentasMuestraEstadosVaciosYNoLienzos(): void {
        $x = $this->pantalla("7");
        foreach (["dgLinea", "dgDona", "dgPartes", "dgMarcas"] as $id) $this->assertSame(0, Html::contar($x, "//canvas[@id='$id']"), "$id sin ventas");
        $this->assertGreaterThanOrEqual(3, Html::contar($x, '//*[contains(@class,"dash-vacio")]'));
        $this->assertStringContainsString("Sin ventas en este periodo", $x->document->textContent);
    }

    public function testConVentasSalenLasGraficasConTablaAccesibleYDatosDeLaBase(): void {
        $this->ventaDeHoy("Cat Dash Uno", "Marca Dash", "Pieza Dash Uno", 40.0, 3);
        $x = $this->pantalla("30");
        foreach (["dgLinea", "dgDona", "dgPartes", "dgMarcas"] as $id) {
            $c = $x->query("//canvas[@id='$id']");
            $this->assertSame(1, $c->length, "falta el lienzo $id");
            $this->assertSame("img", $c->item(0)->getAttribute("role"));
            $this->assertNotSame("", $c->item(0)->getAttribute("aria-label"), "$id sin texto alternativo");
        }
        $this->assertGreaterThanOrEqual(3, Html::contar($x, '//table[contains(@class,"visually-hidden")]'), "alternativa en tabla");
        $d = $this->datos($x);
        $this->assertSame("Cat Dash Uno", $d["categorias"][0]["nombre"]);
        $this->assertEqualsWithDelta(120.0, $d["categorias"][0]["total"], 0.001);
        $this->assertSame("Pieza Dash Uno", $d["partes"][0]["nombre"]);
        $this->assertSame("Marca Dash", $d["marcas"][0]["nombre"]);
        $this->assertEqualsWithDelta(120.0, array_sum($d["serie"]["actual"]), 0.001);
        $this->assertStringContainsString('data-contar="120"', $x->document->saveHTML());
    }

    public function testElPeriodoAnteriorYLaVariacionSeCalculanDelPeriodoDeIgualDuracion(): void {
        $this->ventaDeHoy("Cat Var", "Marca Var", "Pieza Var", 10.0, 1);
        $this->db->exec("INSERT INTO pedidos (cliente_nombre, cliente_telefono, cliente_email, cliente_direccion, estado, subtotal, total, creado_en)
            VALUES ('C', '1', 'c@example.invalid', 'd', 'entregado', 5, 5.35, DATE_SUB(NOW(), INTERVAL 10 DAY))");
        $x = $this->pantalla("7");
        $this->assertGreaterThan(0, Html::contar($x, '//*[contains(@class,"dash-var") and contains(@class,"sube")]'), "10 contra 0 en el periodo anterior no tiene base; el de 30 días sí");
    }

    public function testLosWidgetsSalenDeLosPermisosNoDelNombreDelRol(): void {
        $this->assertSame(["ventas" => true, "pedidos" => true, "partes" => true, "comentarios" => true], \DashboardController::widgets(["ventas", "pedidos", "partes", "comentarios", "usuarios"]));
        $this->assertSame(["ventas" => true, "pedidos" => false, "partes" => true, "comentarios" => true], \DashboardController::widgets(["ventas", "partes", "comentarios"]));
        $this->assertSame(["ventas" => false, "pedidos" => false, "partes" => false, "comentarios" => false], \DashboardController::widgets([]));
    }

    public function testElTemaDeLasGraficasNoLlevaColoresEscritosEnElJs(): void {
        $js = (string)file_get_contents(BASE_PATH . "/assets/js/dashboard.js");
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,6}\b/', $js, "los colores salen de los tokens de tema.css");
        $this->assertStringContainsString("--chart-otras", $js);
        $css = (string)file_get_contents(BASE_PATH . "/assets/css/dashboard.css");
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,6}\b/', $css, "dashboard.css solo usa tokens");
    }

    public function testElRangoTodoNoCompara(): void {
        $x = $this->pantalla("todo");
        $this->assertSame(5, Html::contar($x, '//*[contains(@class,"dash-rango-op")]'));
        $this->assertStringContainsString("r=todo", $x->query('//*[contains(@class,"dash-rango-op") and @aria-current="true"]')->item(0)->getAttribute("href"));
        $this->assertSame(0, Html::contar($x, '//*[contains(@class,"dash-var")]'), "sin variación");
        $this->assertSame(0, Html::contar($x, '//*[contains(@class,"punteado")]'), "sin leyenda del periodo anterior");
        $this->assertStringContainsString("sin comparar", $x->document->textContent);
        $this->assertSame([], $this->datos($x)["serie"]["anterior"]);
    }

    public function testLosAvisosSonAccionesSeparadasConIconoNumeroYBoton(): void {
        $x = $this->pantalla();
        $items = $x->query('//ul[contains(@class,"dash-avisos")]/li');
        $this->assertGreaterThanOrEqual(2, $items->length);
        foreach ($items as $li) {
            $q = new \DOMXPath($li->ownerDocument);
            $this->assertSame(1, $q->query('.//i[@aria-hidden="true" and contains(@class,"dash-aviso-icono")]', $li)->length, "icono decorativo");
            $this->assertGreaterThan(0, $q->query('.//b[contains(@class,"num")]', $li)->length, "número en negrita");
        }
        $this->assertStringContainsString("pedidos por atender", $x->document->textContent);
        $this->assertSame(1, Html::contar($x, '//li[contains(@class,"dash-aviso-err")]'), "con agotadas, el aviso de stock es de error");
        $this->assertMatchesRegularExpression('/\d+ agotadas?/', preg_replace('/\s+/', ' ', $x->query('//li[contains(@class,"dash-aviso-err")]')->item(0)->textContent), "dice cuántas agotadas, no solo color");
        foreach (["Atender pedidos", "Reponer"] as $b) {
            $this->assertSame(1, Html::contar($x, '//ul[contains(@class,"dash-avisos")]//a[contains(@class,"btn") and normalize-space()="' . $b . '"]'), $b);
        }
        $css = (string)file_get_contents(BASE_PATH . "/assets/css/dashboard.css");
        $this->assertDoesNotMatchRegularExpression('/.dash-aviso[^{]*{[^}]*border-left/', $css, "sin la línea de color al borde izquierdo");
    }

    public function testSinPendientesNiStockBajoSaleTodoAlDiaSinBoton(): void {
        $this->pantalla(); // crea los datos de pantalla antes de ajustarlos
        $this->db->exec("UPDATE pedidos SET estado='entregado'");
        $this->db->exec("UPDATE partes SET cantidad=50");
        $this->db->exec("DELETE FROM comentarios");
        $x = $this->pantalla();
        $items = $x->query('//ul[contains(@class,"dash-avisos")]/li');
        $this->assertSame(1, $items->length);
        $this->assertStringContainsString("Todo al día", $items->item(0)->textContent);
        $this->assertStringContainsString("dash-aviso-ok", $items->item(0)->getAttribute("class"));
        $this->assertSame(0, Html::contar($x, '//ul[contains(@class,"dash-avisos")]//a'), "sin botón");
        $this->assertGreaterThan(0, Html::contar($x, '//li[contains(@class,"dash-aviso-ok")]//i[contains(@class,"bi-check-circle")]'));
    }

    public function testLosColoresDeLosAvisosCumplenAAEnLosDosModos(): void {
        $css = (string)file_get_contents(BASE_PATH . "/assets/css/tema.css");
        foreach (["dark", "light"] as $modo) {
            $v = \TemaGenerador::leerVariables($css, $modo);
            $this->assertGreaterThanOrEqual(4.5, \TemaGenerador::contraste($v["text"], $v["surface"]), "texto del aviso en $modo");
            foreach (["warning-text", "danger-text", "success-text", "alert-info-border", "text-muted"] as $t) {
                $this->assertGreaterThanOrEqual(3.0, \TemaGenerador::contraste($v[$t], $v["surface"]), "icono $t sobre la tarjeta en $modo");
            }
            $this->assertGreaterThanOrEqual(4.5, \TemaGenerador::contraste($v["text-muted"], $v["surface"]), "texto secundario del comentario en $modo");
        }
    }

    public function testElTextoDelComentarioEsSecundarioYMasPequenoQueElNombre(): void {
        $css = (string)file_get_contents(BASE_PATH . "/assets/css/lista.css");
        preg_match('/\.comentario-texto \{([^}]*)\}/', $css, $m);
        $this->assertStringContainsString("color: var(--text-muted)", $m[1]);
        $this->assertStringContainsString("font-size: var(--type-body-sm-size)", $m[1]);
        $this->assertMatchesRegularExpression('/\.lista-comentarios th\.col-acciones[^{]*\{[^}]*text-align: center/', $css, "Acciones centrado bajo su cabecera");
        $this->assertStringContainsString("vertical-align: middle", $css);
        $this->assertStringContainsString('class="lista-tabla lista-comentarios"', (string)file_get_contents(BASE_PATH . "/app/Views/admin/comentarios/index.php"));
    }
}
