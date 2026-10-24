# Aplica una migracion a una base, siempre con respaldo previo.
#
#   .\database\migraciones\aplicar.ps1 -Base rastroinventario_check -Archivo 001_password_resets.sql -Confirmar
#   .\database\migraciones\aplicar.ps1 -Base rastroinventario -Confirmar
#
# Sin -Confirmar no hace nada (solo dice que haria). El respaldo va a C:\wamp64\respaldo_rastro\
# (fuera del proyecto, porque lleva hashes de contrasena) y la migracion no se aplica si el
# respaldo no existe o esta vacio. Solo acepta rastroinventario, rastroinventario_test y rastroinventario_check.
param(
    [Parameter(Mandatory = $true)][string]$Base,
    [string]$Archivo     = "001_password_resets.sql",
    [switch]$Confirmar,
    [string]$CarpetaResp = "C:\wamp64\respaldo_rastro",
    [string]$BinMysql    = "C:\wamp64\bin\mysql\mysql8.4.7\bin",
    [string]$Servidor    = "127.0.0.1",
    [int]$Puerto         = 3306,
    [string]$Usuario     = "root"
)

$ErrorActionPreference = "Stop"
$permitidas = @("rastroinventario", "rastroinventario_test", "rastroinventario_check")
if ($permitidas -notcontains $Base) { throw "Base no permitida: $Base (solo: $($permitidas -join ', '))." }
if ($Archivo -notmatch '^[A-Za-z0-9_]+\.sql$') { throw "Nombre de migracion no valido: $Archivo" }

$mysql = Join-Path $BinMysql "mysql.exe"
$dump  = Join-Path $BinMysql "mysqldump.exe"
foreach ($e in @($mysql, $dump)) { if (-not (Test-Path $e)) { throw "No encuentro $e (usa -BinMysql)." } }

$aqui = Split-Path -Parent $MyInvocation.MyCommand.Path
$sql  = Join-Path $aqui $Archivo
if (-not (Test-Path $sql)) { throw "No existe la migracion $sql" }

$cli = @("-u", $Usuario, "-h", $Servidor, "-P", "$Puerto")
$existe = & $mysql @cli -N -B -e "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '$Base'"
if ($LASTEXITCODE -ne 0) { throw "No pude conectar con MySQL en ${Servidor}:$Puerto." }
if (-not $existe) { throw "La base '$Base' no existe." }

$marca   = Get-Date -Format "yyyyMMdd_HHmmss"
$destino = Join-Path $CarpetaResp "rastro_pre_mig_$($Archivo -replace '\.sql$','')_$marca.sql"

if (-not $Confirmar) {
    Write-Host "SIN -Confirmar: no se hizo nada."
    Write-Host "Haria: respaldo de '$Base' en $destino y luego aplicaria $Archivo."
    exit 0
}

if (-not (Test-Path $CarpetaResp)) { New-Item -ItemType Directory -Path $CarpetaResp | Out-Null }
& $dump @cli --single-transaction --routines --default-character-set=utf8mb4 "--result-file=$destino" $Base
if ($LASTEXITCODE -ne 0) { throw "Fallo el respaldo; no se aplico nada." }
if (-not (Test-Path $destino) -or (Get-Item $destino).Length -lt 1024) { throw "El respaldo no existe o esta vacio; no se aplico nada." }
Write-Host "Respaldo: $destino ($((Get-Item $destino).Length) bytes)"

$ruta = $sql -replace '\\', '/'
& $mysql @cli --default-character-set=utf8mb4 $Base -e "source $ruta"
if ($LASTEXITCODE -ne 0) { throw "Fallo la migracion $Archivo. El respaldo esta en $destino." }
Write-Host "Aplicada: $Archivo en '$Base'."
