<?php
namespace Tests\Integration;

use Tests\Support\BaseDatos;
use Tests\Support\Html;
use Tests\Support\Pantallas;

/** S6c.2: «Comentarios recientes» del Dashboard como tarjetas de reseña compactas. */
final class DashboardResenasTest extends BaseDatos {
    protected function setUp(): void {
        parent::setUp();
        Pantallas::reiniciar();
    }

    private function pantalla(): \DOMXPath { return Html::xpath(Pantallas::render($this->db, "admin_dashboard")); }

    private function resena(int $parte, string $autor, int $rating, string $fecha): void {
        $this->db->prepare("INSERT INTO comentarios (parte_id, nombre_visitante, comentario, publicado, rating, creado_en) VALUES (?,?,?,1,?,?)")
            ->execute([$parte, $autor, "Texto de reseña de $autor", $rating, $fecha]);
    }

    public function testCadaResenaTraeAvatarNombreEstrellasPiezaFechaYTexto(): void {
        $this->pantalla();
        $parte = (int)$this->db->query("SELECT id FROM partes LIMIT 1")->fetchColumn();
        $this->db->exec("DELETE FROM comentarios");
        $this->resena($parte, "zoe", 3, date("Y-m-d H:i:s", time() - 300));
        $x = $this->pantalla();
        $li = '//section[@aria-labelledby="dgCom"]//li[contains(@class,"dash-resena")]';
        $this->assertSame(1, Html::contar($x, $li));
        $this->assertSame("Z", trim($x->query("$li/span[contains(@class,'dash-avatar')]")->item(0)->textContent), "inicial en mayúscula");
        $this->assertSame(1, Html::contar($x, "$li//span[contains(@class,'estrellas')][@role='img'][@aria-label='3 de 5 estrellas']"), "texto accesible");
        $this->assertSame(3, Html::contar($x, "$li//i[contains(@class,'bi-star-fill')]"));
        $this->assertSame(2, Html::contar($x, "$li//i[contains(@class,'bi-star')][not(contains(@class,'bi-star-fill'))]"));
        $this->assertStringContainsString("hace 5 min", $x->query("$li//time")->item(0)->textContent);
        $this->assertSame(1, Html::contar($x, "$li//p[contains(@class,'comentario-texto')]"), "texto con tope de 2 líneas");
        $this->assertSame(1, Html::contar($x, '//section[@aria-labelledby="dgCom"]//a[contains(@class,"dash-ver")][normalize-space(.)="Ver todos"]'));
    }

    public function testMuestraSoloLasUltimasCinco(): void {
        $this->pantalla();
        $parte = (int)$this->db->query("SELECT id FROM partes LIMIT 1")->fetchColumn();
        $this->db->exec("DELETE FROM comentarios");
        for ($i = 1; $i <= 7; $i++) $this->resena($parte, "Autor$i", 5, date("Y-m-d H:i:s", time() - $i * 3600));
        $x = $this->pantalla();
        $nombres = [];
        foreach ($x->query('//li[contains(@class,"dash-resena")]//b[contains(@class,"dash-resena-nombre")]') as $n) $nombres[] = $n->textContent;
        $this->assertSame(["Autor1", "Autor2", "Autor3", "Autor4", "Autor5"], $nombres);
    }

    public function testSinComentariosMuestraEstadoVacioCuidado(): void {
        $this->pantalla();
        $this->db->exec("DELETE FROM comentarios");
        $x = $this->pantalla();
        $this->assertSame(0, Html::contar($x, '//li[contains(@class,"dash-resena")]'));
        $this->assertStringContainsString("Aún no hay comentarios", $x->query('//section[@aria-labelledby="dgCom"]//p[contains(@class,"vacio-titulo")]')->item(0)->textContent);
    }

    public function testElTextoDeLaResenaSeEscapa(): void {
        $this->pantalla();
        $parte = (int)$this->db->query("SELECT id FROM partes LIMIT 1")->fetchColumn();
        $this->db->exec("DELETE FROM comentarios");
        $this->db->prepare("INSERT INTO comentarios (parte_id, nombre_visitante, comentario, publicado, rating, creado_en) VALUES (?,?,?,1,4,NOW())")->execute([$parte, "<b>x</b>", "<script>alert(1)</script>"]);
        $html = Pantallas::render($this->db, "admin_dashboard");
        $this->assertStringNotContainsString("<script>alert(1)</script>", $html);
        $this->assertStringContainsString("&lt;script&gt;", $html);
    }
}
