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
 *
 * EMAILJS_PRIVATE_KEY es opcional: solo hace falta si en tu cuenta de EmailJS
 * activaste "API calls: Only allow requests from my listed domains" para las
 * llamadas server-side (Account -> Security).
 */
class EmailJsService {
    private static ?EmailJsService $instance = null;
    private string $logFile;

    private function __construct() {
        $dir = BASE_PATH . DIRECTORY_SEPARATOR . "logs";
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $this->logFile = $dir . DIRECTORY_SEPARATOR . "emailjs.log";
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
                "app_nombre" => "Rastro del Istmo",
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
                "app_nombre"   => "Rastro del Istmo",
            ]
        );
    }

    /** Llama a la API REST de EmailJS. Nunca lanza excepcion hacia afuera. */
    private function enviar(string $templateId, array $params): bool {
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
        @file_put_contents($this->logFile, "[" . date("Y-m-d H:i:s") . "] {$linea}\n", FILE_APPEND);
    }
}
