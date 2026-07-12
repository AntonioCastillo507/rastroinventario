<?php
/**
 * RsaCrypto — Implementación RSA-SHA256 de CryptoInterface.
 * Usada para firmar documentos PDF (facturas) con OpenSSL.
 *
 * setupfix5: la llave privada ahora se genera y se lee con passphrase
 * (RSA_KEY_PASSPHRASE en app/Config/config.php), en vez de quedar en
 * texto plano dentro de ssl/rastro.key. La passphrase nunca se guarda
 * en la base de datos ni se expone en ninguna vista.
 */
class RsaCrypto implements CryptoInterface {
    private string $certPath;
    private string $keyPath;

    public function __construct(string $certPath = "", string $keyPath = "") {
        $this->certPath = $certPath ?: BASE_PATH.DIRECTORY_SEPARATOR."ssl".DIRECTORY_SEPARATOR."rastro.crt";
        $this->keyPath  = $keyPath  ?: BASE_PATH.DIRECTORY_SEPARATOR."ssl".DIRECTORY_SEPARATOR."rastro.key";
    }

    private function passphrase(): string {
        return defined("RSA_KEY_PASSPHRASE") ? RSA_KEY_PASSPHRASE : "";
    }

    public function firmar(string $datos, string $clave = ""): string {
        if (!file_exists($this->keyPath)) return "";
        $privKey = openssl_pkey_get_private("file://".$this->keyPath, $this->passphrase());
        if (!$privKey) return "";
        openssl_sign($datos, $firma, $privKey, OPENSSL_ALGO_SHA256);
        return base64_encode($firma);
    }

    public function verificar(string $datos, string $firma, string $clave = ""): bool {
        if (!file_exists($this->certPath)) return false;
        $pubKey = openssl_pkey_get_public("file://".$this->certPath);
        if (!$pubKey) return false;
        return openssl_verify($datos, base64_decode($firma), $pubKey, OPENSSL_ALGO_SHA256) === 1;
    }

    public function getCertContent(): string {
        return file_exists($this->certPath) ? file_get_contents($this->certPath) : "";
    }
    public function getKeyContent(): string {
        return file_exists($this->keyPath)  ? file_get_contents($this->keyPath)  : "";
    }
    /** Passphrase de la llave privada — la usa FacturaController al firmar el PDF. */
    public function getKeyPassphrase(): string {
        return $this->passphrase();
    }
    public function algoritmo(): string { return "RSA-SHA256"; }
}
