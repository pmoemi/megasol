<?php

namespace App\Services\Automation;

use App\Jobs\SendSmsJob;
use App\Models\Automation;
use App\Models\Customer;
use App\Models\SmsMessage;
use App\Services\Campaign\CampaignService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AutomationRunner
{
    public function __construct(
        protected CampaignService $campaignService,
    ) {}

    public function run(Automation $automation): int
    {
        if (! $automation->is_active) {
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

        foreach ($customers as $customer) {
            $body = $this->campaignService->mergeTags($bodyTemplate, $customer);

            $smsMessage = SmsMessage::create([
                'customer_id' => $customer->id,
                'automation_id' => $automation->id,
                'to' => $customer->phone,
                'body' => $body,
                'direction' => 'outbound',
                'status' => 'queued',
            ]);

            SendSmsJob::dispatch(
                to: $customer->phone,
                message: $body,
                smsMessageId: $smsMessage->id,
                meta: [
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
     * @return Collection<int, Customer>
     */
    public function resolveCustomers(Automation $automation): Collection
    {
        $query = match ($automation->type) {
            'payment_reminder' => Customer::query()
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->where('payment_status', 'due_soon'),
            'overdue' => Customer::query()
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->where('payment_status', 'overdue'),
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
            ->each(function (Automation $automation) use (&$total) {
                $total += $this->run($automation);
            });

        return $total;
    }
}
