@echo off
setlocal EnableExtensions

rem Launcher backup SI GALUH. Dipanggil Windows Task Scheduler atau tombol di aplikasi.
rem Catatan: output TIDAK dialihkan ke file bersama, karena redirect ke file yang
rem sedang terkunci (antivirus/proses lain) membuat backup tidak jalan sama sekali.
rem Semua aktivitas dicatat oleh PHP ke backups\backup.log.
set "APP_DIR=%~dp0..\.."
cd /d "%APP_DIR%"

if not exist "backups" mkdir "backups"

rem 1) Path PHP hasil setup-server.bat
set "PHP_EXE="
if exist "%~dp0php-path.txt" set /p PHP_EXE=<"%~dp0php-path.txt"
if defined PHP_EXE if not exist "%PHP_EXE%" set "PHP_EXE="

rem 2) PHP di PATH
if not defined PHP_EXE for /f "delims=" %%P in ('where php 2^>nul') do if not defined PHP_EXE set "PHP_EXE=%%P"

rem 3) Instalasi Laragon standar
if not defined PHP_EXE (
    for %%R in ("C:\laragon" "D:\laragon") do (
        for /d %%D in ("%%~R\bin\php\*") do (
            if not defined PHP_EXE if exist "%%~D\php.exe" set "PHP_EXE=%%~D\php.exe"
        )
    )
)

if not defined PHP_EXE (
    echo [%DATE% %TIME%] ERROR: php.exe tidak ditemukan. Jalankan scripts\backup\setup-server.bat
    exit /b 1
)

"%PHP_EXE%" "scripts\backup\backup.php"
exit /b %errorlevel%
