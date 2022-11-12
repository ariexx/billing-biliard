<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, HasUuid, SoftDeletes;
    protected $table = 'products';
    protected $primaryKey = 'uuid';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $guarded = ['*'];

    public function hours(): BelongsToMany
    {
        return $this->belongsToMany(Hour::class, 'hours_to_products',
            'product_uuid',
            'hour_uuid')
            ->withTimestamps();
    }

    public function hourPrice(): BelongsToMany
    {
        return $this->belongsToMany(Hour::class, 'hours_to_products',
            'product_uuid',
            'hour_uuid')
            ->withPivot('price')
            ->withTimestamps();
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
