<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, HasUuid, SoftDeletes;
    protected $table = 'products';
    protected $primaryKey = 'uuid';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'type',
        'name',
        'product_code',
        'price',
    ];

    //uc first for type
    public function getTypeAttribute($value): string
    {
        return ucfirst($value);
    }

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

    public function product(): HasOne
    {
        return $this->hasOne(ActiveOrder::class);
    }

    public function activeOrder(): HasOne
    {
        return $this->hasOne(ActiveOrder::class);
    }

    public function scopeBilliard($query)
    {
        return $query->where('type', 'billiard');
    }

    public function scopeDrink($query)
    {
        return $query->where('type', 'drink');
    }

    public function getActiveOrderBilliardAttribute()
    {
        return $this->activeOrder()->where('is_active', true)->whereHas('product', function ($query) {
            $query->where('type', 'billiard');
        })->first();
    }
}
