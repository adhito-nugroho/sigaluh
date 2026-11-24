@echo off
setlocal EnableExtensions

rem ============================================================
rem  Setup backup otomatis SI GALUH di komputer server (Windows).
rem  Jalankan sekali di server: scripts\backup\setup-server.bat
rem  Skrip ini idempoten (aman dijalankan ulang setelah deploy).
rem ============================================================

set "APP_DIR=%~dp0..\.."
cd /d "%APP_DIR%"

rem Sesi SSH Windows sering tidak punya System32 di PATH — pakai path penuh.
set "PS_EXE=%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe"
if not exist "%PS_EXE%" set "PS_EXE=powershell"
set "SCHTASKS_EXE=%SystemRoot%\System32\schtasks.exe"
if not exist "%SCHTASKS_EXE%" set "SCHTASKS_EXE=schtasks"

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
"%PS_EXE%" -NoProfile -ExecutionPolicy Bypass -File "%~dp0register-task.ps1"
if errorlevel 1 (
    echo [!] Pendaftaran via PowerShell gagal, mencoba schtasks...
    "%SCHTASKS_EXE%" /Create /F /SC DAILY /ST 23:15 /TN "SI GALUH - Backup Harian" /TR "cmd /c \"%~dp0run-backup.bat\""
    if errorlevel 1 (
        echo.
        echo [GAGAL] Windows Task Scheduler menolak pendaftaran.
        echo         Jalankan skrip ini langsung di server lewat cmd/RDP,
        echo         bukan dari sesi SSH non-interaktif.
        exit /b 1
    )
    echo [OK] Tugas didaftarkan lewat schtasks.
)

rem --- 3. Uji backup sekali ---
echo.
echo [3/3] Menjalankan uji backup pertama...

rem status.json lama dihapus supaya hasil uji benar-benar berasal dari run ini
if exist "backups\status.json" del /f /q "backups\status.json" >nul 2>&1
if exist "backups\status.json" (
    echo [GAGAL] status.json terkunci proses lain. Tutup backup yang sedang berjalan lalu ulangi.
    exit /b 1
)

call "%~dp0run-backup.bat"
set "TEST_EXIT=%errorlevel%"

if not exist "backups\status.json" (
    echo.
    echo [GAGAL] Script backup tidak berjalan sampai selesai.
    echo         Periksa php.exe dan izin tulis folder backups, lalu ulangi.
    exit /b 1
)

echo.
echo --- 6 baris terakhir backups\backup.log ---
"%PS_EXE%" -NoProfile -Command "Get-Content 'backups\backup.log' -Tail 6 -ErrorAction SilentlyContinue"

echo.
if "%TEST_EXIT%"=="0" (
    echo [OK] Backup lokal + salin ke Google Drive berhasil.
) else (
    echo [PERINGATAN] Uji backup belum sepenuhnya berhasil ^(kode %TEST_EXIT%^).
    echo   - Lihat log di atas untuk penyebabnya.
    echo   - Jika backup lokal sukses tetapi Drive kosong: pasang dan login
    echo     "Google Drive for desktop" di server ini, atau isi folder tujuan
    echo     lewat menu Pengaturan ^> Backup Data di aplikasi.
)

echo.
echo Jadwal aktif: setiap hari 23:15 (tugas "SI GALUH - Backup Harian").
echo ======================================================
exit /b 0
