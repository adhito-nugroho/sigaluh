@echo off
setlocal enabledelayedexpansion

:: =======================================================
:: KONFIGURASI DATABASE & SSH SERVER
:: =======================================================
set DB_NAME=sigaluh2
set DB_USER_LOCAL=root
set DB_PASS_LOCAL=

set SERVER_USER=adit
set SERVER_IP=127.0.0.1
set SERVER_PORT=2222
set DB_USER_REMOTE=root
set DB_PASS_REMOTE=

set DUMP_FILE=db_export_temp.sql

echo ======================================================
echo 🗄️ MEMULAI MIGRASI DATABASE MYSQL KE SERVER
echo ======================================================

:: 1. Export database dari MySQL Lokal
echo 📤 [1/3] Meng-export database lokal (%DB_NAME%)...

powershell -Command "$d = (dir C:\laragon\bin\mysql\*\bin\mysqldump.exe, D:\laragon\bin\mysql\*\bin\mysqldump.exe, 'C:\Program Files\MySQL\*\bin\mysqldump.exe' -ErrorAction SilentlyContinue).FullName; if ($d -is [array]) { $d = $d[0] }; if (-not $d) { $d = 'mysqldump' }; & $d -u %DB_USER_LOCAL% %DB_NAME% > %DUMP_FILE%"

if not exist %DUMP_FILE% (
    echo ❌ Gagal export database lokal! Pastikan MySQL Laragon lokal aktif.
    pause
    exit /b 1
)

:: 2. Upload file SQL ke Server via SCP
echo.
echo 🚚 [2/3] Mengirim file dump SQL ke server...
scp -P %SERVER_PORT% %DUMP_FILE% %SERVER_USER%@%SERVER_IP%:C:/Windows/Temp/%DUMP_FILE%

if %errorlevel% neq 0 (
    echo ❌ Gagal mengunggah file SQL ke server!
    del %DUMP_FILE%
    pause
    exit /b 1
)

:: 3. Import SQL ke MySQL Server via SSH (Menambahkan PATH Laragon secara otomatis)
echo.
echo 📥 [3/3] Meng-import database di MySQL Server...

set "REMOTE_PATH_ADDITION=C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin;C:\laragon\bin\mysql\mysql-8.0.35-winx64\bin;C:\laragon\bin\mysql\mysql-5.7.33-winx64\bin;D:\laragon\bin\mysql\mysql-8.0.30-winx64\bin;C:\Program Files\MySQL\MySQL Server 8.0\bin"

if "%DB_PASS_REMOTE%"=="" (
    ssh -p %SERVER_PORT% %SERVER_USER%@%SERVER_IP% "set PATH=%%PATH%%;%REMOTE_PATH_ADDITION% && mysql -u %DB_USER_REMOTE% -e \"CREATE DATABASE IF NOT EXISTS %DB_NAME%;\" && mysql -u %DB_USER_REMOTE% %DB_NAME% < C:\Windows\Temp\%DUMP_FILE% && del C:\Windows\Temp\%DUMP_FILE%"
) else (
    ssh -p %SERVER_PORT% %SERVER_USER%@%SERVER_IP% "set PATH=%%PATH%%;%REMOTE_PATH_ADDITION% && mysql -u %DB_USER_REMOTE% -p%DB_PASS_REMOTE% -e \"CREATE DATABASE IF NOT EXISTS %DB_NAME%;\" && mysql -u %DB_USER_REMOTE% -p%DB_PASS_REMOTE% %DB_NAME% < C:\Windows\Temp\%DUMP_FILE% && del C:\Windows\Temp\%DUMP_FILE%"
)

:: Hapus dump lokal sementara
if exist %DUMP_FILE% del %DUMP_FILE%

if %errorlevel% neq 0 (
    echo.
    echo ❌ Gagal import database di server!
    pause
    exit /b 1
)

echo.
echo ======================================================
echo ✅ MIGRASI DATABASE MYSQL BERHASIL!
echo ======================================================
pause
