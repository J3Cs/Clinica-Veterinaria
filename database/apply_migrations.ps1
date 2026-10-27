# =============================================================================
# apply_migrations.ps1
# Clinica Veterinaria - Aplicar migraciones y seeds al contenedor Docker
# =============================================================================
# Uso:
#   .\database\apply_migrations.ps1              -> aplica migraciones + seeds
#   .\database\apply_migrations.ps1 -SkipSeeds   -> solo migraciones
# =============================================================================

param(
    [switch]$SkipSeeds
)

# --- Configuracion -----------------------------------------------------------
$CONTAINER  = "mysql_dual"
$DB_NAME    = "clinica_veterinaria"
$DB_USER    = "app_user"
$DB_PASS    = "app_password"

$MIGRATIONS = @(
    "database\migrations\002_propietarios_dui.sql",
    "database\migrations\003_mascotas_activo.sql",
    "database\migrations\004_fk_restrict.sql",
    "database\migrations\005_citas_indice_disponibilidad.sql"
)

$SEEDS = @(
    "database\seeds\dev_citas_seed.sql"
)

# --- Helpers -----------------------------------------------------------------
function Write-Step($msg)  { Write-Host "`n[>] $msg" -ForegroundColor Cyan }
function Write-Ok($msg)    { Write-Host "    [OK] $msg" -ForegroundColor Green }
function Write-Fail($msg)  { Write-Host "    [ERROR] $msg" -ForegroundColor Red }

function Apply-SqlFile {
    param([string]$RelativePath)

    $fullPath = Join-Path $PSScriptRoot ".." $RelativePath

    if (-not (Test-Path $fullPath)) {
        Write-Fail "Archivo no encontrado: $fullPath"
        return $false
    }

    Write-Step $RelativePath
    $sql = Get-Content $fullPath -Raw -Encoding UTF8

    $sql | docker exec -i $CONTAINER mysql -u $DB_USER -p$DB_PASS $DB_NAME 2>&1

    if ($LASTEXITCODE -eq 0) {
        Write-Ok "Aplicado correctamente."
        return $true
    } else {
        Write-Fail "Fallo al aplicar. Revisa la salida anterior."
        return $false
    }
}

# --- Verificar que el contenedor esta corriendo ------------------------------
Write-Host "`n============================================" -ForegroundColor Yellow
Write-Host " Clinica Veterinaria - Migraciones DB" -ForegroundColor Yellow
Write-Host "============================================" -ForegroundColor Yellow

Write-Step "Verificando contenedor '$CONTAINER'..."
$running = docker ps --filter "name=$CONTAINER" --filter "status=running" --format "{{.Names}}"

if ($running -ne $CONTAINER) {
    Write-Fail "El contenedor '$CONTAINER' no esta corriendo."
    Write-Host "    Inicia los contenedores con: docker compose up -d" -ForegroundColor Yellow
    exit 1
}
Write-Ok "Contenedor activo."

# --- Aplicar migraciones -----------------------------------------------------
Write-Host "`n--- MIGRACIONES ---" -ForegroundColor Magenta
$errors = 0

foreach ($file in $MIGRATIONS) {
    $ok = Apply-SqlFile -RelativePath $file
    if (-not $ok) { $errors++ }
}

# --- Aplicar seeds (opcional) ------------------------------------------------
if (-not $SkipSeeds) {
    Write-Host "`n--- SEEDS ---" -ForegroundColor Magenta
    foreach ($file in $SEEDS) {
        $ok = Apply-SqlFile -RelativePath $file
        if (-not $ok) { $errors++ }
    }
} else {
    Write-Host "`n[!] Seeds omitidos (-SkipSeeds activo)." -ForegroundColor Yellow
}

# --- Resumen -----------------------------------------------------------------
Write-Host "`n============================================" -ForegroundColor Yellow
if ($errors -eq 0) {
    Write-Host " Completado sin errores." -ForegroundColor Green
} else {
    Write-Host " Completado con $errors error(es). Revisa la salida." -ForegroundColor Red
}
Write-Host "============================================`n" -ForegroundColor Yellow

exit $errors

