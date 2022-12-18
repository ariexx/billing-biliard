<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ActiveOrder extends Pivot
{
    protected $table = 'active_orders';
    public $incrementing = false;
    protected $primaryKey = 'order_uuid';
    protected $keyType = 'string';

    protected $fillable = [
        'order_uuid',
        'product_uuid',
        'hour',
        'unique_id',
        'is_active',
        'started_at',
        'end_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'started_at' => 'datetime',
        'end_at' => 'datetime'
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->unique_id = uniqid();
        });
    }

    //get attributes how much time in started_at and end_at
    public function getDurationAttribute(): int
    {
        return $this->started_at->diffInMinutes($this->end_at);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): HasOne
    {
        return $this->hasOne(OrderItem::class, 'active_order_unique_id', 'unique_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
