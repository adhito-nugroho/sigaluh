@echo off
setlocal enabledelayedexpansion

:: =======================================================
:: KONFIGURASI SERVER (CLOUDFLARE SSH TUNNEL & WINDOWS)
:: =======================================================
set SERVER_USER=adit
set SERVER_IP=127.0.0.1
set SERVER_PORT=2222
set REMOTE_DIR=d:\laragon\www\sigaluh2
set BRANCH=main

echo ======================================================
echo 🚀 MEMULAI DEPLOYMENT DENGAN GIT PULL
echo ======================================================

:: 1. Simpan dan Push perubahan dari Laptop ke GitHub / Remote Git
echo 📤 [1/2] Mendorong perubahan lokal ke Repository...
git add .

set /p msg="Masukkan pesan commit (tekan Enter untuk default 'update'): "
if "!msg!"=="" set msg=update aplikasi

git commit -m "!msg!"
git push origin %BRANCH%

if %errorlevel% neq 0 (
    echo ❌ Gagal melakukan git push ke repository!
    pause
    exit /b 1
)

:: 2. Kirim perintah SSH ke Server Windows untuk jalankan Git Pull
echo.
echo 🔄 [2/2] Menjalankan 'git pull' di server via Cloudflare Tunnel (127.0.0.1:2222)...
ssh -p %SERVER_PORT% %SERVER_USER%@%SERVER_IP% "cd /d %REMOTE_DIR% && git pull origin %BRANCH%"

if %errorlevel% neq 0 (
    echo.
    echo ❌ Gagal melakukan git pull di server!
    echo Pastikan tunnel cloudflared sedang berjalan di port 2222.
    pause
    exit /b 1
)

echo.
echo ======================================================
echo ✅ DEPLOYMENT SELESAI! Server berhasil diperbarui.
echo ======================================================
pause
