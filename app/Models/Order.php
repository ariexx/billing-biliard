<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, HasUuid, SoftDeletes;
    protected $table = 'orders';
    protected $primaryKey = 'uuid';
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * 'total' sengaja TIDAK ada di sini: tidak ada kolom total di tabel orders.
     * Nilainya dihitung oleh getTotalAttribute(). Mass-assign 'total' hanya
     * menghasilkan "Unknown column 'total'".
     */
    protected $fillable = [
        'order_number',
        'user_uuid',
        'payment_uuid',
        'print_count',
        'paid_at',
        'paid_by_uuid',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->user_uuid = (string) auth()->id();

            if (empty($model->order_number)) {
                $model->order_number = static::generateOrderNumber();
            }
        });
    }

    /**
     * Nomor urut harian: ORD-20260906-0001.
     *
     * Menggantikan rand(1000,9999) yang keyspace-nya hanya 9000 per hari sehingga
     * tabrakan nyaris pasti terjadi dalam hitungan hari. UNIQUE index pada kolom
     * ini adalah pengaman terakhirnya.
     */
    public static function generateOrderNumber(): string
    {
        $prefix = 'ORD-'.date('Ymd').'-';

        for ($percobaan = 0; $percobaan < 10; $percobaan++) {
            $terakhir = static::withTrashed()
                ->where('order_number', 'like', $prefix.'%')
                ->orderByDesc('order_number')
                ->value('order_number');

            $urutan = $terakhir
                ? ((int) substr($terakhir, strlen($prefix))) + 1
                : 1;

            $kandidat = $prefix.str_pad((string) $urutan, 4, '0', STR_PAD_LEFT);

            if (! static::withTrashed()->where('order_number', $kandidat)->exists()) {
                return $kandidat;
            }
        }

        // Sangat jarang: dipakai kalau 10 percobaan berturut-turut bentrok.
        return $prefix.now()->format('His').random_int(100, 999);
    }

    public function getTotalAttribute()
    {
        return $this->orderItems->sum('price');
    }

    public function getIsPaidAttribute(): bool
    {
        return $this->paid_at !== null;
    }

    public function scopeBelumLunas($query)
    {
        return $query->whereNull('paid_at');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Satu order bisa punya beberapa baris active_orders (perpanjangan main bebas
     * membuat baris baru), jadi hasOne di bawah ini mengembalikan baris yang
     * sembarang. Untuk logika bisnis pakai currentSession() atau activeOrders().
     */
    public function activeOrder(): HasOne
    {
        return $this->hasOne(ActiveOrder::class);
    }

    public function activeOrders(): HasMany
    {
        return $this->hasMany(ActiveOrder::class, 'order_uuid', 'uuid');
    }

    /**
     * Sesi meja yang sedang berjalan, atau null kalau semuanya sudah ditutup.
     */
    public function currentSession(): ?ActiveOrder
    {
        return $this->activeOrders()
            ->where('is_active', true)
            ->orderByDesc('started_at')
            ->first();
    }
}
