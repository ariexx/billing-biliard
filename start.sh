#!/usr/bin/env bash
# ---------------------------------------------------------------
#  Billing Biliar - jalankan aplikasi (Git Bash)
#
#  Padanan start.bat untuk Git Bash. Dibuat karena pada sebagian mesin
#  Windows `php artisan serve` gagal bind dari cmd/PowerShell tetapi
#  berjalan normal dari Git Bash.
# ---------------------------------------------------------------
set -u

cd "$(dirname "$0")"

PORT="${1:-8000}"

# Git Bash tidak me-resolve `php` dari php.bat saat dijalankan di dalam skrip,
# jadi binari php.exe dicari sendiri. Bisa ditimpa: PHP_BIN=/path/php.exe ./start.sh
if [ -z "${PHP_BIN:-}" ]; then
    if command -v php.exe >/dev/null 2>&1; then
        PHP_BIN="$(command -v php.exe)"
    else
        PHP_BIN="$(ls -d "$HOME"/.config/herd/bin/php*/php.exe 2>/dev/null | sort -r | head -1)"
    fi
fi

if [ -z "${PHP_BIN:-}" ] || [ ! -x "$PHP_BIN" ]; then
    echo "PHP tidak ditemukan. Jalankan dengan menyebut lokasinya:" >&2
    echo "  PHP_BIN=/c/path/ke/php.exe ./start.sh" >&2
    exit 1
fi

echo "PHP: $("$PHP_BIN" -v 2>/dev/null | head -1)"

# Aplikasi tidak bisa boot tanpa .env dan APP_KEY, sedangkan wizard pemasangan
# sendiri berjalan di dalam aplikasi. Jadi keduanya disiapkan lebih dulu.
if [ ! -f .env ]; then
    echo "Menyiapkan berkas konfigurasi awal..."
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    echo "Membuat kunci aplikasi..."
    "$PHP_BIN" artisan key:generate --force >/dev/null
fi

# Berhenti dengan pesan jelas kalau port sudah dipakai, bukan merambat diam-diam
# ke port berikutnya seperti perilaku bawaan `artisan serve`.
if netstat -ano 2>/dev/null | grep -qE "LISTENING" && \
   netstat -ano 2>/dev/null | grep -q "127.0.0.1:${PORT} .*LISTENING"; then
    echo
    echo "=========================================================="
    echo "  Port ${PORT} sudah dipakai."
    echo
    echo "  Kemungkinan aplikasi ini SUDAH berjalan. Coba buka:"
    echo "    http://localhost:${PORT}"
    echo
    echo "  Atau jalankan di port lain:   ./start.sh 8080"
    echo "=========================================================="
    echo
    exit 1
fi

if [ ! -f storage/installed ]; then
    echo
    echo "=========================================================="
    echo "  Aplikasi belum dipasang."
    echo "  Buka di browser:  http://localhost:${PORT}/install"
    echo "=========================================================="
    echo
fi

# Scheduler menjalankan backup otomatis, rekap harian Telegram, dan penutupan
# sesi meja yang waktunya habis. Windows tidak punya cron, jadi ia hidup bersama
# aplikasi. Dimatikan otomatis saat skrip ini dihentikan.
"$PHP_BIN" artisan schedule:work >/dev/null 2>&1 &
SCHEDULER_PID=$!
trap 'kill "$SCHEDULER_PID" 2>/dev/null' EXIT INT TERM

echo
echo "Aplikasi berjalan di http://localhost:${PORT}"
echo "Scheduler berjalan (PID ${SCHEDULER_PID})."
echo "Tekan Ctrl+C untuk berhenti."
echo

"$PHP_BIN" artisan serve --port="${PORT}"
