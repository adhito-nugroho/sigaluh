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
    echo ❌ Gagal melakukan git push dari laptop!
    pause
    exit /b 1
)

:: 2. Kirim perintah SSH ke Server Windows untuk jalankan Git Pull
echo.
echo 🔄 [2/2] Menghubungi server via SSH (%SERVER_USER%@%SERVER_IP%:%SERVER_PORT%)...
echo *(Jika diminta password SSH, silakan masukkan password akun server)*
echo.

:: Perintah CMD native untuk server Windows
ssh -p %SERVER_PORT% %SERVER_USER%@%SERVER_IP% "cd /d %REMOTE_DIR% && git pull origin %BRANCH%"

if %errorlevel% neq 0 (
    echo.
    echo ❌ SSH atau Git Pull di server gagal.
    echo.
    echo Periksa kemungkinan berikut:
    echo 1. Apakah 'cloudflared access tcp' masih berjalan di port 2222?
    echo 2. Apakah password SSH salah / terputus?
    echo 3. Apakah folder %REMOTE_DIR% di server sudah di-clone git repository-nya?
    pause
    exit /b 1
)

echo.
echo ======================================================
echo ✅ DEPLOYMENT SELESAI! Server berhasil diperbarui.
echo ======================================================
pause
