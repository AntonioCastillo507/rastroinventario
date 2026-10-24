<?php
namespace Tests\Support;

/** Lee el HTML que producen las vistas para contar filas, tarjetas y enlaces. */
final class Html {
    public static function xpath(string $html): \DOMXPath {
        $doc = new \DOMDocument();
        $previo = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);
        return new \DOMXPath($doc);
    }

    public static function contar(\DOMXPath $x, string $consulta): int {
        return $x->query($consulta)->length;
    }

    /** @return list<string> atributos href ya decodificados (&amp; → &) */
    public static function enlacesDePaginacion(\DOMXPath $x): array {
        $out = [];
        foreach ($x->query("//nav[@aria-label='Paginación']//a/@href") as $a) $out[] = $a->nodeValue;
        return $out;
    }

    /** Texto de la línea "Mostrando X–Y de N", o "" si no hay barra */
    public static function resumen(\DOMXPath $x): string {
        $n = $x->query("//nav[@aria-label='Paginación']/div");
        return $n->length ? trim($n->item(0)->textContent) : "";
    }
}
