<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAsset extends Model
{
    protected $fillable = [
        'customer_id',
        'unit_serial',
        'product_name',
        'model',
        'installation_date',
        'warranty_expiry',
        'status',
        'notes',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'installation_date' => 'date',
            'warranty_expiry' => 'date',
            'meta' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function getIsUnderWarrantyAttribute(): bool
    {
        return $this->warranty_expiry !== null && $this->warranty_expiry->isFuture();
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->whereNull('customer_id');
    }

    public function scopeAssigned(Builder $query): Builder
    {
        return $query->whereNotNull('customer_id');
    }
}
