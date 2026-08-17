@echo off
setlocal enabledelayedexpansion

:: =======================================================
:: KONFIGURASI SERVER (CLOUDFLARE SSH TUNNEL & WINDOWS)
:: =======================================================
set SERVER_USER=adit
set SERVER_IP=127.0.0.1
set SERVER_PORT=2222
set "REMOTE_DIR=C:\laragon\www\sigaluh2"
set BRANCH=main

:: Path penuh di server (SSH Windows PATH sering kosong/minimal)
set "REMOTE_GIT=C:\laragon\bin\git\cmd\git.exe"
set "REMOTE_PHP=C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe"

echo ======================================================
echo MEMULAI DEPLOYMENT DENGAN GIT PULL DAN MIGRASI DB
echo ======================================================

:: 1. Commit (jika ada perubahan) lalu Push ke remote
echo [1/3] Mendorong perubahan lokal ke Repository...

git add .

git status --porcelain | findstr /R "." >nul
if errorlevel 1 (
    echo Tidak ada perubahan lokal. Skip commit, lanjut push/pull...
) else (
    set "msg=update aplikasi"
    git commit -m "!msg!"
)

git push origin %BRANCH%
if errorlevel 1 (
    echo Gagal melakukan git push dari laptop!
    pause
    exit /b 1
)

:: 2. Git Pull di Server via SSH
:: Shell remote sudah cmd — jangan bungkus cmd /c lagi (PATH SSH tanpa System32)
echo.
echo [2/3] Menjalankan 'git pull' di server...
echo *(Jika diminta password SSH, masukkan password akun server)*
echo.

ssh -p %SERVER_PORT% %SERVER_USER%@%SERVER_IP% "cd /d %REMOTE_DIR% && %REMOTE_GIT% pull origin %BRANCH%"

if errorlevel 1 (
    echo.
    echo Git Pull di server gagal.
    pause
    exit /b 1
)

:: 3. Jalankan PHP Migration di Server via SSH
echo.
echo [3/3] Menjalankan migrasi database di server...

ssh -p %SERVER_PORT% %SERVER_USER%@%SERVER_IP% "cd /d %REMOTE_DIR% && %REMOTE_PHP% migrate.php"

if errorlevel 1 (
    echo.
    echo Migrasi CLI server gagal. Anda juga bisa membuka http://file.cdkbojonegoro.my.id/migrate.php di browser.
)

echo.
echo ======================================================
echo DEPLOYMENT DAN MIGRASI SELESAI!
echo ======================================================
pause
