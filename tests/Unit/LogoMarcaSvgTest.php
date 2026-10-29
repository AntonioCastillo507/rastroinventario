<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Saneador de SVG con el perfil «marca»: solo formas y trazos permitidos, nada activo ni externo */
final class LogoMarcaSvgTest extends TestCase {
    private function svg(string $dentro, string $raizAttrs = ""): string {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 50" ' . $raizAttrs . '>' . $dentro . '</svg>';
    }

    public function testQuitaScriptsEventosEnlacesYEstilos(): void {
        $in = $this->svg(
            '<script>alert(1)</script><style>path{fill:red}</style>'
            . '<path d="M0 0h10v10z" onclick="alert(1)" style="fill:red" fill="url(#g)"/>'
            . '<a xlink:href="javascript:alert(1)" xmlns:xlink="http://www.w3.org/1999/xlink"><path d="M1 1h2v2z"/></a>'
            . '<use href="http://evil.test/x.svg#a"/><image href="http://evil.test/i.png"/>'
            . '<foreignObject><div xmlns="http://www.w3.org/1999/xhtml">x</div></foreignObject>'
            . '<g onload="x()"><circle cx="5" cy="5" r="3" onmouseover="x()"/></g>',
            'onload="alert(1)"'
        );
        $out = \Logo::saneaMarca($in);
        $this->assertNotNull($out);
        foreach (["script", "style", "onclick", "onload", "onmouseover", "href", "javascript", "evil.test", "<use", "<image", "foreignObject", "<a ", "url(", "<div"] as $malo) {
            $this->assertStringNotContainsString($malo, $out, $malo);
        }
        $this->assertStringContainsString("<path", $out);
        $this->assertStringContainsString("<circle", $out);
    }

    public function testRechazaDoctypeYEntidades(): void {
        $this->assertNull(\Logo::saneaMarca('<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]>' . $this->svg('<path d="M0 0h1v1z"/>')));
        $this->assertNull(\Logo::saneaMarca('<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">' . $this->svg('<path d="M0 0h1v1z"/>')));
        $this->assertNull(\Logo::saneaMarca('<!ENTITY a "b">' . $this->svg('<path d="M0 0h1v1z"/>')));
    }

    public function testRechazaLoQueNoEsUnSvgUtilizable(): void {
        $this->assertNull(\Logo::saneaMarca(""));
        $this->assertNull(\Logo::saneaMarca("no es xml"));
        $this->assertNull(\Logo::saneaMarca("<svg><path d='M0 0'/>"), "XML roto");
        $this->assertNull(\Logo::saneaMarca('<html xmlns="http://www.w3.org/1999/xhtml"><body/></html>'));
        $this->assertNull(\Logo::saneaMarca('<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0h1v1z"/></svg>'), "sin viewBox");
        $this->assertNull(\Logo::saneaMarca('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"/>'), "sin formas");
        $this->assertNull(\Logo::saneaMarca('<svg xmlns="http://www.w3.org/2000/svg" viewBox="a b c d"><path d="M0 0h1v1z"/></svg>'));
        $this->assertNull(\Logo::saneaMarca($this->svg('<script>x</script>')), "solo había un script");
    }

    public function testLimiteDe100KbSeAplicaAntesDeParsear(): void {
        $dentro = '<path d="M0 0h1v1z"/>';
        $relleno = 102400 - strlen($this->svg($dentro . '<!---->'));
        $justo = $this->svg($dentro . '<!--' . str_repeat("x", $relleno) . '-->');
        $this->assertSame(102400, strlen($justo));
        $this->assertNotNull(\Logo::saneaMarca($justo), "exactamente 100 KB entra");
        $this->assertNull(\Logo::saneaMarca($justo . " "), "un byte más no");
        $this->assertNull(\Logo::sanear($this->svg($dentro . '<!--' . str_repeat("x", 11000) . '-->')), "el perfil de la tienda sigue con 10 KB");
    }

    public function testConservaTrazosYTransformValidos(): void {
        $in = $this->svg('<g transform="translate(5 5) rotate(10)"><line x1="0" y1="0" x2="9" y2="9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="bevel"/>'
            . '<ellipse cx="5" cy="5" rx="4" ry="2" fill="none" stroke="currentColor"/><polyline points="0,0 5,5 9,0" fill="none" stroke="currentColor"/></g>');
        $out = \Logo::saneaMarca($in);
        $this->assertNotNull($out);
        foreach (['transform="translate(5 5) rotate(10)"', "<line", 'stroke-width="2"', 'stroke-linecap="round"', "<ellipse", "<polyline", 'stroke="currentColor"'] as $ok) {
            $this->assertStringContainsString($ok, $out, $ok);
        }
    }

    public function testDescartaTransformYTrazosInvalidos(): void {
        $out = \Logo::saneaMarca($this->svg('<path d="M0 0h1v1z" transform="translate(1) url(javascript:x)" stroke="red" stroke-linecap="evil" fill="#fff"/>'));
        $this->assertNotNull($out);
        foreach (["transform", "javascript", "stroke=", "stroke-linecap", "#fff"] as $malo) {
            $this->assertStringNotContainsString($malo, $out, $malo);
        }
    }

    public function testDescartaTrazadosConCaracteresRaros(): void {
        $this->assertNull(\Logo::saneaMarca($this->svg('<path d="M0 0 <script>"/><path d="M1 1h2v2z"/>')), "XML inválido");
        $out = \Logo::saneaMarca($this->svg('<path d="M0 0 alert(1)"/><path d="M1 1h2v2z"/>'));
        $this->assertNotNull($out);
        $this->assertStringNotContainsString("alert", $out);
    }

    public function testElPerfilDeLaTiendaSigueEstricto(): void {
        $in = $this->svg('<ellipse cx="5" cy="5" rx="2" ry="2"/><path d="M0 0h1v1z" stroke="currentColor" transform="translate(1 1)"/>');
        $tienda = \Logo::sanear($in);
        $this->assertNotNull($tienda);
        $this->assertStringNotContainsString("ellipse", $tienda);
        $this->assertStringNotContainsString("stroke", $tienda);
        $this->assertStringNotContainsString("transform", $tienda);
        $this->assertStringContainsString("ellipse", (string)\Logo::saneaMarca($in));
    }

    public function testUnaEntidadNoDeclaradaNoLeeNingunArchivo(): void {
        $this->assertNull(\Logo::saneaMarca($this->svg('<path d="&xxe;"/>')));
    }
}
