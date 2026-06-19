<?php

namespace App\Services\Automation;

use App\Jobs\SendSmsJob;
use App\Models\Automation;
use App\Models\Customer;
use App\Models\SmsMessage;
use App\Services\Campaign\CampaignService;
use App\Services\Sms\AfricasTalkingSmsService;
use App\Support\AutomationSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AutomationRunner
{
    /**
     * Only state/event-triggered automations run on the recurring scheduler.
     * Promotional/broadcast types (offer, tip, seasonal, …) must be sent
     * deliberately as campaigns — they should never auto-blast the whole base
     * on every hourly run.
     */
    public const SCHEDULED_TYPES = ['payment_reminder', 'overdue', 'welcome'];

    public function __construct(
        protected CampaignService $campaignService,
    ) {}

    /**
     * Whether scheduled SMS automations are globally paused from the settings UI.
     */
    public static function isPaused(): bool
    {
        return AutomationSettings::isPaused();
    }

    public function run(Automation $automation): int
    {
        // Respect the global pause and the configured quiet hours.
        if (! $automation->is_active || AutomationSettings::isPaused() || AutomationSettings::inQuietHours()) {
            return 0;
        }

        $customers = $this->resolveCustomers($automation);
        $template = $automation->messageTemplate;
        $bodyTemplate = $template?->body ?? '';

        if ($bodyTemplate === '') {
            Log::warning('Automation skipped: no message template body', ['automation_id' => $automation->id]);

            return 0;
        }

        $sent = 0;
        $maxPerRun = AutomationSettings::maxPerRun();
        $smsService = app(AfricasTalkingSmsService::class);

        foreach ($customers as $customer) {
            // Safety cap: never send more than the configured maximum per run.
            if ($sent >= $maxPerRun) {
                Log::info('Automation hit per-run send cap', [
                    'automation_id' => $automation->id,
                    'cap' => $maxPerRun,
                ]);

                break;
            }

            if ($customer->sms_opted_out) {
                continue;
            }

            // Cooldown guard: never re-send the same automation to a customer who
            // already received it within the cooldown window. Without this the
            // hourly runner re-blasts the entire matching audience every run.
            if ($this->recentlySent($automation, $customer)) {
                continue;
            }

            $phone = $smsService->resolveRecipientPhone((string) ($customer->phone ?? ''));

            if ($phone === null) {
                continue;
            }

            $body = $this->campaignService->mergeTags($bodyTemplate, $customer);

            $smsMessage = SmsMessage::create([
                'customer_id' => $customer->id,
                'automation_id' => $automation->id,
                'to' => $phone,
                'body' => $body,
                'direction' => 'outbound',
                'status' => 'queued',
                'meta' => ['source' => 'automation', 'automation_id' => $automation->id],
            ]);

            SendSmsJob::dispatch(
                to: $phone,
                message: $body,
                smsMessageId: $smsMessage->id,
                meta: [
                    'source' => 'automation',
                    'automation_id' => $automation->id,
                    'customer_id' => $customer->id,
                ],
            );

            $sent++;
        }

        $automation->update(['last_run_at' => now()]);

        return $sent;
    }

    /**
     * Whether this automation already messaged this customer inside the cooldown
     * window. Keeps the hourly scheduler from re-sending to the same recipient
     * (set sms.automation_cooldown_hours to 0 to disable).
     */
    protected function recentlySent(Automation $automation, Customer $customer): bool
    {
        $hours = AutomationSettings::cooldownHours();

        if ($hours <= 0) {
            return false;
        }

        return SmsMessage::query()
            ->where('automation_id', $automation->id)
            ->where('customer_id', $customer->id)
            ->where('created_at', '>=', now()->subHours($hours))
            ->exists();
    }

    /**
     * @return Collection<int, Customer>
     */
    public function resolveCustomers(Automation $automation): Collection
    {
        $leadDays = AutomationSettings::reminderLeadDays();
        $overdueAfter = AutomationSettings::overdueAfterDays();

        $query = match ($automation->type) {
            // Due soon, with a real upcoming due date (a dateless customer would
            // otherwise get a broken "due on ." message), and — when a lead time
            // is set — due within that many days.
            'payment_reminder' => Customer::query()
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->where('payment_status', 'due_soon')
                ->whereNotNull('next_payment_date')
                ->whereDate('next_payment_date', '>=', now()->startOfDay())
                ->when($leadDays > 0, fn ($q) => $q->whereDate('next_payment_date', '<=', now()->addDays($leadDays))),
            // Overdue, and (when a threshold is set) at least that many days past due.
            'overdue' => Customer::query()
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->where('payment_status', 'overdue')
                ->when($overdueAfter > 0, fn ($q) => $q->where(function ($w) use ($overdueAfter) {
                    $w->whereNull('next_payment_date')
                        ->orWhereDate('next_payment_date', '<=', now()->subDays($overdueAfter));
                })),
            'welcome' => Customer::query()
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->where('activated_at', '>=', now()->subDay()),
            default => $this->campaignService->resolveAudienceQuery(
                $automation->audience_type,
                $automation->audience_meta ?? [],
            ),
        };

        return $query->get();
    }

    public function runAllActive(): int
    {
        $total = 0;

        Automation::query()
            ->where('is_active', true)
            ->whereIn('type', self::SCHEDULED_TYPES)
            ->each(function (Automation $automation) use (&$total) {
                $total += $this->run($automation);
            });

        return $total;
    }
}
