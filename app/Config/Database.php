<?php
class Database {
    private static ?PDO $conn = null;
    private function __construct() {}
    public static function get(): PDO {
        if (self::$conn === null) {
            try {
                self::$conn = new PDO(
                    "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
                    DB_USER, DB_PASS,
                    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                     PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
                     PDO::ATTR_EMULATE_PREPARES=>false]
                );
                self::$conn->exec("SET time_zone = '-05:00'"); // Panama UTC-5
            } catch (PDOException $e) {
                http_response_code(500);
                die("<b>Error de BD:</b> ".htmlspecialchars($e->getMessage()));
            }
        }
        return self::$conn;
    }
}
