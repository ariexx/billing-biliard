@echo off
REM ---------------------------------------------------------------
REM  Billing Biliar - jalankan aplikasi
REM ---------------------------------------------------------------

cd /d "%~dp0"

REM Port bisa diganti lewat argumen:  start.bat 8080
set "PORT=%~1"
if "%PORT%"=="" set "PORT=8000"

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

REM Kalau port sudah dipakai, berhenti dengan pesan yang jelas. Tanpa ini
REM `artisan serve` diam-diam pindah ke 8001, 8002, dan seterusnya sampai 8010,
REM lalu menyerah dengan sebelas baris "Failed to listen" yang membingungkan.
netstat -ano -p TCP | findstr /R /C:"LISTENING" | findstr /C:"127.0.0.1:%PORT% " >nul 2>&1
if not errorlevel 1 (
    echo.
    echo ==========================================================
    echo   Port %PORT% sudah dipakai.
    echo.
    echo   Kemungkinan aplikasi ini SUDAH berjalan. Coba buka:
    echo     http://localhost:%PORT%
    echo.
    echo   Kalau bukan, tutup dulu program yang memakainya, atau
    echo   jalankan di port lain:   start.bat 8080
    echo.
    echo   Melihat proses pemakainya:
    echo     netstat -ano ^| findstr :%PORT%
    echo ==========================================================
    echo.
    pause
    exit /b 1
)

if not exist "storage\installed" (
    echo.
    echo ==========================================================
    echo   Aplikasi belum dipasang.
    echo   Buka di browser:  http://localhost:%PORT%/install
    echo ==========================================================
    echo.
)

REM Scheduler menjalankan backup otomatis, rekap harian Telegram, dan penutupan
REM sesi meja yang waktunya habis. Windows tidak punya cron, jadi ia hidup
REM bersama aplikasi. Hanya dijalankan kalau belum ada, supaya menjalankan
REM start.bat dua kali tidak menumpuk jendela scheduler.
tasklist /FI "WINDOWTITLE eq billiard-scheduler" 2>nul | findstr /I "cmd.exe" >nul
if errorlevel 1 (
    start "billiard-scheduler" /min cmd /c "php artisan schedule:work"
) else (
    echo Scheduler sudah berjalan, tidak dijalankan ulang.
)

echo.
echo Aplikasi berjalan di http://localhost:%PORT%
echo Tekan Ctrl+C untuk berhenti.
echo.

REM --port dipatok supaya PHP melaporkan alasan bind yang sebenarnya, bukan
REM merambat diam-diam ke port berikutnya.
php artisan serve --port=%PORT%
