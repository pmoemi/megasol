<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workflow extends Model
{
    protected $fillable = [
        'name',
        'description',
        'trigger_type',
        'schedule_cron',
        'definition',
        'is_active',
        'created_by',
        'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(WorkflowExecution::class);
    }

    /** Computed status string for UI display. */
    public function getStatusAttribute(): string
    {
        return $this->is_active ? 'active' : 'inactive';
    }

    /** Alias so the blade can use $workflow->trigger_subtype. */
    public function getTriggerSubtypeAttribute(): string
    {
        return $this->trigger_type ?? 'manual';
    }

    /** Placeholder success-rate until we compute from executions. */
    public function getSuccessRateAttribute(): float
    {
        $total   = $this->executions()->count();
        $success = $this->executions()->where('status', 'completed')->count();

        return $total > 0 ? round(($success / $total) * 100, 1) : 0.0;
    }
}
