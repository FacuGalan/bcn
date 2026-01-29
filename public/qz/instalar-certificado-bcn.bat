@echo off
chcp 65001 >nul
title Instalador de Certificado BCN Pymes para QZ Tray
echo.
echo ========================================
echo  BCN Pymes - Instalador de Certificado
echo  para impresion con QZ Tray
echo ========================================
echo.

:: Verificar permisos de administrador
net session >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] Este script necesita ejecutarse como Administrador.
    echo.
    echo Haga clic derecho en el archivo y seleccione
    echo "Ejecutar como administrador"
    echo.
    pause
    exit /b 1
)

:: Buscar carpeta de QZ Tray
set "QZPATH="
if exist "C:\Program Files\QZ Tray" (
    set "QZPATH=C:\Program Files\QZ Tray"
) else if exist "C:\Program Files (x86)\QZ Tray" (
    set "QZPATH=C:\Program Files (x86)\QZ Tray"
)

if not defined QZPATH (
    echo [ERROR] QZ Tray no esta instalado.
    echo Descargue e instale desde: https://qz.io/download/
    echo.
    pause
    exit /b 1
)

echo QZ Tray encontrado en: %QZPATH%
echo.

:: Cerrar QZ Tray si esta corriendo
echo Cerrando QZ Tray...
taskkill /F /IM "qz-tray.exe" >nul 2>&1
timeout /t 2 /nobreak >nul

:: Descargar certificado
echo Descargando certificado...
powershell -Command "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; Invoke-WebRequest -Uri 'https://bcn.bcnsoft.com.ar/qz/certificate-v2.txt' -OutFile '%QZPATH%\override.crt'" 2>nul

if exist "%QZPATH%\override.crt" (
    echo.
    echo [OK] Certificado instalado en: %QZPATH%\override.crt
) else (
    echo [ERROR] No se pudo descargar el certificado.
    pause
    exit /b 1
)

:: Reiniciar QZ Tray
echo.
echo Iniciando QZ Tray...
start "" "%QZPATH%\qz-tray.exe"

echo.
echo ========================================
echo  Instalacion completada!
echo ========================================
echo.
echo Ya puede imprimir desde BCN Pymes
echo sin mensajes de confirmacion.
echo.
pause
