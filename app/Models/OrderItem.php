<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderItem extends Model
{
    use HasFactory, HasUuid, SoftDeletes;
    protected $table = 'order_items';
    protected $primaryKey = 'uuid';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'order_uuid',
        'product_uuid',
        'quantity',
        'price',
        'active_order_unique_id',
        'hour',
        'void_reason',
        'voided_by_uuid',
    ];

    /**
     * Baris waktu biliar, bukan minuman atau snack.
     *
     * Penandanya active_order_unique_id: baris waktu SELALU terhubung ke satu
     * sesi di active_orders, sedangkan minuman tidak pernah. Tipe produk dipakai
     * sebagai cadangan untuk baris lama dari sebelum kolom itu ada.
     */
    public function isBarisWaktu(): bool
    {
        return $this->active_order_unique_id !== null
            || $this->product?->getRawOriginal('type') === 'billiard';
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_uuid', 'uuid');
    }

    /**
     * Lama main sebenarnya, khusus sesi main bebas.
     *
     * Kolom `hour` pada baris main bebas berisi angka paket yang diisi admin
     * (biasanya 1), bukan lama main -- menampilkannya apa adanya membingungkan
     * kasir dan pelanggan. Untuk sesi yang sudah ditutup, end_at diisi waktu
     * penutupan oleh stopTimer(); untuk yang masih jalan, dihitung sampai
     * sekarang.
     */
    public function durasiMenit(): ?int
    {
        $sesi = $this->activeOrder;

        if (! $sesi || $sesi->hour_type !== 'free time') {
            return null;
        }

        $selesai = $sesi->is_active ? now() : $sesi->end_at;

        return max(0, (int) $sesi->started_at->diffInMinutes($selesai));
    }

    /**
     * Teks durasi untuk halaman order dan struk.
     */
    public function labelDurasi(): string
    {
        $menit = $this->durasiMenit();

        if ($menit !== null) {
            return intdiv($menit, 60).' jam '.($menit % 60).' menit';
        }

        return $this->hour ? $this->hour.' Jam' : '-';
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function activeOrder(): BelongsTo
    {
        return $this->belongsTo(ActiveOrder::class, 'active_order_unique_id', 'unique_id');
    }

    /**
     * withTrashed() supaya struk lama tetap bisa dicetak setelah produknya
     * dihapus. Tanpa ini relasinya null dan view struk fatal error.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
