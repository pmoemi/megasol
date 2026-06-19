<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepaymentSchedule extends Model
{
    public const ENTRY_INSTALLMENT = 'installment';

    public const ENTRY_PAYMENT = 'payment';

    public const ENTRY_PLAN = 'plan';

    protected $fillable = [
        'customer_id',
        'customer_asset_id',
        'entry_type',
        'installment_number',
        'due_date',
        'amount_due',
        'amount_paid',
        'status',
        'paid_at',
        'source',
        'external_reference',
        'sales_identifier',
        'payment_plan_name',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'installment_number' => 'integer',
            'due_date' => 'date',
            'amount_due' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'paid_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CustomerAsset::class, 'customer_asset_id');
    }

    public function getBalanceAttribute(): float
    {
        return max(0, (float) $this->amount_due - (float) $this->amount_paid);
    }

    public function isPlanSummary(): bool
    {
        return $this->entry_type === self::ENTRY_PLAN;
    }
}
