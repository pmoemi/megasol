<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Segment extends Model
{
    protected $fillable = [
        'name',
        'description',
        'rules',
        'customers_count',
    ];

    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'customers_count' => 'integer',
        ];
    }
}
