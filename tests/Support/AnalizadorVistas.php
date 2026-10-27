<?php
namespace Tests\Support;

/**
 * Busca en el código de una vista las salidas (<?= ?>, echo, print) que imprimen una variable
 * sin pasarla por htmlspecialchars. Trabaja con los tokens de PHP, no con expresiones regulares.
 * Las funciones de FUNCIONES_SEGURAS devuelven texto inocuo (ya escapado o numérico), así que sus
 * argumentos no se revisan; el resto de variables se acepta solo si $permitida las deja pasar.
 */
final class AnalizadorVistas {
    private const FUNCIONES_SEGURAS = [
        "htmlspecialchars", "htmlentities", "urlencode", "rawurlencode",
        "number_format", "date", "count", "intval", "starsHtml",
        "attrs", "attrsPorCampo", // Confirmar:: escapa todo lo que devuelve (ConfirmarTest lo comprueba)
    ];
    private const IGNORAR = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

    /**
     * @param callable(string,?string):bool $permitida recibe el nombre de la variable y su primera clave (o null)
     * @return list<array{linea:int,variable:string}>
     */
    public static function analizar(string $php, callable $permitida): array {
        $t = array_values(array_filter(token_get_all($php), fn($x) => !is_array($x) || !in_array($x[0], self::IGNORAR, true)));
        $fallos = [];
        $n = count($t);
        for ($i = 0; $i < $n; $i++) {
            $id = is_array($t[$i]) ? $t[$i][0] : null;
            if ($id !== T_OPEN_TAG_WITH_ECHO && $id !== T_ECHO && $id !== T_PRINT) continue;
            // Salida desde aquí hasta ';' (a nivel 0 de paréntesis) o hasta el cierre de la etiqueta
            $j = $i + 1;
            $prof = 0;
            for (; $j < $n; $j++) {
                $x = $t[$j];
                if ($x === "(" || $x === "[" || $x === "{") $prof++;
                elseif ($x === ")" || $x === "]" || $x === "}") $prof--;
                elseif (($x === ";" && $prof <= 0) || (is_array($x) && $x[0] === T_CLOSE_TAG)) break;
            }
            foreach (self::revisar(array_slice($t, $i + 1, $j - $i - 1), $permitida) as $f) $fallos[] = $f;
            $i = $j;
        }
        return $fallos;
    }

    private static function revisar(array $e, callable $permitida): array {
        $fallos = [];
        $n = count($e);
        // La condición de un ternario (lo que va antes de '?') no se imprime: solo decide qué rama sale
        $cond = [];
        foreach ($e as $q => $x) {
            if ($x !== "?") continue;
            $prof = 0;
            for ($k = $q - 1; $k >= 0; $k--) {
                $y = $e[$k];
                if ($y === ")" || $y === "]") $prof++;
                elseif ($y === "(" || $y === "[") { if ($prof === 0) break; $prof--; }
                elseif ($y === ":" && $prof === 0) break;
                $cond[$k] = true;
            }
        }
        for ($i = 0; $i < $n; $i++) {
            $x = $e[$i];
            if (!is_array($x) || isset($cond[$i])) continue;
            // El sujeto de un match solo elige entre ramas
            if ($x[0] === T_MATCH && ($e[$i + 1] ?? null) === "(") {
                $i = self::cerrar($e, $i + 1, "(", ")");
                continue;
            }
            // Función segura: se salta toda la llamada, paréntesis incluidos
            if ($x[0] === T_STRING && in_array(strtolower($x[1]), array_map("strtolower", self::FUNCIONES_SEGURAS), true) && ($e[$i + 1] ?? null) === "(") {
                $i = self::cerrar($e, $i + 1, "(", ")");
                continue;
            }
            // Conversión a número: (int) $v, (float) $v
            if ($x[0] === T_INT_CAST || $x[0] === T_DOUBLE_CAST) {
                if (is_array($e[$i + 1] ?? null) && $e[$i + 1][0] === T_VARIABLE) $i = self::cadena($e, $i + 1)[2];
                continue;
            }
            if ($x[0] === T_VARIABLE) {
                [$nombre, $clave, $fin] = self::cadena($e, $i);
                if ($nombre !== '$this' && !$permitida(ltrim($nombre, '$'), $clave)) {
                    $fallos[] = ["linea" => $x[2], "variable" => $nombre . ($clave !== null ? "[$clave]" : "")];
                }
                $i = $fin;
            }
        }
        return $fallos;
    }

    /** Variable con sus índices: devuelve [nombre, primera clave, posición del último token] */
    private static function cadena(array $e, int $i): array {
        $nombre = $e[$i][1];
        $clave = null;
        $fin = $i;
        while (true) {
            $s = $e[$fin + 1] ?? null;
            if ($s === "[") {
                $cierra = self::cerrar($e, $fin + 1, "[", "]");
                if ($clave === null) {
                    $k = $e[$fin + 2] ?? null;
                    $clave = is_array($k) ? trim($k[1], "\"'") : null; // si la clave es otra variable queda como texto de ese token
                }
                $fin = $cierra;
            } elseif (is_array($s) && $s[0] === T_OBJECT_OPERATOR && is_array($e[$fin + 2] ?? null)) {
                $clave ??= $e[$fin + 2][1];
                $fin += 2;
            } else {
                break;
            }
        }
        return [$nombre, $clave, $fin];
    }

    /** Posición del cierre que corresponde al token de apertura en $i */
    private static function cerrar(array $e, int $i, string $abre, string $cierra): int {
        $prof = 0;
        for ($j = $i; $j < count($e); $j++) {
            if ($e[$j] === $abre) $prof++;
            elseif ($e[$j] === $cierra && --$prof === 0) return $j;
        }
        return count($e) - 1;
    }
}
