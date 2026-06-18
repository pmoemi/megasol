<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentAssignment extends Model
{
    protected $fillable = [
        'customer_id',
        'agent_id',
        'assigned_by',
        'status',
        'reason',
        'notes',
        'amount_at_assignment',
        'assigned_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_at_assignment' => 'decimal:2',
            'assigned_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /** Statuses considered "open" (active collections work). */
    public const OPEN_STATUSES = ['assigned', 'in_progress', 'promised_to_pay', 'escalated'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function getIsOpenAttribute(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }
}
