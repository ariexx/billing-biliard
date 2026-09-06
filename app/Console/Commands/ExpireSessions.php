<?php

namespace App\Console\Commands;

use App\Models\ActiveOrder;
use Illuminate\Console\Command;

/**
 * Menutup blok jam reguler yang waktunya sudah habis.
 *
 * Sebelumnya pekerjaan ini dilakukan dari dalam Blade (active-order.blade.php
 * memanggil $order->update() saat render), sehingga sesi hanya kadaluarsa selama
 * ada browser yang membuka dashboard. Kalau kasir menutup browser, mejanya tetap
 * berstatus terpakai selamanya dan tidak bisa dipesan lagi.
 *
 * Sesi main bebas sengaja TIDAK disentuh: penutupannya harus lewat stopTimer
 * supaya tagihannya dihitung.
 */
class ExpireSessions extends Command
{
    protected $signature = 'orders:expire-sessions';

    protected $description = 'Tutup sesi meja reguler yang waktunya sudah habis';

    public function handle(): int
    {
        $expired = ActiveOrder::where('is_active', true)
            ->where('hour_type', 'regular')
            ->where('end_at', '<=', now())
            ->get();

        foreach ($expired as $session) {
            $session->update(['is_active' => false]);
        }

        if ($expired->isNotEmpty()) {
            $message = 'Sesi meja kadaluarsa ditutup: '.$expired->count();
            $this->info($message);
            \Log::channel('daily')->info($message);
        }

        return self::SUCCESS;
    }
}
