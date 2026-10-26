<?php
namespace Tests\Support;

/**
 * Cliente HTTP mínimo contra el servidor temporal (php -S): guarda las cookies de sesión entre llamadas,
 * no sigue redirecciones solo (se piden a mano) y sabe sacar el token CSRF de una página.
 * Las cookies se envían a mano: la de sesión es de la ruta de APP_URL y el servidor temporal sirve desde la raíz.
 */
final class ClienteHttp {
    /** @var \CurlHandle */
    private $curl;
    /** @var array<string,string> */
    private array $cookies = [];

    public function __construct(private string $base) {
        $this->curl = curl_init();
        curl_setopt_array($this->curl, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 20,
        ]);
    }

    /** @return array{codigo:int,ubicacion:?string,cuerpo:string} */
    public function get(string $ruta): array {
        curl_setopt_array($this->curl, [CURLOPT_URL => $this->base . $ruta, CURLOPT_HTTPGET => true]);
        return $this->ejecutar();
    }

    /** @return array{codigo:int,ubicacion:?string,cuerpo:string} */
    public function post(string $ruta, array $campos): array {
        curl_setopt_array($this->curl, [CURLOPT_URL => $this->base . $ruta, CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($campos)]);
        return $this->ejecutar();
    }

    /** Pide la página, saca su token CSRF y hace el POST con él */
    public function postConToken(string $rutaFormulario, string $rutaPost, array $campos): array {
        $token = self::token($this->get($rutaFormulario)["cuerpo"]);
        return $this->post($rutaPost, $campos + ["csrf_token" => $token]);
    }

    public static function token(string $html): string {
        return preg_match('/name="csrf_token" value="([0-9a-f]+)"/', $html, $m) ? $m[1] : "";
    }

    /** value del input oculto con ese name (p. ej. resumen_visto), o "" si no está */
    public static function oculto(string $html, string $name): string {
        return preg_match('/name="' . preg_quote($name, "/") . '" value="([^"]*)"/', $html, $m) ? html_entity_decode($m[1]) : "";
    }

    /** Abre el paso 2 y lo envía como lo haría el navegador: con su token y su resumen_visto. Los $campos mandan sobre los de la página. */
    public function confirmarPaso2(array $campos): array {
        $html = $this->get("/index.php?action=carrito_datos")["cuerpo"];
        return $this->post("/index.php?action=procesar_carrito", $campos + ["csrf_token" => self::token($html), "resumen_visto" => self::oculto($html, "resumen_visto")]);
    }

    private function ejecutar(): array {
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, $this->cookies ? ["Cookie: " . http_build_query($this->cookies, "", "; ")] : []);
        $r = (string)curl_exec($this->curl);
        $cab = (int)curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
        $cabeceras = substr($r, 0, $cab);
        if (preg_match_all('/^Set-Cookie:\s*([^=;\s]+)=([^;\r\n]*)/mi', $cabeceras, $c, PREG_SET_ORDER)) {
            foreach ($c as [, $n, $v]) { if ($v === "" || $v === "deleted") unset($this->cookies[$n]); else $this->cookies[$n] = $v; }
        }
        $ubicacion = preg_match('/^Location:\s*(\S+)/mi', $cabeceras, $m) ? $m[1] : null;
        return ["codigo" => (int)curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE), "ubicacion" => $ubicacion, "cuerpo" => substr($r, $cab)];
    }

    /** Ruta (con consulta) de una URL absoluta de Location, para pedirla tal cual */
    public static function ruta(?string $ubicacion): string {
        $p = parse_url((string)$ubicacion);
        $ruta = $p["path"] ?? "/";
        $raiz = rtrim((string)parse_url(APP_URL, PHP_URL_PATH), "/");
        if ($raiz !== "" && str_starts_with($ruta, $raiz . "/")) $ruta = substr($ruta, strlen($raiz));
        return $ruta . (isset($p["query"]) ? "?" . $p["query"] : "");
    }
}
