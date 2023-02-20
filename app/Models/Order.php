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

    public function activeOrder(): HasOne
    {
        return $this->hasOne(ActiveOrder::class);
    }
}
