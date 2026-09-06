@echo off
REM ---------------------------------------------------------------
REM  Billing Biliar - jalankan aplikasi
REM ---------------------------------------------------------------

cd /d "%~dp0"

REM Aplikasi tidak bisa boot tanpa .env dan APP_KEY, sedangkan wizard pemasangan
REM sendiri berjalan di dalam aplikasi. Jadi keduanya disiapkan lebih dulu.
if not exist ".env" (
    echo Menyiapkan berkas konfigurasi awal...
    copy ".env.example" ".env" >nul
)

findstr /B /C:"APP_KEY=base64:" ".env" >nul 2>&1
if errorlevel 1 (
    echo Membuat kunci aplikasi...
    php artisan key:generate --force >nul
)

if not exist "storage\installed" (
    echo.
    echo ==========================================================
    echo   Aplikasi belum dipasang.
    echo   Buka di browser:  http://localhost:8000/install
    echo ==========================================================
    echo.
)

REM Scheduler menjalankan backup otomatis dan penutupan sesi meja yang habis.
REM Windows tidak punya cron, jadi ia hidup bersama aplikasi.
start "billiard-scheduler" /min cmd /c "php artisan schedule:work"

php artisan serve
