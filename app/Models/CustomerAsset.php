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

    /**
     * @return array{label: string, color: string}
     */
    public function repaymentStatusMeta(): array
    {
        $status = is_array($this->meta) ? (string) ($this->meta['paygro_repayment_status'] ?? 'active') : 'active';

        return match ($status) {
            'paid_off' => ['label' => 'Fully Paid', 'color' => 'success'],
            'defaulting' => ['label' => 'At Risk', 'color' => 'danger'],
            default => ['label' => 'Active', 'color' => 'info'],
        };
    }

    public function isPaidOff(): bool
    {
        $meta = is_array($this->meta) ? $this->meta : [];

        if (($meta['paygro_repayment_status'] ?? '') === 'paid_off') {
            return true;
        }

        if (array_key_exists('paygro_outstanding_balance', $meta)) {
            return (float) $meta['paygro_outstanding_balance'] <= 0.01;
        }

        return false;
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
