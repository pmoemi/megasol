<?php

namespace App\Services\Workflow;

use App\Jobs\SendCampaignEmailJob;
use App\Jobs\SendSmsJob;
use App\Models\Customer;
use App\Models\EmailMessage;
use App\Models\SmsMessage;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Services\Campaign\CampaignService;
use App\Services\Email\EmailSendService;
use Illuminate\Support\Facades\Log;

/**
 * Simplified workflow engine inspired by megasol WorkflowEngine.
 *
 * Definition format:
 * {
 *   "steps": [
 *     {"type": "send_sms", "body": "Hello {first_name}"},
 *     {"type": "send_email", "subject": "Hi", "body_html": "<p>Hello</p>"},
 *     {"type": "delay", "minutes": 60}
 *   ]
 * }
 */
class WorkflowEngine
{
    public function __construct(
        protected CampaignService $campaigns,
        protected EmailSendService $email,
    ) {}

    public function runForCustomer(Workflow $workflow, Customer $customer): WorkflowExecution
    {
        $execution = WorkflowExecution::create([
            'workflow_id' => $workflow->id,
            'customer_id' => $customer->id,
            'status' => 'running',
            'started_at' => now(),
            'context' => ['customer_id' => $customer->id],
        ]);

        try {
            foreach ($workflow->definition['steps'] ?? [] as $step) {
                $this->runStep($step, $customer, $execution);
            }

            $execution->update(['status' => 'completed', 'completed_at' => now()]);
            $workflow->update(['last_run_at' => now()]);
        } catch (\Throwable $e) {
            $execution->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            Log::error('Workflow execution failed', [
                'workflow_id' => $workflow->id,
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $execution->fresh();
    }

    /**
     * @param  array<string, mixed>  $step
     */
    protected function runStep(array $step, Customer $customer, WorkflowExecution $execution): void
    {
        $type = $step['type'] ?? '';

        match ($type) {
            'send_sms' => $this->sendSmsStep($step, $customer),
            'send_email' => $this->sendEmailStep($step, $customer),
            'delay' => sleep(min(300, ((int) ($step['minutes'] ?? 0)) * 60)),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $step
     */
    protected function sendSmsStep(array $step, Customer $customer): void
    {
        if (! $customer->phone) {
            return;
        }

        $body = $this->campaigns->mergeTags($step['body'] ?? '', $customer);

        $sms = SmsMessage::create([
            'customer_id' => $customer->id,
            'to' => $customer->phone,
            'body' => $body,
            'direction' => 'outbound',
            'status' => 'queued',
        ]);

        SendSmsJob::dispatch($customer->phone, $body, $sms->id);
    }

    /**
     * @param  array<string, mixed>  $step
     */
    protected function sendEmailStep(array $step, Customer $customer): void
    {
        if (! $customer->email) {
            return;
        }

        $subject = $this->campaigns->mergeTags($step['subject'] ?? 'Message from MegaSol', $customer);
        $html = $this->campaigns->mergeTags($step['body_html'] ?? '', $customer);

        $emailMessage = EmailMessage::create([
            'customer_id' => $customer->id,
            'to' => $customer->email,
            'subject' => $subject,
            'body_html' => $html,
            'direction' => 'outbound',
            'status' => 'queued',
        ]);

        $this->email->sendCampaignEmail($customer->email, $subject, $html, $emailMessage->id);
    }

    public function runScheduledWorkflows(): int
    {
        $count = 0;

        Workflow::query()
            ->where('is_active', true)
            ->where('trigger_type', 'scheduled')
            ->each(function (Workflow $workflow) use (&$count) {
                Customer::query()->chunk(100, function ($customers) use ($workflow, &$count) {
                    foreach ($customers as $customer) {
                        $this->runForCustomer($workflow, $customer);
                        $count++;
                    }
                });
            });

        return $count;
    }
}
