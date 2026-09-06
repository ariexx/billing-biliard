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

    protected $fillable = [
        'order_number',
        'user_uuid',
        'payment_uuid',
        'total',
        'print_count'
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->user_uuid = auth()->id();
            $model->order_number = 'ORD-'.date('Ymd').'-'.rand(1000, 9999);
        });
    }

    public function getTotalAttribute()
    {
        return $this->orderItems->sum('price');
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
