<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $fillable = [
        'name',
        'channel',
        'type',
        'subject',
        'message_template_id',
        'email_template_id',
        'body',
        'body_html',
        'preview_text',
        'from_name',
        'from_email',
        'status',
        'audience_type',
        'audience_meta',
        'scheduled_at',
        'started_at',
        'completed_at',
        'stats',
        'sends_per_minute',
        'batch_size',
        'batch_delay_seconds',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'audience_meta' => 'array',
            'stats' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'sends_per_minute' => 'integer',
            'batch_size' => 'integer',
            'batch_delay_seconds' => 'integer',
        ];
    }

    public function messageTemplate(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class);
    }

    public function emailTemplate(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function smsMessages(): HasMany
    {
        return $this->hasMany(SmsMessage::class);
    }

    public function emailMessages(): HasMany
    {
        return $this->hasMany(EmailMessage::class);
    }

    public function abTestVariants(): HasMany
    {
        return $this->hasMany(AbTestVariant::class);
    }

    public function campaignLinks(): HasMany
    {
        return $this->hasMany(CampaignLink::class);
    }

    public function isEmail(): bool
    {
        return ($this->channel ?: 'sms') === 'email';
    }

    public function isAbTest(): bool
    {
        return $this->type === 'ab_test';
    }

    public function getSentAtAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->completed_at ?? $this->started_at;
    }

    public function getSentCountAttribute(): int
    {
        return (int) ($this->stats['sent_count'] ?? $this->recipients()->where('status', 'sent')->count());
    }

    public function getDeliveredCountAttribute(): int
    {
        return (int) ($this->stats['delivered_count'] ?? $this->recipients()->where('status', 'delivered')->count());
    }

    public function getFailedCountAttribute(): int
    {
        return (int) ($this->stats['failed_count'] ?? $this->recipients()->where('status', 'failed')->count());
    }

    public function getDeliveryRateAttribute(): float
    {
        $total = $this->sent_count;
        if ($total === 0) {
            return 0.0;
        }
        return round(($this->delivered_count / $total) * 100, 1);
    }

    public function getOpenedCountAttribute(): int
    {
        return (int) ($this->stats['opened'] ?? $this->recipients()->whereNotNull('opened_at')->count());
    }

    public function getClickedCountAttribute(): int
    {
        return (int) ($this->stats['clicked'] ?? $this->recipients()->whereNotNull('clicked_at')->count());
    }

    public function getOpenRateAttribute(): float
    {
        $base = $this->sent_count ?: $this->recipients()->whereIn('status', ['sent', 'delivered'])->count();
        if ($base === 0) {
            return 0.0;
        }
        return round(($this->opened_count / $base) * 100, 1);
    }

    public function getClickRateAttribute(): float
    {
        $base = $this->sent_count ?: $this->recipients()->whereIn('status', ['sent', 'delivered'])->count();
        if ($base === 0) {
            return 0.0;
        }
        return round(($this->clicked_count / $base) * 100, 1);
    }
}
