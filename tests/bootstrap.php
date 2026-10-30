<?php
// Arranque de las pruebas. Ante cualquier duda sobre la base de datos se aborta antes de usarla.

function abortar(string $msg): never {
    fwrite(STDERR, PHP_EOL . "[PRUEBAS ABORTADAS] " . $msg . PHP_EOL . PHP_EOL);
    exit(2);
}

if (PHP_SAPI !== "cli") abortar("Las pruebas solo se corren por consola.");

// Primera guarda: la variable tiene que valer exactamente la base de pruebas
$bd = getenv("RASTRO_DB_NAME");
if ($bd !== "rastroinventario_test") {
    abortar("RASTRO_DB_NAME debe valer exactamente rastroinventario_test (vale "
        . ($bd === false ? "nada" : "otra cosa") . "). Usa:  composer test");
}

// Carpeta temporal propia: uploads de prueba, sesiones y registro de errores
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "rastro_test_" . getmypid();
foreach (["uploads", "sessions"] as $sub) {
    if (!is_dir("$tmp/$sub") && !mkdir("$tmp/$sub", 0777, true)) abortar("No pude crear la carpeta temporal $tmp.");
}
register_shutdown_function(function () use ($tmp): void {
    if (session_status() === PHP_SESSION_ACTIVE) session_destroy(); // suelta el archivo de sesión antes de borrar la carpeta
    if (!is_dir($tmp)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    @rmdir($tmp);
});

define("BASE_PATH", dirname(__DIR__));
define("UPLOAD_PATH", $tmp . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR);
define("RASTRO_TEST_TMP", $tmp);

// La sesión se abre aquí, sin cookies, para que bootstrap.php no falle por la salida de PHPUnit
ini_set("session.use_cookies", "0");
ini_set("session.use_only_cookies", "0");
session_cache_limiter("");
session_save_path("$tmp/sessions");
session_start();

require_once BASE_PATH . "/vendor/autoload.php";
require_once BASE_PATH . "/bootstrap.php";
ini_set("error_log", "$tmp/error.log"); // los fallos esperados de las pruebas no ensucian logs/error.log

// Segunda guarda: conexión propia a la base de pruebas, con un fallo claro y código de salida distinto de 0
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=rastroinventario_test;charset=utf8mb4", DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    abortar("No pude conectar con la base rastroinventario_test (código " . $e->getCode() . "). "
        . "¿Está encendido MySQL (puerto 3306) y existe la base? Créala con:  composer test:reset");
}

// Tercera guarda: la conexión que usa la aplicación tiene que ser la de pruebas (Database.php cae en silencio a DB_NAME)
$activa = Database::get()->query("SELECT DATABASE()")->fetchColumn();
if ($activa !== "rastroinventario_test") {
    abortar("La aplicación se conectó a otra base distinta de rastroinventario_test. No se corre nada.");
}

// Cuarta guarda: la base tiene que estar instalada y sin datos reales
$tablas = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='rastroinventario_test'")->fetchColumn();
$roles  = (int)$pdo->query("SELECT COUNT(*) FROM roles WHERE nombre IN ('Administrador','Vendedor','Consultor','Cliente')")->fetchColumn();
if ($tablas !== 17 || $roles !== 4) {
    abortar("rastroinventario_test no está instalada (tablas: $tablas, roles: $roles). Créala con:  composer test:reset");
}
$ajenos = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE email NOT LIKE '%@example.invalid'")->fetchColumn();
if ($ajenos > 0) {
    abortar("rastroinventario_test tiene $ajenos usuario(s) con un correo que no es @example.invalid: parece una base con datos reales. No se corre nada.");
}
unset($pdo, $activa, $tablas, $roles, $ajenos, $bd);
