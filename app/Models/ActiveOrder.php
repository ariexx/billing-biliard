<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ActiveOrder extends Pivot
{
    protected $table = 'active_orders';
    public $incrementing = false;

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
