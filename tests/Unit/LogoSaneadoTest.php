<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Logo::sanear reconstruye el SVG con una lista blanca: lo que no está en ella no llega a la página.
 * Es la medida de seguridad de imprimir un SVG en línea (un SVG puede llevar scripts, enlaces y estilos).
 */
final class LogoSaneadoTest extends TestCase {
    private const BUENO = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><path fill="currentColor" fill-rule="evenodd" d="M1 1L9 1L9 9Z"/></svg>';

    private function sanear(string $svg): ?string {
        return \Logo::sanear($svg);
    }

    public function testUnSvgBuenoSaleConLoPuestoPorElSistema(): void {
        $s = $this->sanear(self::BUENO);
        $this->assertNotNull($s);
        $this->assertStringContainsString('viewBox="0 0 10 10"', $s);
        $this->assertStringContainsString('d="M1 1L9 1L9 9Z"', $s);
        $this->assertStringContainsString('fill="currentColor"', $s);
        $this->assertStringContainsString('class="marca-logo"', $s);
        $this->assertStringContainsString('aria-hidden="true"', $s);
        $this->assertStringContainsString('focusable="false"', $s);
    }

    public static function peligrosos(): array {
        $c = fn(string $dentro, string $attrs = "") => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"' . $attrs . '>' . $dentro . '<path d="M1 1L9 1L9 9Z"/></svg>';
        return [
            "script" => [$c('<script>alert(1)</script>'), "script"],
            "atributo onload en svg" => [$c('', ' onload="alert(1)"'), "onload"],
            "onclick en path" => ['<svg viewBox="0 0 10 10"><path onclick="alert(1)" d="M1 1L9 1Z"/></svg>', "onclick"],
            "onmouseover en g" => [$c('<g onmouseover="x()"><path d="M1 1L2 2Z"/></g>'), "onmouseover"],
            "foreignObject" => [$c('<foreignObject><div>hola</div></foreignObject>'), "foreignObject"],
            "image remota" => [$c('<image href="https://malo.example/x.png"/>'), "image"],
            "use con href" => [$c('<use href="#a"/>'), "<use"],
            "enlace a" => [$c('<a href="javascript:alert(1)"><path d="M1 1Z"/></a>'), "javascript"],
            "style" => [$c('<style>path{fill:red}</style>'), "<style"],
            "atributo style" => ['<svg viewBox="0 0 10 10"><path style="fill:red" d="M1 1L9 1Z"/></svg>', "style="],
            "texto" => [$c('<text x="1" y="1">Hola</text>'), "<text"],
            "color propio" => ['<svg viewBox="0 0 10 10"><path fill="#ff0000" d="M1 1L9 1L9 9Z"/></svg>', "ff0000"],
            "xlink:href" => ['<svg xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 10 10"><path xlink:href="javascript:x()" d="M1 1L9 1Z"/></svg>', "xlink"],
            "path con script en d" => ['<svg viewBox="0 0 10 10"><path d="M1 1L9 1&quot;onload=&quot;x()Z"/></svg>', "onload"],
            "set y animate" => [$c('<animate attributeName="d" values="x"/><set attributeName="onload" to="x()"/>'), "animate"],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("peligrosos")]
    public function testLoPeligrosoNoLlegaALaSalida(string $svg, string $prohibido): void {
        $s = $this->sanear($svg);
        if ($s === null) { $this->addToAssertionCount(1); return; } // rechazado entero también vale
        $this->assertStringNotContainsStringIgnoringCase($prohibido, $s);
        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $s);
        $this->assertDoesNotMatchRegularExpression('/javascript:|<script|<style|<image|<use|<foreignObject|<animate|<set\b|<a\b|<text/i', $s);
    }

    public static function inservibles(): array {
        return [
            "vacío" => [""],
            "no es xml" => ["esto no es un svg"],
            "otro tipo de raíz" => ['<html><body><path d="M1 1Z"/></body></html>'],
            "sin viewBox" => ['<svg xmlns="http://www.w3.org/2000/svg"><path d="M1 1L9 1L9 9Z"/></svg>'],
            "viewBox raro" => ['<svg viewBox="uno dos tres cuatro"><path d="M1 1Z"/></svg>'],
            "sin formas" => ['<svg viewBox="0 0 10 10"><g/></svg>'],
            "con DOCTYPE" => ['<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd"><svg viewBox="0 0 10 10"><path d="M1 1L9 1Z"/></svg>'],
            "entidad externa" => ['<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg viewBox="0 0 10 10"><path d="M1 1Z"/><text>&x;</text></svg>'],
            "billion laughs" => ['<?xml version="1.0"?><!DOCTYPE s [<!ENTITY a "aaaa"><!ENTITY b "&a;&a;&a;&a;">]><svg viewBox="0 0 10 10"><path d="M1 1Z"/><text>&b;</text></svg>'],
            "enorme" => ['<svg viewBox="0 0 10 10"><path d="M1 1L' . str_repeat("2 2L", 4000) . '9 9Z"/></svg>'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("inservibles")]
    public function testLoQueNoSirveSeRechazaYLaVistaUsaElPng(string $svg): void {
        $this->assertNull($this->sanear($svg));
    }

    public function testUnAtributoDMalFormadoSeDescartaPeroLoDemasSeConserva(): void {
        $s = $this->sanear('<svg viewBox="0 0 10 10"><path d="M1 1L9 1;alert(1)Z"/><circle cx="5" cy="5" r="2" fill="currentColor"/></svg>');
        $this->assertNotNull($s);
        $this->assertStringNotContainsString("alert", $s);
        $this->assertStringContainsString("<circle", $s);
    }

    public function testElLogoRealSeImprimeSaneadoYSinElAtributoDeOrigen(): void {
        \Marca::usarParaPruebas(["logo" => "assets/img/logo.png"]);
        try {
            $s = \Logo::enLinea();
        } finally {
            \Marca::usarParaPruebas(null);
        }
        $this->assertStringStartsWith("<svg", $s);
        $this->assertStringNotContainsString("data-origen", $s, "el atributo de la prueba de fidelidad no llega a la página");
        $this->assertStringContainsString('fill="currentColor"', $s);
        $this->assertStringContainsString('class="marca-logo"', $s);
    }

    public function testSinSvgOSinLogoDevuelveVacioParaUsarElPng(): void {
        foreach (["", "assets/img/no-existe.png"] as $logo) {
            \Marca::usarParaPruebas(["logo" => $logo]);
            try {
                $this->assertSame("", \Logo::enLinea(), "logo «{$logo}»");
            } finally {
                \Marca::usarParaPruebas(null);
            }
        }
    }

    public function testLasVistasSoloImprimenElSvgQueSaleDeLogoEnLinea(): void {
        $hallados = 0;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(BASE_PATH . "/app/Views", \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->getExtension() !== "php") continue;
            $codigo = (string)file_get_contents($f->getPathname());
            if (!str_contains($codigo, '$logoSvg')) continue;
            $hallados++;
            preg_match_all('/\$logoSvg\s*=\s*([^;]+);/', $codigo, $m);
            $this->assertNotEmpty($m[1], basename($f->getPathname()) . ": \$logoSvg sin asignación");
            foreach ($m[1] as $origen) $this->assertStringStartsWith("Logo::enLinea(", trim($origen), basename($f->getPathname()) . ": \$logoSvg no sale de Logo::enLinea");
        }
        $this->assertGreaterThanOrEqual(1, $hallados); // desde P7 el único que imprime el logo es marca_bloque.php (acceso, encabezado, pie y errores lo incluyen)
    }

    public function testNoQuedaElFiltroCssQueTenia_ElLogo(): void {
        foreach (["app/Views/layouts/public_header.php", "app/Views/layouts/admin_header.php", "app/Views/layouts/public_footer.php", "app/Views/layouts/marca_bloque.php", "app/Views/layouts/acceso_marca.php", "app/Views/public/error.php"] as $f) {
            $this->assertDoesNotMatchRegularExpression('/filter\s*:/i', (string)file_get_contents(BASE_PATH . "/$f"), "$f: filtro en línea sobre el logo");
        }
        foreach (["base.css", "extra.css", "style.css"] as $css) {
            // ninguna regla sobre el logo o la marca lleva filter (los de .btn-close y .navbar-toggler-icon son otra cosa)
            $this->assertDoesNotMatchRegularExpression('/(marca|logo|navbar-brand)[^{}]*\{[^}]*\bfilter\s*:/i', (string)file_get_contents(BASE_PATH . "/assets/css/$css"), "$css: filtro CSS que tiñe el logo");
        }
    }
}
