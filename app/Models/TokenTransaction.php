<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TokenTransaction extends Model
{
    protected $fillable = [
        'customer_id',
        'customer_payment_id',
        'type',
        'tokens',
        'days',
        'balance_after',
        'source',
        'external_reference',
        'token_value',
        'product_serial_number',
        'token_tag',
        'meta',
        'description',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'tokens' => 'integer',
            'days' => 'integer',
            'balance_after' => 'integer',
            'meta' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(CustomerPayment::class, 'customer_payment_id');
    }
}
