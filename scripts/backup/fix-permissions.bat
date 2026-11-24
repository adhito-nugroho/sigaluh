@echo off
setlocal EnableExtensions

rem ============================================================
rem  Perbaiki izin folder SI GALUH agar backup bisa membaca file.
rem  Pemakaian: scripts\backup\fix-permissions.bat [user]
rem  Contoh   : scripts\backup\fix-permissions.bat adit
rem ============================================================

set "APP_DIR=%~dp0..\.."
cd /d "%APP_DIR%"

rem Sesi SSH Windows sering tanpa System32 di PATH
set "ICACLS_EXE=%SystemRoot%\System32\icacls.exe"
if not exist "%ICACLS_EXE%" set "ICACLS_EXE=icacls"

set "TARGET_USER=%~1"
if "%TARGET_USER%"=="" set "TARGET_USER=%USERNAME%"

echo ======================================================
echo  PERBAIKAN IZIN FOLDER SI GALUH
echo  User: %TARGET_USER%
echo  Folder: %CD%
echo ======================================================
echo.

rem --- Deteksi PHP untuk verifikasi ---
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

if defined PHP_EXE (
    echo --- Sebelum perbaikan ---
    "%PHP_EXE%" "scripts\backup\check-readable.php"
    echo.
)

echo --- Menerapkan izin ---
set "FAILED=0"

for %%D in ("uploads\lampiran" "uploads\ttd" "backups" "scripts\backup") do (
    if exist "%%~D" (
        echo [*] %%~D
        "%ICACLS_EXE%" "%%~D" /remove:d "%TARGET_USER%" /T /C /Q >nul 2>&1
        "%ICACLS_EXE%" "%%~D" /grant "%TARGET_USER%":(OI)(CI)F /T /C /Q >nul 2>&1
        if errorlevel 1 (
            echo     GAGAL
            set "FAILED=1"
        ) else (
            echo     OK
        )
    ) else (
        echo [*] %%~D - tidak ada, dilewati
    )
)

echo.
if not defined PHP_EXE goto after_check

echo --- Sesudah perbaikan ---
"%PHP_EXE%" "scripts\backup\check-readable.php"
set "CHECK_EXIT=%errorlevel%"
echo.

if "%FAILED%"=="1" (
    echo [GAGAL] Sebagian folder tidak dapat diperbaiki.
    echo         Jalankan skrip ini sebagai Administrator lewat RDP di server.
    exit /b 1
)

if not "%CHECK_EXIT%"=="0" (
    echo [PERINGATAN] Masih ada file yang tidak dapat dibaca.
    echo   - Jika alasannya "Access is denied"/"Permission denied", jalankan sebagai Administrator.
    echo   - Jika file terenkripsi EFS atau rusak, upload ulang TTD tersebut lewat menu Profil ^> Tanda Tangan.
    exit /b 1
)

echo [OK] Izin folder diperbaiki dan semua file upload dapat dibaca.
exit /b 0

:after_check
if "%FAILED%"=="1" (
    echo [GAGAL] Sebagian folder tidak dapat diperbaiki.
    echo         Jalankan skrip ini sebagai Administrator lewat RDP di server.
    exit /b 1
)
echo [OK] Izin folder diperbaiki ^(verifikasi dilewati: PHP tidak ditemukan^).
exit /b 0
