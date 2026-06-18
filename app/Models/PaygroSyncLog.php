<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaygroSyncLog extends Model
{
    protected $fillable = [
        'sync_type',
        'status',
        'attempts',
        'duration_ms',
        'source',
        'session_refreshed',
        'last_http_status',
        'records_processed',
        'records_failed',
        'payload',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'duration_ms' => 'integer',
            'session_refreshed' => 'boolean',
            'last_http_status' => 'integer',
            'records_processed' => 'integer',
            'records_failed' => 'integer',
            'payload' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
