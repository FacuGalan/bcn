@echo off
chcp 65001 >nul
title Instalador de Certificado BCN Pymes para QZ Tray
echo.
echo ========================================
echo  BCN Pymes - Instalador de Certificado
echo  para impresion con QZ Tray
echo ========================================
echo.

:: Crear carpeta en Documentos del usuario
set "CERTDIR=%USERPROFILE%\Documents\BCN Pymes"
if not exist "%CERTDIR%" mkdir "%CERTDIR%"

:: Descargar certificado
echo Descargando certificado...
powershell -Command "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; Invoke-WebRequest -Uri 'https://bcn.bcnsoft.com.ar/qz/certificate-v2.txt' -OutFile '%CERTDIR%\bcn-pymes-qz.crt'" 2>nul

if not exist "%CERTDIR%\bcn-pymes-qz.crt" (
    echo [ERROR] No se pudo descargar el certificado.
    echo Verifique su conexion a internet.
    pause
    exit /b 1
)

echo.
echo [OK] Certificado descargado en:
echo     %CERTDIR%\bcn-pymes-qz.crt
echo.
echo ========================================
echo  INSTRUCCIONES PARA COMPLETAR
echo ========================================
echo.
echo 1. Se abrira QZ Tray Site Manager
echo 2. Haga clic en el boton [+] (agregar)
echo 3. Seleccione el archivo:
echo    %CERTDIR%\bcn-pymes-qz.crt
echo 4. Cierre la ventana
echo.
echo Presione una tecla para abrir Site Manager...
pause >nul

:: Buscar QZ Tray y abrir Site Manager
set "QZPATH="
if exist "C:\Program Files\QZ Tray\qz-tray.exe" (
    set "QZPATH=C:\Program Files\QZ Tray"
) else if exist "C:\Program Files (x86)\QZ Tray\qz-tray.exe" (
    set "QZPATH=C:\Program Files (x86)\QZ Tray"
)

if not defined QZPATH (
    echo.
    echo [AVISO] QZ Tray no encontrado en ubicacion estandar.
    echo.
    echo Abra QZ Tray manualmente:
    echo   - Clic derecho en icono de QZ Tray en bandeja del sistema
    echo   - Seleccione: Advanced ^> Site Manager
    echo   - Agregue el certificado desde: %CERTDIR%\bcn-pymes-qz.crt
    echo.
    echo Abriendo carpeta con el certificado...
    explorer "%CERTDIR%"
    pause
    exit /b 0
)

:: Verificar si QZ Tray esta corriendo
tasklist /FI "IMAGENAME eq qz-tray.exe" 2>nul | find /I "qz-tray.exe" >nul
if %ERRORLEVEL% NEQ 0 (
    echo Iniciando QZ Tray...
    start "" "%QZPATH%\qz-tray.exe"
    timeout /t 3 /nobreak >nul
)

:: Abrir Site Manager via protocolo qz
echo Abriendo Site Manager...
start "" "qz:site-manager"

:: Si el protocolo no funciona, abrir carpeta como fallback
timeout /t 2 /nobreak >nul
echo.
echo Si no se abrio Site Manager automaticamente:
echo   - Clic derecho en icono QZ Tray (bandeja del sistema)
echo   - Seleccione: Advanced ^> Site Manager
echo.
echo Certificado guardado en: %CERTDIR%\bcn-pymes-qz.crt
echo.

:: Abrir carpeta con el certificado para facilitar
explorer /select,"%CERTDIR%\bcn-pymes-qz.crt"

echo ========================================
echo  Despues de agregar el certificado,
echo  ya puede imprimir sin confirmaciones
echo ========================================
echo.
pause
