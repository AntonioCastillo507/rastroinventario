<?php
/**
 * Crea un usuario (por defecto Administrador) desde la consola, o cambia la clave de uno existente.
 *
 *   php database\crear_usuario.php                      -> Administrador nuevo
 *   php database\crear_usuario.php --rol=Vendedor       -> otro rol
 *   php database\crear_usuario.php --actualizar         -> nueva clave para un correo que ya existe
 *
 * Pide correo, nombre y la contraseña dos veces (sin eco en Windows). La clave nunca va por argumentos.
 * Para automatizar pruebas sobre bases desechables: RASTRO_USR_EMAIL, RASTRO_USR_CLAVE y RASTRO_USR_NOMBRE.
 */
if (PHP_SAPI !== "cli") { http_response_code(404); exit; }

define("BASE_PATH", dirname(__DIR__));
require_once BASE_PATH . "/bootstrap.php";

function salir(string $msg, int $codigo = 1): never {
    fwrite(STDERR, $msg . PHP_EOL);
    exit($codigo);
}

function preguntar(string $texto): string {
    fwrite(STDOUT, $texto);
    $l = fgets(STDIN);
    return $l === false ? "" : trim($l);
}

// Lee la clave sin mostrarla. En Windows usa PowerShell; en otros sistemas, stty.
function preguntarClave(string $texto): string {
    // Entrada redirigida (no es una consola): se lee la línea tal cual, no hay nada que ocultar
    if (!stream_isatty(STDIN)) return preguntar($texto);
    fwrite(STDOUT, $texto);
    if (PHP_OS_FAMILY === "Windows") {
        $ps = '$s = Read-Host -AsSecureString; '
            . '[Runtime.InteropServices.Marshal]::PtrToStringBSTR([Runtime.InteropServices.Marshal]::SecureStringToBSTR($s))';
        $r = shell_exec('powershell -NoProfile -Command "' . $ps . '"');
    } else {
        shell_exec("stty -echo");
        $r = fgets(STDIN);
        shell_exec("stty echo");
    }
    fwrite(STDOUT, PHP_EOL);
    return $r === false || $r === null ? "" : rtrim($r, "\r\n");
}

$rolNombre  = "Administrador";
$actualizar = false;
foreach (array_slice($argv, 1) as $a) {
    if ($a === "--actualizar") $actualizar = true;
    elseif (str_starts_with($a, "--rol=")) $rolNombre = substr($a, 6);
    else salir("Opción desconocida: $a");
}

$db = Database::get();
fwrite(STDOUT, "Base de datos: " . $db->query("SELECT DATABASE()")->fetchColumn() . PHP_EOL);

$rid = $db->prepare("SELECT id FROM roles WHERE nombre = ?");
$rid->execute([$rolNombre]);
$rolId = $rid->fetchColumn();
if (!$rolId) salir("El rol '$rolNombre' no existe. ¿Cargaste database/seed_basico.sql?");

$porEntorno = getenv("RASTRO_USR_CLAVE") !== false;
$email  = Sanitizer::email($porEntorno ? (string)getenv("RASTRO_USR_EMAIL") : preguntar("Correo: "));
$um     = new UserModel();
$previo = $email !== "" ? $um->findByEmail($email) : false;
if ($actualizar) {
    if (!$previo) salir("No existe ningún usuario con ese correo.");
    $nombre = $previo["nombre"];
} else {
    if ($previo) salir("Ya existe un usuario con ese correo. Usa --actualizar para cambiarle la clave.");
    $nombre = Sanitizer::string($porEntorno ? (string)getenv("RASTRO_USR_NOMBRE") : preguntar("Nombre: "));
}

if ($porEntorno) {
    $clave = (string)getenv("RASTRO_USR_CLAVE");
} else {
    $clave = preguntarClave("Contraseña: ");
    if ($clave !== preguntarClave("Repite la contraseña: ")) salir("Las contraseñas no coinciden.");
}

$v = (new Validator())->required("email", $email, "Correo")->email("email", $email)
      ->required("nombre", $nombre, "Nombre")
      ->password("clave", $clave, $email, $nombre);
if (!$v->isValid()) salir("No se creó nada: " . $v->first());

$hash = password_hash($clave, PASSWORD_BCRYPT);
if ($actualizar) {
    $um->updatePassword((int)$previo["id"], $hash);
    $um->resetIntentos((int)$previo["id"]);
    fwrite(STDOUT, "Contraseña actualizada para $email (cuenta desbloqueada)." . PHP_EOL);
    exit(0);
}

$db->beginTransaction();
try {
    $uid = $um->create(["nombre" => $nombre, "email" => $email, "password" => $hash, "telefono" => "", "direccion" => ""]);
    if (!$uid) throw new RuntimeException("No se pudo crear el usuario.");
    $um->setRoles($uid, [(int)$rolId]);
    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    salir("No se creó nada: " . $e->getMessage());
}
fwrite(STDOUT, "Usuario creado: $email con rol $rolNombre (id $uid)." . PHP_EOL);
