<?php

namespace App\Models;

use App\Services\Integrations\PayGroService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'account_number',
        'first_name',
        'last_name',
        'phone',
        'email',
        'product_type',
        'location',
        'payment_status',
        'next_payment_date',
        'outstanding_balance',
        'lifecycle_stage',
        'account_status',
        'token_balance',
        'sms_opted_out',
        'assigned_agent_id',
        'activated_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'next_payment_date' => 'date',
            'outstanding_balance' => 'decimal:2',
            'token_balance' => 'integer',
            'sms_opted_out' => 'boolean',
            'activated_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function smsMessages(): HasMany
    {
        return $this->hasMany(SmsMessage::class);
    }

    public function emailMessages(): HasMany
    {
        return $this->hasMany(EmailMessage::class);
    }

    public function customerLists(): BelongsToMany
    {
        return $this->belongsToMany(CustomerList::class, 'customer_list_members');
    }

    public function campaignRecipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class)->latest('paid_at');
    }

    public function tokenTransactions(): HasMany
    {
        return $this->hasMany(TokenTransaction::class)->latest('occurred_at');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(CustomerAsset::class);
    }

    public function repaymentSchedules(): HasMany
    {
        return $this->hasMany(RepaymentSchedule::class)->orderBy('installment_number');
    }

    public function agentAssignments(): HasMany
    {
        return $this->hasMany(AgentAssignment::class)->latest('assigned_at');
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(AgentAssignment::class)
            ->whereIn('status', AgentAssignment::OPEN_STATUSES)
            ->latest('assigned_at');
    }

    /**
     * Days past due when the customer has no token credit remaining.
     * Hire Purchase: days beyond the plan grace period or past next due date.
     * Daily PAYGO: days since the last recorded payment.
     */
    public function getDaysInArrearsAttribute(): int
    {
        if ($this->token_balance > 0) {
            return 0;
        }

        $computed = app(PayGroService::class)->computeDaysInArrears(
            $this->relationLoaded('assets') ? $this : $this->load('assets')
        );

        if ($computed > 0) {
            return $computed;
        }

        if ($this->isHirePurchaseAccount() && $this->next_payment_date?->isPast()) {
            return (int) $this->next_payment_date->startOfDay()->diffInDays(now()->startOfDay());
        }

        return 0;
    }

    /**
     * Whether this customer is on a Hire Purchase plan (installment/arrears model).
     */
    public function isHirePurchaseAccount(): bool
    {
        $meta = is_array($this->meta) ? $this->meta : [];

        if (array_key_exists('paygro_has_hire_purchase', $meta)) {
            return (bool) $meta['paygro_has_hire_purchase'];
        }

        foreach ($this->assets as $asset) {
            $assetMeta = is_array($asset->meta) ? $asset->meta : [];
            $creditType = strtolower((string) (
                $assetMeta['paygro_payment_credit_type']
                ?? $assetMeta['paygro_plan_credit_type']
                ?? ''
            ));

            if (
                str_contains($creditType, 'hire purchase')
                || str_contains($creditType, 'higher purchase')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Total paid to date across all recorded payments.
     */
    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    /**
     * Human label + badge colour for the account status.
     *
     * @return array{label: string, color: string}
     */
    public function accountStatusMeta(): array
    {
        return match ($this->account_status) {
            'paid_off' => ['label' => 'Paid Off', 'color' => 'success'],
            'defaulting' => ['label' => 'Defaulting', 'color' => 'danger'],
            'written_off' => ['label' => 'Written Off', 'color' => 'muted'],
            default => ['label' => 'Active', 'color' => 'info'],
        };
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getInitialsAttribute(): string
    {
        $first = strtoupper(substr($this->first_name ?? '', 0, 1));
        $last  = strtoupper(substr($this->last_name ?? '', 0, 1));
        return ($first . $last) ?: '?';
    }
}
