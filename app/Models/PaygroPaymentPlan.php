<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaygroPaymentPlan extends Model
{
    protected $fillable = [
        'paygro_srl_no',
        'plan_name',
        'product_model',
        'unlock_price',
        'down_payment_price',
        'credit_days_down_payment',
        'credit_packet_price',
        'credit_packet_size',
        'total_payments',
        'credit_type_name',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'paygro_srl_no' => 'integer',
            'unlock_price' => 'decimal:2',
            'down_payment_price' => 'decimal:2',
            'credit_days_down_payment' => 'integer',
            'credit_packet_price' => 'decimal:2',
            'credit_packet_size' => 'integer',
            'total_payments' => 'integer',
            'meta' => 'array',
        ];
    }
}
