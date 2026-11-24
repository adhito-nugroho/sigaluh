@echo off
setlocal EnableExtensions

rem ============================================================
rem  Setup backup otomatis SI GALUH di komputer server (Windows).
rem  Jalankan sekali di server: scripts\backup\setup-server.bat
rem  Skrip ini idempoten (aman dijalankan ulang setelah deploy).
rem ============================================================

set "APP_DIR=%~dp0..\.."
cd /d "%APP_DIR%"

echo ======================================================
echo  SETUP BACKUP OTOMATIS SI GALUH
echo ======================================================
echo Folder aplikasi: %CD%
echo.

if not exist "backups" mkdir "backups"
if not exist "backups\.htaccess" >"backups\.htaccess" echo Require all denied

rem --- 1. Deteksi php.exe server ---
set "PHP_EXE="
if exist "%~dp0php-path.txt" set /p PHP_EXE=<"%~dp0php-path.txt"
if defined PHP_EXE if not exist "%PHP_EXE%" set "PHP_EXE="
if not defined PHP_EXE for /f "delims=" %%P in ('where php 2^>nul') do if not defined PHP_EXE set "PHP_EXE=%%P"
if not defined PHP_EXE (
    for %%R in ("C:\laragon" "D:\laragon") do (
        for /d %%D in ("%%~R\bin\php\*") do (
            if not defined PHP_EXE if exist "%%~D\php.exe" set "PHP_EXE=%%~D\php.exe"
        )
    )
)
if not defined PHP_EXE (
    echo [GAGAL] php.exe tidak ditemukan. Pastikan Laragon terpasang di server.
    exit /b 1
)
>"%~dp0php-path.txt" echo %PHP_EXE%
echo [1/3] PHP: %PHP_EXE%

rem --- 2. Daftarkan jadwal harian 23:15 ---
echo.
echo [2/3] Mendaftarkan jadwal backup harian 23:15...
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0register-task.ps1"
if errorlevel 1 (
    echo.
    echo [GAGAL] Windows Task Scheduler menolak pendaftaran.
    echo         Jalankan skrip ini langsung di server lewat cmd/RDP,
    echo         bukan dari sesi SSH non-interaktif.
    exit /b 1
)

rem --- 3. Uji backup sekali ---
echo.
echo [3/3] Menjalankan uji backup pertama...
call "%~dp0run-backup.bat"
set "TEST_EXIT=%errorlevel%"

echo.
echo --- 6 baris terakhir backups\backup.log ---
powershell -NoProfile -Command "Get-Content 'backups\backup.log' -Tail 6 -ErrorAction SilentlyContinue"

echo.
if "%TEST_EXIT%"=="0" (
    echo [OK] Backup lokal + salin ke Google Drive berhasil.
) else (
    echo [PERINGATAN] Backup lokal berhasil, tetapi Google Drive belum tersalin.
    echo   - Pasang dan login "Google Drive for desktop" di server ini, atau
    echo   - isi folder tujuan lewat menu Pengaturan ^> Backup Data di aplikasi.
)

echo.
echo Jadwal aktif: setiap hari 23:15 (tugas "SI GALUH - Backup Harian").
echo ======================================================
exit /b 0
