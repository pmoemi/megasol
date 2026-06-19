<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsMessage extends Model
{
    /** @var array<int, string> */
    public const SUCCESS_STATUSES = ['sent', 'delivered', 'success', 'processed', 'submitted'];

    /** @var array<int, string> */
    public const TEST_SOURCES = ['settings_test', 'terminal_test'];

    protected $fillable = [
        'customer_id',
        'campaign_id',
        'campaign_recipient_id',
        'automation_id',
        'to',
        'from',
        'body',
        'direction',
        'status',
        'provider_message_id',
        'provider_response',
        'cost',
        'error_message',
        'meta',
        'sent_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'provider_response' => 'array',
            'meta' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function campaignRecipient(): BelongsTo
    {
        return $this->belongsTo(CampaignRecipient::class);
    }

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    /**
     * Outbound messages that represent real product activity (not gateway tests).
     */
    public function scopeForReporting(Builder $query): Builder
    {
        return $query->where('direction', 'outbound')->excludingTests();
    }

    public function scopeExcludingTests(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $q) {
                $q->whereNull('meta->cli_test')
                    ->orWhere('meta->cli_test', false);
            })
            ->where(function (Builder $q) {
                $q->whereNull('meta->source')
                    ->orWhereNotIn('meta->source', self::TEST_SOURCES);
            })
            ->where(function (Builder $q) {
                $q->whereNotNull('customer_id')
                    ->orWhereNotNull('campaign_id')
                    ->orWhereNotNull('automation_id')
                    ->orWhereIn('meta->source', ['campaign', 'automation', 'workflow', 'profile', 'paygro_latest_token', 'inbound_reply']);
            });
    }

    public function scopeSuccessfullySent(Builder $query): Builder
    {
        return $query->whereIn('status', self::SUCCESS_STATUSES);
    }

    public function scopeSentOnDate(Builder $query, string $date): Builder
    {
        return $query->where(function (Builder $q) use ($date) {
            $q->whereDate('sent_at', $date)
                ->orWhere(function (Builder $inner) use ($date) {
                    $inner->whereNull('sent_at')
                        ->whereDate('created_at', $date)
                        ->whereIn('status', self::SUCCESS_STATUSES);
                });
        });
    }

    public function isTestMessage(): bool
    {
        $meta = is_array($this->meta) ? $this->meta : [];

        if (! empty($meta['cli_test'])) {
            return true;
        }

        $source = $meta['source'] ?? null;

        return is_string($source) && in_array($source, self::TEST_SOURCES, true);
    }

    public function sourceLabel(): string
    {
        $meta = is_array($this->meta) ? $this->meta : [];
        $source = $meta['source'] ?? null;

        return match ($source) {
            'campaign' => 'Campaign',
            'paygro_latest_token' => 'Token SMS',
            'profile' => 'Customer profile',
            'settings_test' => 'Settings test',
            'terminal_test' => 'Terminal test',
            'automation' => 'Automation',
            'workflow' => 'Workflow',
            'inbound_reply' => 'Auto-reply',
            default => $this->campaign_id
                ? 'Campaign'
                : ($source ? ucfirst(str_replace('_', ' ', (string) $source)) : 'Direct'),
        };
    }
}
