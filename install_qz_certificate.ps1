# Script para instalar certificado QZ Tray como trusted
# Esto elimina el cartel de "Allow" cada vez que se conecta

$qzTrayPath = "$env:LOCALAPPDATA\QZ Tray"
$trustedPath = "$qzTrayPath\trusted"
$overridePath = "$qzTrayPath\override"

Write-Host "=== Instalacion de Certificado QZ Tray ===" -ForegroundColor Cyan
Write-Host ""

# Verificar/crear directorio de QZ Tray
if (-not (Test-Path $qzTrayPath)) {
    Write-Host "Directorio de QZ Tray no existe, creandolo..." -ForegroundColor Yellow
    Write-Host "  $qzTrayPath" -ForegroundColor Gray
    New-Item -ItemType Directory -Path $qzTrayPath -Force | Out-Null
    Write-Host "  Directorio creado" -ForegroundColor Green
    Write-Host ""
    Write-Host "NOTA: QZ Tray debe estar instalado para que esto funcione." -ForegroundColor Yellow
    Write-Host "      Descarga desde: https://qz.io/download/" -ForegroundColor Cyan
    Write-Host ""
}

# Crear directorio trusted si no existe
if (-not (Test-Path $trustedPath)) {
    Write-Host "Creando directorio trusted..." -ForegroundColor Yellow
    New-Item -ItemType Directory -Path $trustedPath -Force | Out-Null
}

# Crear directorio override si no existe
if (-not (Test-Path $overridePath)) {
    Write-Host "Creando directorio override..." -ForegroundColor Yellow
    New-Item -ItemType Directory -Path $overridePath -Force | Out-Null
}

# Copiar certificado
$certSource = "$PSScriptRoot\public\qz\certificate.txt"
$certDest = "$trustedPath\bcn-pymes.crt"

if (Test-Path $certSource) {
    Write-Host "Copiando certificado a QZ Tray trusted..." -ForegroundColor Yellow
    Copy-Item -Path $certSource -Destination $certDest -Force
    Write-Host "  Certificado instalado en: $certDest" -ForegroundColor Green
} else {
    Write-Host "ERROR: No se encontro el certificado en:" -ForegroundColor Red
    Write-Host "  $certSource" -ForegroundColor Yellow
    exit 1
}

# Crear archivo de propiedades para auto-permitir localhost
$propsFile = "$qzTrayPath\qz-tray.properties"
$propsContent = @"
# Configuracion BCN Pymes - QZ Tray
# Generado automaticamente

# Lista de origenes permitidos (sin cartel de Allow)
# Formato: wss.whitelist=hostname1,hostname2
wss.whitelist=localhost,127.0.0.1,bcn-pymes.local,pymes.local

# Logging (cambiar a true para debug)
log.level=OFF
"@

# Verificar si ya existe y preguntar
if (Test-Path $propsFile) {
    Write-Host ""
    Write-Host "Ya existe un archivo de configuracion qz-tray.properties" -ForegroundColor Yellow
    $backup = "$propsFile.backup.$(Get-Date -Format 'yyyyMMdd_HHmmss')"
    Copy-Item -Path $propsFile -Destination $backup -Force
    Write-Host "  Backup creado en: $backup" -ForegroundColor Gray
}

# Escribir configuracion
Set-Content -Path $propsFile -Value $propsContent -Encoding UTF8
Write-Host "Archivo de configuracion actualizado: $propsFile" -ForegroundColor Green

Write-Host ""
Write-Host "=== Instalacion Completada ===" -ForegroundColor Green
Write-Host ""
Write-Host "IMPORTANTE: Debes reiniciar QZ Tray para que los cambios surtan efecto." -ForegroundColor Yellow
Write-Host ""
Write-Host "Pasos:" -ForegroundColor Cyan
Write-Host "  1. Cierra QZ Tray (click derecho en icono de bandeja > Exit)"
Write-Host "  2. Abre QZ Tray nuevamente"
Write-Host "  3. Actualiza la pagina del navegador"
Write-Host ""

# Preguntar si reiniciar QZ Tray
$restart = Read-Host "Deseas reiniciar QZ Tray ahora? (S/N)"
if ($restart -eq "S" -or $restart -eq "s") {
    Write-Host "Cerrando QZ Tray..." -ForegroundColor Yellow
    Stop-Process -Name "qz-tray" -Force -ErrorAction SilentlyContinue
    Start-Sleep -Seconds 2

    # Buscar ejecutable de QZ Tray
    $qzExe = "$env:PROGRAMFILES\QZ Tray\qz-tray.exe"
    if (-not (Test-Path $qzExe)) {
        $qzExe = "${env:PROGRAMFILES(x86)}\QZ Tray\qz-tray.exe"
    }

    if (Test-Path $qzExe) {
        Write-Host "Iniciando QZ Tray..." -ForegroundColor Yellow
        Start-Process -FilePath $qzExe
        Write-Host "QZ Tray reiniciado correctamente" -ForegroundColor Green
    } else {
        Write-Host "No se pudo encontrar el ejecutable de QZ Tray" -ForegroundColor Yellow
        Write-Host "Por favor, inicia QZ Tray manualmente" -ForegroundColor Yellow
    }
}

Write-Host ""
Write-Host "Listo!" -ForegroundColor Green
