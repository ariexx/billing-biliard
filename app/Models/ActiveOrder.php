<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ActiveOrder extends Pivot
{
    protected $table = 'active_orders';
    public $incrementing = false;

    protected $fillable = [
        'order_uuid',
        'product_uuid',
        'hour',
        'is_active',
        'started_at',
        'end_at'
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
