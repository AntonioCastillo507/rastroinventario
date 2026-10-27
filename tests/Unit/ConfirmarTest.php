<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Confirmar:: arma los atributos del modal de confirmación; todo lo que devuelve va escapado para HTML. */
final class ConfirmarTest extends TestCase {
    private function valor(string $attrs, string $nombre): string {
        $this->assertSame(1, preg_match('/ ' . preg_quote($nombre, "/") . '="([^"]*)"/', $attrs, $m), "falta $nombre");
        return html_entity_decode($m[1], ENT_QUOTES, "UTF-8");
    }

    public function testAttrsDevuelveJsonValidoConLosCuatroDatos(): void {
        $a = \Confirmar::attrs(["titulo" => "Desactivar «Faro»", "texto" => "Deja de verse.", "boton" => "Desactivar", "cancelar" => "Conservar", "peligro" => true]);
        $d = json_decode($this->valor($a, "data-confirmar"), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(["titulo" => "Desactivar «Faro»", "texto" => "Deja de verse.", "boton" => "Desactivar", "cancelar" => "Conservar", "peligro" => true], $d, "el texto en español sale tal cual, sin \u00ab");
        $this->assertStringStartsWith(' data-confirmar="', $a);
    }

    public function testElNombreDelObjetoNoPuedeCerrarElAtributoNiMeterEtiquetas(): void {
        $malo = '"><script>alert(1)</script> \' onfocus=\'x';
        $a = \Confirmar::attrs(["titulo" => "Eliminar «{$malo}»", "texto" => "t", "boton" => "b"]);
        $this->assertStringNotContainsString("<script", $a);
        $this->assertSame(0, preg_match('/[<>]/', $a), "sin ángulos");
        $this->assertSame(0, substr_count($a, '"') - 2, "solo las dos comillas del atributo: " . $a);
        $this->assertSame(0, substr_count($a, "'"), "sin comillas simples sueltas");
        $this->assertSame("Eliminar «{$malo}»", json_decode($this->valor($a, "data-confirmar"), true)["titulo"], "al decodificar vuelve íntegro");
    }

    public function testPorCampoLlevaElNombreDelCampoYElMapa(): void {
        $a = \Confirmar::attrsPorCampo("estado", ["entregado" => ["titulo" => "X", "texto" => "Y", "boton" => "Z"]]);
        $this->assertSame("estado", $this->valor($a, "data-confirmar-campo"));
        $this->assertSame(["entregado" => ["titulo" => "X", "texto" => "Y", "boton" => "Z"]], json_decode($this->valor($a, "data-confirmar"), true));
        $this->assertStringContainsString('data-confirmar-campo="a&quot;b"', \Confirmar::attrsPorCampo('a"b', []), "el nombre del campo también se escapa");
    }
}
