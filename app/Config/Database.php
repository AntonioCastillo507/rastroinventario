<?php
class Database {
    private static ?PDO $conn = null;
    private function __construct() {}
    public static function get(): PDO {
        if (self::$conn === null) {
            // Pruebas: solo por consola o php -S y solo estas dos bases; Apache nunca lo lee
            $bd = (in_array(PHP_SAPI, ["cli", "cli-server"], true)
                && preg_match('/^rastroinventario_(test|check)$/', (string)getenv("RASTRO_DB_NAME")))
                ? getenv("RASTRO_DB_NAME") : DB_NAME;
            try {
                self::$conn = new PDO(
                    "mysql:host=".DB_HOST.";dbname=$bd;charset=utf8mb4",
                    DB_USER, DB_PASS,
                    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                     PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
                     PDO::ATTR_EMULATE_PREPARES=>false]
                );
                self::$conn->exec("SET time_zone = '-05:00'"); // Panama UTC-5
            } catch (PDOException $e) {
                paginaError(registrarError($e, "BD: "), 503);
            }
        }
        return self::$conn;
    }
}
