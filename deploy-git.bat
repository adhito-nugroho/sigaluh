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
echo 🚀 MEMULAI DEPLOYMENT DENGAN GIT PULL DAN MIGRASI DB
echo ======================================================

:: 1. Simpan dan Push perubahan dari Laptop ke GitHub / Remote Git
echo 📤 [1/3] Mendorong perubahan lokal ke Repository...
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

:: 2. Jalankan Git Pull di Server via SSH
echo.
echo 🔄 [2/3] Menjalankan 'git pull' di server...
echo *(Jika diminta password SSH, masukkan password akun server)*
echo.
ssh -p %SERVER_PORT% %SERVER_USER%@%SERVER_IP% "cd /d %REMOTE_DIR% && git pull origin %BRANCH%"

if %errorlevel% neq 0 (
    echo.
    echo ❌ Git Pull di server gagal.
    pause
    exit /b 1
)

:: 3. Jalankan PHP Migration di Server via SSH
echo.
echo 🗄️ [3/3] Menjalankan migrasi database di server...
ssh -p %SERVER_PORT% %SERVER_USER%@%SERVER_IP% "cd /d %REMOTE_DIR% && (if exist C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe (C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe migrate.php) else (php migrate.php))"

if %errorlevel% neq 0 (
    echo.
    echo ⚠️ Migrasi CLI server gagal. Anda juga bisa membuka http://file.cdkbojonegoro.my.id/migrate.php di browser.
)

echo.
echo ======================================================
echo ✅ DEPLOYMENT DAN MIGRASI SELESAI!
echo ======================================================
pause
