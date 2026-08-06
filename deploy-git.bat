@echo off
setlocal enabledelayedexpansion

:: =======================================================
:: KONFIGURASI SERVER (CLOUDFLARE SSH TUNNEL & WINDOWS)
:: =======================================================
set SERVER_USER=adit
set SERVER_IP=127.0.0.1
set SERVER_PORT=2222
set REMOTE_DIR=c:\laragon\www\sigaluh2
set BRANCH=main

echo ======================================================
echo 🚀 MEMULAI DEPLOYMENT DENGAN GIT PULL & MIGRASI DB
echo ======================================================

:: 1. Simpan dan Push perubahan dari Laptop ke GitHub / Remote Git
echo 📤 [1/2] Mendorong perubahan lokal ke Repository...
git add .

set /p msg="Masukkan pesan commit (tekan Enter untuk default 'update'): "
if "!msg!"=="" set msg=update aplikasi

git commit -m "!msg!"
git push origin %BRANCH%

if %errorlevel% neq 0 (
    echo ❌ Gagal melakukan git push dari laptop!
    pause
    exit /b 1
)

:: 2. Kirim perintah SSH ke Server Windows untuk jalankan Git Pull & PHP Migrate
echo.
echo 🔄 [2/2] Menghubungi server via SSH (%SERVER_USER%@%SERVER_IP%:%SERVER_PORT%)...
echo *(Jika diminta password SSH, silakan masukkan password akun server)*
echo.

set "PHP_PATHS=C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64;C:\laragon\bin\php\php-8.3.6-nts-Win32-vs16-x64;C:\laragon\bin\php\php-8.2.0;C:\laragon\bin\php\php-8.1.0;C:\laragon\bin\php\php-8.0.0;D:\laragon\bin\php\php-8.1.10-Win32-vs16-x64"

ssh -p %SERVER_PORT% %SERVER_USER%@%SERVER_IP% "set PATH=%%PATH%%;%PHP_PATHS% && cd /d %REMOTE_DIR% && git pull origin %BRANCH% && php migrate.php"

if %errorlevel% neq 0 (
    echo.
    echo ❌ Deployment atau Git Pull di server gagal.
    echo.
    pause
    exit /b 1
)

echo.
echo ======================================================
echo ✅ DEPLOYMENT & MIGRASI DATABASE BERHASIL!
echo ======================================================
pause
