<?php
namespace Tests\Support;

/** Ejecuta una acción de un controlador en un proceso PHP aparte (para las que terminan en exit o redirect). */
final class Proceso {
    /**
     * @param array{clase:string,accion:string,post?:array,get?:array,upload_path?:string,sesion?:array,ip?:string,csrf?:bool,metodo_http?:string} $entrada
     * @return array{cabeceras:list<string>,codigo:int,sesion:array,salida:string}
     */
    public static function accion(array $entrada): array {
        $json = RASTRO_TEST_TMP . DIRECTORY_SEPARATOR . "entrada_" . uniqid() . ".json";
        file_put_contents($json, json_encode($entrada));
        $env = array_merge(getenv(), ["RASTRO_DB_NAME" => "rastroinventario_test", "XDEBUG_MODE" => self::veCabeceras() ? "develop" : "off"]);
        $p = proc_open([PHP_BINARY, BASE_PATH . "/tests/Support/ejecutar_accion.php", $json],
            [1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes, null, $env);
        if (!is_resource($p)) throw new \RuntimeException("No pude lanzar el proceso hijo.");
        $salida = stream_get_contents($pipes[1]);
        $err    = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $codigo = proc_close($p);
        @unlink($json);
        if ($codigo === 2 || !str_contains($salida, "@@RESULTADO@@")) {
            throw new \RuntimeException("El proceso hijo falló (código $codigo): " . trim($err . " " . $salida));
        }
        [$antes, $json] = explode("@@RESULTADO@@", $salida, 2);
        $r = json_decode(trim($json), true);
        $r["salida"] = $antes;
        return $r;
    }

    /** En consola PHP no guarda las cabeceras enviadas; solo Xdebug permite leerlas (xdebug_get_headers) */
    public static function veCabeceras(): bool {
        return extension_loaded("xdebug");
    }

    /** Valor de la cabecera Location, o null */
    public static function ubicacion(array $r): ?string {
        foreach ($r["cabeceras"] as $h) if (stripos($h, "Location:") === 0) return trim(substr($h, 9));
        return null;
    }
}
