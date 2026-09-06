@echo off
REM Scheduler menjalankan backup database otomatis (lihat app/Console/Kernel.php).
REM Windows tidak punya cron, jadi schedule:work hidup bersama aplikasi.
start "billiard-scheduler" /min cmd /c "php artisan schedule:work"
php artisan serve
