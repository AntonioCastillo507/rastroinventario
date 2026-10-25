<?php
namespace Tests\Support;

/**
 * Busca los datos de la marca (nombre, teléfono, correo) escritos a mano en el código.
 * Solo se permiten en app/Config/marca.php, en docs/ y en database/demo.sql; los .md de la raíz y composer.json
 * son documentación del repositorio y no se revisan.
 */
final class GuardaMarca {
    /** Carpetas y archivos que se revisan, relativos a la raíz */
    private const REVISAR = ["app", "admin", "assets/js", "assets/css", "bin", "database", "tests", "index.php", "login.php", "registro.php", "olvide.php", "restablecer.php", "logout.php", "bootstrap.php"];
    private const EXTENSIONES = ["php", "js", "css", "sql", "ps1", "html", "txt", "json"];
    /** Rutas (relativas, con /) que no se revisan */
    private const EXCLUIDAS = ["app/Config/marca.php", "database/demo.sql"];
    /** Carpetas que nunca se leen: secretos y terceros */
    private const SALTAR = ["vendor", "node_modules", ".git", "ssl", "logs", "uploads"];

    /**
     * Patrones (regex, sin delimitadores) de los valores reales de la marca, a partir de sus datos.
     * @param array{nombre:string,telefono:string,correo:string} $marca
     * @return array<string,string> etiqueta => regex
     */
    public static function patrones(array $marca): array {
        $p = [];
        $nombre = trim($marca["nombre"]);
        if ($nombre !== "") {
            $palabras = array_map(fn($w) => preg_quote($w, "~"), preg_split('/\s+/u', $nombre));
            $p["nombre"] = implode('[\s_-]*', $palabras);   // con espacios, sin ellos o en mayúsculas
        }
        $digitos = preg_replace('/\D+/', "", $marca["telefono"]);
        if (strlen($digitos) >= 7) {
            $local = substr($digitos, -8);                    // sin el código del país
            $p["telefono"] = implode('[\s.\-]?', str_split($local, 4));
        }
        if (trim($marca["correo"]) !== "") $p["correo"] = preg_quote(trim($marca["correo"]), "~");
        return $p;
    }

    /**
     * @param array<string,string> $patrones
     * @return list<string> "ruta:línea (etiqueta)"
     */
    public static function buscar(string $raiz, array $patrones): array {
        $hallazgos = [];
        $raiz = rtrim(str_replace("\\", "/", $raiz), "/");
        foreach (self::REVISAR as $entrada) {
            $ruta = "$raiz/$entrada";
            if (is_file($ruta)) { self::revisar($raiz, $ruta, $patrones, $hallazgos); continue; }
            if (!is_dir($ruta)) continue;
            $it = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($ruta, \FilesystemIterator::SKIP_DOTS),
                fn(\SplFileInfo $f) => !($f->isDir() && in_array($f->getFilename(), self::SALTAR, true))
            ));
            foreach ($it as $f) if ($f->isFile()) self::revisar($raiz, str_replace("\\", "/", $f->getPathname()), $patrones, $hallazgos);
        }
        sort($hallazgos);
        return $hallazgos;
    }

    private static function revisar(string $raiz, string $archivo, array $patrones, array &$hallazgos): void {
        $rel = ltrim(substr($archivo, strlen($raiz)), "/");
        if (in_array($rel, self::EXCLUIDAS, true)) return;
        if (!in_array(strtolower(pathinfo($archivo, PATHINFO_EXTENSION)), self::EXTENSIONES, true)) return;
        if (basename($archivo) === "config.php" || basename($archivo) === "rsa_secret.php") return; // secretos: no se leen
        $lineas = @file($archivo, FILE_IGNORE_NEW_LINES);
        if ($lineas === false) return;
        foreach ($lineas as $i => $linea) {
            foreach ($patrones as $etiqueta => $regex) {
                if (preg_match("~$regex~iu", $linea)) $hallazgos[] = "$rel:" . ($i + 1) . " ($etiqueta)";
            }
        }
    }
}
