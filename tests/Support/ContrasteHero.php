<?php
namespace Tests\Support;

/**
 * Contraste del texto de la portada sobre la foto con el scrim puesto, calculado con los píxeles reales de la imagen
 * (la misma cuenta que tests/Support/contraste_hero.js hace en el navegador). WCAG 2.x: (L1 + 0,05) / (L2 + 0,05).
 */
final class ContrasteHero {
    /** @return array{0:int,1:int,2:int} */
    public static function hex(string $hex): array {
        $hex = ltrim($hex, "#");
        if (strlen($hex) === 3) $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private static function lineal(float $v): float {
        $v /= 255;
        return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
    }

    public static function luminancia(float $r, float $g, float $b): float {
        return 0.2126 * self::lineal($r) + 0.7152 * self::lineal($g) + 0.0722 * self::lineal($b);
    }

    public static function razon(float $a, float $b): float {
        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    /**
     * Peor contraste de un texto opaco sobre cualquier píxel de la foto, ya cubierta por el scrim.
     * @param array{0:int,1:int,2:int} $texto
     * @param array{0:int,1:int,2:int} $scrim color del scrim
     * @param float $alfa opacidad del scrim (0 a 1)
     */
    public static function peorContraste(string $imagen, array $texto, array $scrim, float $alfa): float {
        $im = @imagecreatefromstring((string)file_get_contents($imagen));
        if ($im === false) throw new \RuntimeException("No pude leer la imagen $imagen");
        $lt = self::luminancia(...$texto);
        $w = imagesx($im);
        $h = imagesy($im);
        $peor = INF;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($im, $x, $y);
                $r = (($c >> 16) & 255) * (1 - $alfa) + $scrim[0] * $alfa;
                $g = (($c >> 8) & 255) * (1 - $alfa) + $scrim[1] * $alfa;
                $b = ($c & 255) * (1 - $alfa) + $scrim[2] * $alfa;
                $r2 = self::razon($lt, self::luminancia($r, $g, $b));
                if ($r2 < $peor) $peor = $r2;
            }
        }
        return $peor;
    }
}
