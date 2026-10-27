<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/** Pantalla Seguridad (bitácora de accesos): lista paginada, permiso propio y sin la bitácora en el dashboard. */
final class SeguridadTest extends BaseDatos {
    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
    }

    /** 45 accesos, todos en el mismo segundo (sin desempate se repetirían o saltarían filas) */
    private function accesos(int $n = 45): void {
        $s = $this->db->prepare("INSERT INTO login_logs (usuario_id, email_intento, ip, exitoso, fecha) VALUES (NULL, ?, '203.0.113.9', ?, '2031-01-01 10:00:00')");
        for ($i = 1; $i <= $n; $i++) $s->execute(["acceso$i@example.invalid", $i % 3 ? 1 : 0]);
    }

    public function testListaPaginadaConLaCabeceraYElComponenteDeLista(): void {
        $this->accesos();
        $x = Html::xpath(Pantallas::render($this->db, "admin_seguridad"));
        $this->assertSame(1, Html::contar($x, "//h1"));
        $this->assertSame(20, Html::contar($x, '//table[contains(@class,"lista-tabla")]/tbody/tr'), "20 filas por página");
        $this->assertGreaterThan(0, Html::contar($x, '//nav[@aria-label="Paginación"]'));
        $this->assertStringContainsString("Mostrando 1–20 de", $x->document->textContent);
        $this->assertStringContainsString("Correcto", $x->document->textContent);
        $this->assertStringContainsString("Fallido", $x->document->textContent, "el resultado lleva texto, no solo color");
    }

    public function testLaSegundaPaginaTrae21aLas40YNoRepiteFilas(): void {
        $this->accesos();
        $lm = new \LogModel();
        $total = $lm->contar();
        $ids = [];
        for ($p = 1; $p <= (int)ceil($total / 20); $p++) {
            foreach ($lm->pagina(\Paginador::crear((string)$p, $total, 20)) as $f) $ids[] = (int)$f["id"];
        }
        $this->assertCount($total, $ids);
        $this->assertCount($total, array_unique($ids), "ninguna fila repetida entre páginas");
        $esperado = array_map(fn($r) => (int)$r["id"], $this->db->query("SELECT id FROM login_logs ORDER BY fecha DESC, id DESC")->fetchAll());
        $this->assertSame($esperado, $ids, "mismo orden que sin paginar");
        $x = Html::xpath(Pantallas::render($this->db, "admin_seguridad_p2"));
        $this->assertStringContainsString("Mostrando 21–40 de", $x->document->textContent);
    }

    public function testSinAccesosMuestraElEstadoVacio(): void {
        $this->db->exec("DELETE FROM login_logs");
        $x = Html::xpath(Pantallas::render($this->db, "admin_seguridad"));
        $this->assertStringContainsString("Aún no hay accesos registrados", $x->document->textContent);
    }

    public function testEscapaElCorreoIntentado(): void {
        $this->db->exec("INSERT INTO login_logs (email_intento, ip, exitoso) VALUES ('<script>alert(1)</script>@x.invalid', '203.0.113.9', 0)");
        $html = Pantallas::render($this->db, "admin_seguridad");
        $this->assertStringNotContainsString("<script>alert(1)</script>", $html);
        $this->assertStringContainsString("&lt;script&gt;alert(1)&lt;/script&gt;", $html);
    }

    public function testSoloElAdministradorTieneElPermisoYApareceEnElMenu(): void {
        $q = $this->db->query("SELECT r.nombre, MAX(p.puede_ver) v FROM roles r LEFT JOIN permisos p ON p.rol_id = r.id AND p.modulo = 'seguridad' GROUP BY r.id, r.nombre");
        $por = array_column($q->fetchAll(), "v", "nombre");
        $this->assertSame(1, (int)$por["Administrador"]);
        foreach (["Vendedor", "Consultor", "Cliente"] as $rol) $this->assertSame(0, (int)$por[$rol], "$rol no debe ver Seguridad");
        $x = Html::xpath(Pantallas::render($this->db, "admin_dashboard"));
        $this->assertSame(1, Html::contar($x, '//aside//a[contains(@href,"mod=seguridad")]'), "entrada en el menú lateral");
        $this->assertSame(0, Html::contar($x, '//*[contains(text(),"Bitácora de Accesos")]'), "la bitácora ya no está en el dashboard");
    }

    public function testLaMigracionEsIdempotente(): void {
        $sql = (string)file_get_contents(BASE_PATH . "/database/migraciones/002_permiso_seguridad.sql");
        $antes = (int)$this->db->query("SELECT COUNT(*) FROM permisos WHERE modulo = 'seguridad'")->fetchColumn();
        $this->db->exec("DELETE FROM permisos WHERE modulo = 'seguridad'");
        foreach ([1, 2] as $_) $this->db->exec(preg_replace('/^--.*$/m', "", str_replace("SET NAMES utf8mb4;", "", $sql)));
        $this->assertSame($antes, (int)$this->db->query("SELECT COUNT(*) FROM permisos WHERE modulo = 'seguridad'")->fetchColumn(), "dos corridas dejan una sola fila");
    }

    public function testElControladorExigeElPermisoDeSeguridadYLaRutaEstaDeclarada(): void {
        $c = (string)file_get_contents(BASE_PATH . "/app/Controllers/SeguridadController.php");
        $this->assertStringContainsString('requirePermission("seguridad")', $c);
        $this->assertStringContainsString('"seguridad"   => SeguridadController::class', (string)file_get_contents(BASE_PATH . "/admin/index.php"));
    }
}
