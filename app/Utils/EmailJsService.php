<?php
/**
 * EmailJsService — Envio de correos transaccionales via la API REST de EmailJS.
 *
 * Es un "esqueleto": funciona sin romper nada aunque no llenes las credenciales.
 * Mientras EMAILJS_ENABLED sea false (o falten las credenciales), simplemente
 * no hace nada y deja un renglon en logs/emailjs.log para que puedas revisarlo.
 *
 * Para activarlo de verdad, llena estas 5 constantes en app/Config/config.php:
 *   EMAILJS_ENABLED            true
 *   EMAILJS_SERVICE_ID         (Email Services -> tu servicio -> Service ID)
 *   EMAILJS_PUBLIC_KEY         (Account -> General -> Public Key)
 *   EMAILJS_TEMPLATE_BIENVENIDA  (ID de la plantilla de "cuenta creada")
 *   EMAILJS_TEMPLATE_PEDIDO      (ID de la plantilla de "compra realizada")
 *   EMAILJS_TEMPLATE_RESET       (opcional: plantilla de "recuperar contrasena"; variables to_email,
 *                                 to_name, reset_url, expira_minutos, app_nombre)
 *
 * EMAILJS_PRIVATE_KEY es opcional: solo hace falta si en tu cuenta de EmailJS
 * activaste "API calls: Only allow requests from my listed domains" para las
 * llamadas server-side (Account -> Security).
 */
class EmailJsService {
    private static ?EmailJsService $instance = null;
    // Solo para pruebas (vacíos en producción): sustituir el envío y mover los registros de carpeta
    public static mixed $transporte = null;   // callable(string $templateId, array $params): bool
    public static ?string $dirLogs = null;

    private function __construct() {
        $dir = self::dirLogs();
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
    }

    public static function get(): EmailJsService {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    /** Correo de bienvenida — se dispara al crear una cuenta de Cliente. */
    public function enviarBienvenida(string $email, string $nombre): bool {
        return $this->enviar(
            defined("EMAILJS_TEMPLATE_BIENVENIDA") ? EMAILJS_TEMPLATE_BIENVENIDA : "",
            [
                "to_email"   => $email,
                "to_name"    => $nombre,
                "app_nombre" => Marca::get("nombre"),
            ]
        );
    }

    /** Correo de confirmacion — se dispara cuando un pedido queda registrado. */
    public function enviarConfirmacionCompra(string $email, string $nombre, int $pedidoId, float $total): bool {
        return $this->enviar(
            defined("EMAILJS_TEMPLATE_PEDIDO") ? EMAILJS_TEMPLATE_PEDIDO : "",
            [
                "to_email"     => $email,
                "to_name"      => $nombre,
                "pedido_id"    => str_pad((string)$pedidoId, 6, "0", STR_PAD_LEFT),
                "pedido_total" => number_format($total, 2),
                "factura_url"  => APP_URL . "/?action=factura&pedido=" . $pedidoId,
                "app_nombre"   => Marca::get("nombre"),
            ]
        );
    }

    /** Correo de recuperacion de contrasena. $url es el enlace con el token (se arma con APP_URL). */
    public function enviarRecuperacion(string $email, string $nombre, string $url): bool {
        $ok = $this->enviar(
            defined("EMAILJS_TEMPLATE_RESET") ? EMAILJS_TEMPLATE_RESET : "",
            [
                "to_email"       => $email,
                "to_name"        => $nombre,
                "reset_url"      => $url,
                "expira_minutos" => PasswordResetModel::TTL_MIN,
                "app_nombre"     => Marca::get("nombre"),
            ]
        );
        // Solo en localhost y solo si el envio fallo: deja el enlace en un registro para probar el flujo
        if (!$ok) self::registrarEnlaceDev(defined("APP_URL") ? APP_URL : "", $email, $url);
        return $ok;
    }

    /** true si la URL base de la app es localhost, 127.0.0.1 o ::1 (nunca se mira el encabezado Host) */
    public static function esLocal(string $appUrl): bool {
        $host = parse_url($appUrl, PHP_URL_HOST);
        return is_string($host) && in_array(strtolower($host), ["localhost", "127.0.0.1", "[::1]", "::1"], true);
    }

    /**
     * Escribe el enlace de recuperacion en logs/reset_dev.log, pero solo si $appUrl es local.
     * En produccion no escribe nada: el token nunca va a un registro.
     */
    public static function registrarEnlaceDev(string $appUrl, string $email, string $enlace, ?string $dir = null): bool {
        if (!self::esLocal($appUrl)) return false;
        $dir ??= self::dirLogs();
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $email = preg_replace('/[\x00-\x1F\x7F]/', "", $email);
        return @file_put_contents($dir . DIRECTORY_SEPARATOR . "reset_dev.log", "[" . date("Y-m-d H:i:s") . "] {$email} {$enlace}
", FILE_APPEND) !== false;
    }

    private static function dirLogs(): string {
        return self::$dirLogs ?? (BASE_PATH . DIRECTORY_SEPARATOR . "logs");
    }

    /** Llama a la API REST de EmailJS. Nunca lanza excepcion hacia afuera. */
    private function enviar(string $templateId, array $params): bool {
        if (self::$transporte !== null) return (bool)(self::$transporte)($templateId, $params);

        // .invalid es un dominio reservado que nunca se entrega: ninguna prueba manda correos reales
        $para = strtolower(trim((string)($params["to_email"] ?? "")));
        if (str_ends_with($para, ".invalid")) {
            $this->log("SKIP (destinatario .invalid, no se envia) -> " . $para);
            return false;
        }

        $enabled = defined("EMAILJS_ENABLED") && EMAILJS_ENABLED === true;
        $tieneCredenciales = defined("EMAILJS_SERVICE_ID") && EMAILJS_SERVICE_ID !== ""
            && defined("EMAILJS_PUBLIC_KEY") && EMAILJS_PUBLIC_KEY !== ""
            && $templateId !== "";

        if (!$enabled || !$tieneCredenciales) {
            $this->log("SKIP (EmailJS no configurado aun) -> " . ($params["to_email"] ?? "?"));
            return false;
        }

        try {
            $body = [
                "service_id"  => EMAILJS_SERVICE_ID,
                "template_id" => $templateId,
                "user_id"     => EMAILJS_PUBLIC_KEY,
                "template_params" => $params,
            ];
            if (defined("EMAILJS_PRIVATE_KEY") && EMAILJS_PRIVATE_KEY !== "") {
                $body["accessToken"] = EMAILJS_PRIVATE_KEY;
            }

            $ch = curl_init("https://api.emailjs.com/api/v1.0/email/send");
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_HTTPHEADER     => ["Content-Type: application/json"],
                CURLOPT_POSTFIELDS     => json_encode($body),
            ]);
            $respuesta = curl_exec($ch);
            $codigo    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error     = curl_error($ch);
            curl_close($ch);

            if ($codigo >= 200 && $codigo < 300) {
                $this->log("OK -> " . ($params["to_email"] ?? "?") . " ({$templateId})");
                return true;
            }
            $this->log("FALLO [{$codigo}] {$error} {$respuesta} -> " . ($params["to_email"] ?? "?"));
            return false;
        } catch (\Throwable $e) {
            $this->log("EXCEPCION: " . $e->getMessage());
            return false;
        }
    }

    private function log(string $linea): void {
        @file_put_contents(self::dirLogs() . DIRECTORY_SEPARATOR . "emailjs.log", "[" . date("Y-m-d H:i:s") . "] {$linea}\n", FILE_APPEND);
    }
}
