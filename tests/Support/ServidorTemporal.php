<?php
namespace Tests\Support;

/**
 * Servidor temporal de PHP (php -S) sobre la carpeta del proyecto y la base rastroinventario_test, solo en 127.0.0.1.
 * Sirve para comprobar códigos HTTP reales. Se cierra al terminar la prueba.
 */
final class ServidorTemporal {
    /** @var resource|null */
    private $proceso = null;
    private int $puerto = 0;

    public function iniciar(): void {
        $socket = stream_socket_server("tcp://127.0.0.1:0", $errno, $err);
        if ($socket === false) throw new \RuntimeException("No pude reservar un puerto: $err");
        $this->puerto = (int)substr(strrchr((string)stream_socket_get_name($socket, false), ":"), 1);
        fclose($socket);
        $env = array_merge(getenv(), ["RASTRO_DB_NAME" => "rastroinventario_test", "XDEBUG_MODE" => "off"]);
        $this->proceso = proc_open([PHP_BINARY, "-S", "127.0.0.1:" . $this->puerto, "-t", BASE_PATH], [0 => ["pipe", "r"], 1 => ["file", "NUL", "w"], 2 => ["file", "NUL", "w"]], $pipes, BASE_PATH, $env);
        if (!is_resource($this->proceso)) throw new \RuntimeException("No pude lanzar php -S.");
        for ($i = 0; $i < 50; $i++) {
            $c = @fsockopen("127.0.0.1", $this->puerto, $en, $es, 0.2);
            if ($c) { fclose($c); return; }
            usleep(100000);
        }
        $this->detener();
        throw new \RuntimeException("php -S no respondió a tiempo.");
    }

    /** Dirección base del servidor, p. ej. http://127.0.0.1:50321 */
    public function base(): string {
        return "http://127.0.0.1:" . $this->puerto;
    }

    public function detener(): void {
        if (is_resource($this->proceso)) {
            $estado = proc_get_status($this->proceso);
            if (!empty($estado["pid"]) && PHP_OS_FAMILY === "Windows") exec("taskkill /F /T /PID " . (int)$estado["pid"] . " >NUL 2>&1");
            else proc_terminate($this->proceso);
            proc_close($this->proceso);
        }
        $this->proceso = null;
    }

    /** @return array{codigo:int,cuerpo:string} */
    public function get(string $ruta): array {
        $ctx = stream_context_create(["http" => ["ignore_errors" => true, "timeout" => 10, "follow_location" => 0]]);
        $cuerpo = @file_get_contents("http://127.0.0.1:" . $this->puerto . $ruta, false, $ctx);
        $codigo = 0;
        foreach ($http_response_header ?? [] as $h) if (preg_match('~^HTTP/\S+ (\d{3})~', $h, $m)) $codigo = (int)$m[1];
        return ["codigo" => $codigo, "cuerpo" => (string)$cuerpo];
    }
}
