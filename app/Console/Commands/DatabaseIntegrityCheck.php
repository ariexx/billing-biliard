<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pemeriksaan pra-migrasi. Skema lama membiarkan order_items dan active_orders
 * tanpa PRIMARY KEY dan order_number tanpa UNIQUE, jadi data produksi bisa sudah
 * mengandung duplikat yang membuat migrasi penambahan constraint gagal.
 *
 * Jalankan tanpa flag untuk melihat laporan, lalu --fix untuk membereskannya.
 */
class DatabaseIntegrityCheck extends Command
{
    protected $signature = 'db:integrity-check {--fix : Perbaiki duplikat yang ditemukan}';

    protected $description = 'Periksa duplikat dan data ganjil sebelum migrasi constraint dijalankan';

    private int $masalah = 0;

    public function handle(): int
    {
        $koneksi = config('database.default');
        $this->line('Memeriksa database: '.config("database.connections.{$koneksi}.database"));
        $this->newLine();

        $this->cekOrderItems();
        $this->cekOrderNumber();
        $this->cekActiveOrders();
        $this->cekHourTypeKosong();

        $this->newLine();

        if ($this->masalah === 0) {
            $this->info('Bersih. Migrasi constraint aman dijalankan.');

            return self::SUCCESS;
        }

        if (! $this->option('fix')) {
            $this->warn("Ditemukan {$this->masalah} masalah. Jalankan ulang dengan --fix untuk memperbaiki.");
            $this->warn('Pastikan backup terbaru sudah ada: php artisan backup:database');

            return self::FAILURE;
        }

        $this->info('Perbaikan selesai. Jalankan ulang tanpa --fix untuk memastikan sudah bersih.');

        return self::SUCCESS;
    }

    private function duplikat(string $table, string $column)
    {
        return DB::table($table)
            ->select($column, DB::raw('COUNT(*) as jumlah'))
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1')
            ->get();
    }

    private function laporkan(string $label, $duplikat): bool
    {
        if ($duplikat->isEmpty()) {
            $this->info("OK  {$label} tidak ada duplikat");

            return false;
        }

        $this->masalah += $duplikat->count();
        $this->error("!!  {$label}: {$duplikat->count()} nilai duplikat");

        return true;
    }

    /**
     * order_items tidak punya primary key, jadi tidak ada cara mengalamati satu
     * baris duplikat dari Eloquent. Perbaikannya harus SQL mentah dengan LIMIT;
     * MySQL mengevaluasi UUID() per baris sehingga tiap baris dapat nilai unik.
     */
    private function cekOrderItems(): void
    {
        $duplikat = $this->duplikat('order_items', 'uuid');

        if (! $this->laporkan('order_items.uuid', $duplikat)) {
            return;
        }

        foreach ($duplikat->take(10) as $baris) {
            $this->line("      {$baris->uuid} muncul {$baris->jumlah}x");
        }

        $this->line('      Dampak: $item->delete() menghapus SEMUA baris ber-uuid sama.');

        if (! $this->option('fix')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            $this->warn('      --fix untuk order_items hanya didukung di MySQL.');

            return;
        }

        foreach ($duplikat as $baris) {
            DB::update(
                'UPDATE order_items SET uuid = UUID() WHERE uuid = ? LIMIT '.((int) $baris->jumlah - 1),
                [$baris->uuid]
            );
            $this->line("      {$baris->uuid}: ".($baris->jumlah - 1).' baris diberi uuid baru');
        }
    }

    private function cekOrderNumber(): void
    {
        $duplikat = $this->duplikat('orders', 'order_number');

        if (! $this->laporkan('orders.order_number', $duplikat)) {
            return;
        }

        foreach ($duplikat->take(10) as $baris) {
            $this->line("      {$baris->order_number} muncul {$baris->jumlah}x");
        }

        $this->line('      Dampak: dua struk tercetak dengan nomor yang sama.');

        if (! $this->option('fix')) {
            return;
        }

        // orders punya primary key uuid, jadi barisnya bisa dialamati persis.
        // Order tertua mempertahankan nomornya; yang lebih baru diberi akhiran.
        foreach ($duplikat as $baris) {
            $rows = DB::table('orders')
                ->where('order_number', $baris->order_number)
                ->orderBy('created_at')
                ->pluck('uuid')
                ->skip(1)
                ->values();

            foreach ($rows as $i => $uuid) {
                $nomorBaru = $baris->order_number.'-R'.($i + 1);
                DB::table('orders')->where('uuid', $uuid)->update(['order_number' => $nomorBaru]);
                $this->line("      {$uuid} -> {$nomorBaru}");
            }
        }
    }

    private function cekActiveOrders(): void
    {
        // unique_id sudah punya UNIQUE index sejak 2022_12_18, jadi ini hanya
        // jaring pengaman kalau index-nya pernah di-drop manual.
        $this->laporkan('active_orders.unique_id', $this->duplikat('active_orders', 'unique_id'));
    }

    private function cekHourTypeKosong(): void
    {
        $jumlah = DB::table('active_orders')->whereNull('hour_type')->count();

        if ($jumlah === 0) {
            $this->info('OK  active_orders.hour_type tidak ada yang kosong');

            return;
        }

        $this->masalah++;
        $this->error("!!  active_orders.hour_type kosong pada {$jumlah} baris");
        $this->line('      Dampak: baris ini ditutup paksa oleh dashboard tanpa pernah ditagih.');

        if ($this->option('fix')) {
            DB::table('active_orders')->whereNull('hour_type')->update(['hour_type' => 'regular']);
            $this->line("      {$jumlah} baris diisi 'regular'");
        }
    }
}
