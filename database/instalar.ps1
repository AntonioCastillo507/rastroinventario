# Crea una base de Rastroinventario desde cero: schema.sql + seed_basico.sql (+ demo.sql).
#
#   .\database\instalar.ps1 -Base rastroinventario
#   .\database\instalar.ps1 -Base rastroinventario_test -Recrear
#   .\database\instalar.ps1 -Base rastroinventario_demo -Demo
#
# -Recrear borra la base antes de crearla, pero SOLO si se llama rastroinventario_test
# o rastroinventario_check. Con cualquier otro nombre (incluida rastroinventario) se niega.
param(
    [Parameter(Mandatory = $true)][string]$Base,
    [switch]$Demo,
    [switch]$Recrear,
    [string]$Mysql    = "C:\wamp64\bin\mysql\mysql8.4.7\bin\mysql.exe",
    [string]$Servidor = "127.0.0.1",
    [int]$Puerto      = 3306,
    [string]$Usuario  = "root"
)

$ErrorActionPreference = "Stop"
$bdPermitidasParaBorrar = @("rastroinventario_test", "rastroinventario_check")

if ($Base -notmatch '^[A-Za-z0-9_]{1,64}$') { throw "Nombre de base no valido: $Base" }
if (-not (Test-Path $Mysql)) { throw "No encuentro mysql.exe en $Mysql (usa -Mysql con la ruta correcta)." }

$aqui = Split-Path -Parent $MyInvocation.MyCommand.Path
$archivos = @("schema.sql", "seed_basico.sql")
if ($Demo) { $archivos += "demo.sql" }
foreach ($a in $archivos) {
    if (-not (Test-Path (Join-Path $aqui $a))) { throw "Falta $a en $aqui" }
}

# Cliente de MySQL: sin contrasena, como la instalacion por defecto de Wamp
$cli = @("-u", $Usuario, "-h", $Servidor, "-P", "$Puerto", "--default-character-set=utf8mb4")

$existe = & $Mysql @cli -N -B -e "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '$Base'"
if ($LASTEXITCODE -ne 0) { throw "No pude conectar con MySQL en ${Servidor}:$Puerto." }

if ($existe) {
    if (-not $Recrear) { throw "La base '$Base' ya existe. Usa -Recrear (solo permitido para: $($bdPermitidasParaBorrar -join ', '))." }
    if ($bdPermitidasParaBorrar -notcontains $Base) { throw "-Recrear solo se permite con: $($bdPermitidasParaBorrar -join ', '). No se toco nada." }
    & $Mysql @cli -e "DROP DATABASE ``$Base``"
    if ($LASTEXITCODE -ne 0) { throw "No se pudo borrar '$Base'." }
}

& $Mysql @cli -e "CREATE DATABASE ``$Base`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
if ($LASTEXITCODE -ne 0) { throw "No se pudo crear '$Base'." }

foreach ($a in $archivos) {
    $ruta = (Join-Path $aqui $a) -replace '\\', '/'
    & $Mysql @cli $Base -e "source $ruta"
    if ($LASTEXITCODE -ne 0) { throw "Fallo la carga de $a. La base '$Base' quedo a medias." }
    Write-Host "Cargado: $a"
}

Write-Host "Listo: base '$Base' creada. Siguiente paso: php database\crear_usuario.php"
