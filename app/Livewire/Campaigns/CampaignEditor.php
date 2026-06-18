<?php

namespace App\Livewire\Campaigns;

use App\Jobs\ProcessCampaignJob;
use App\Models\AbTestVariant;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\CustomerList;
use App\Models\EmailTemplate;
use App\Models\MessageTemplate;
use App\Models\Segment;
use App\Helpers\HtmlSanitizer;
use App\Services\Campaign\CampaignService;
use App\Services\Email\EmailSendService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Component;

#[Layout('components.layouts.app')]
class CampaignEditor extends Component
{
    public ?Campaign $campaign = null;

    public string $channel = 'sms';

    public string $type = 'regular';

    public string $name = '';

    public ?int $message_template_id = null;

    public ?int $email_template_id = null;

    public string $subject = '';

    public string $preview_text = '';

    public string $from_name = '';

    public string $from_email = '';

    public string $body = '';

    public string $body_html = '';

    public string $audience_type = 'all';

    public ?int $segment_id = null;

    public ?int $list_id = null;

    public string $payment_status = 'current';

    public string $lifecycle_stage = 'active';

    public ?string $scheduled_at = null;

    public int $sends_per_minute = 60;

    public int $batch_size = 0;

    public int $batch_delay_seconds = 0;

    public bool $send_now = false;

    public int $audience_count = 0;

    // A/B test (email only)
    public string $subjectA = '';

    public string $subjectB = '';

    public int $splitPercentage = 50;

    // Specific-customers audience picker
    /** @var array<int> */
    public array $customer_ids = [];

    public string $customerSearch = '';

    /** @var array<int, array<string, mixed>> */
    public array $customerSearchResults = [];

    // Test email (preview send)
    public string $testEmail = '';

    public bool $testSending = false;

    public ?string $testResult = null;

    public bool $showEmailBuilder = false;

    public string $editorMode = 'visual';

    /** @var array<int, array<string, mixed>> */
    public array $bodyBlocks = [];

    public int $currentStep = 1;

    public int $maxStepReached = 1;

    #[Computed]
    public function getSafeBodyHtmlProperty(): string
    {
        return HtmlSanitizer::sanitize($this->body_html);
    }

    public function mount(?Campaign $campaign = null): void
    {
        $this->campaign = $campaign;

        if ($campaign) {
            $meta = $campaign->audience_meta ?? [];
            $this->fill([
                'channel' => $campaign->channel ?: 'sms',
                'type' => $campaign->type ?: 'regular',
                'name' => $campaign->name,
                'message_template_id' => $campaign->message_template_id,
                'email_template_id' => $campaign->email_template_id,
                'subject' => $campaign->subject ?? '',
                'preview_text' => $campaign->preview_text ?? '',
                'from_name' => $campaign->from_name ?? '',
                'from_email' => $campaign->from_email ?? '',
                'body' => $campaign->body ?? '',
                'body_html' => $campaign->body_html ?? '',
                'audience_type' => $campaign->audience_type,
                'segment_id' => $meta['segment_id'] ?? null,
                'list_id' => $meta['list_id'] ?? null,
                'customer_ids' => array_values(array_map('intval', $meta['customer_ids'] ?? [])),
                'payment_status' => $meta['payment_status'] ?? 'current',
                'lifecycle_stage' => $meta['lifecycle_stage'] ?? 'active',
                'scheduled_at' => $campaign->scheduled_at?->format('Y-m-d\TH:i'),
                'sends_per_minute' => $campaign->sends_per_minute ?: 60,
                'batch_size' => (int) $campaign->batch_size,
                'batch_delay_seconds' => (int) $campaign->batch_delay_seconds,
            ]);
            $this->maxStepReached = 4;

            if ($campaign->type === 'ab_test') {
                $variants = $campaign->abTestVariants;
                $this->subjectA = $variants->firstWhere('variant', 'A')?->subject ?? '';
                $this->subjectB = $variants->firstWhere('variant', 'B')?->subject ?? '';
                $this->splitPercentage = $variants->firstWhere('variant', 'A')?->percentage ?? 50;
            }
        } elseif (request()->query('audience') === 'list' && request()->query('list_id')) {
            $this->audience_type = 'list';
            $this->list_id = (int) request()->query('list_id');
        } elseif (request()->query('audience') === 'segment' && request()->query('segment_id')) {
            $this->audience_type = 'segment';
            $this->segment_id = (int) request()->query('segment_id');
        } elseif (request()->query('audience') === 'customers' && request()->query('customer_id')) {
            $this->audience_type = 'customers';
            $this->customer_ids = [(int) request()->query('customer_id')];
        }

        // Pre-load an email template when arriving from the template gallery
        // ("Use This Template" → /campaigns/create?template=ID).
        if (! $campaign && request()->query('template')) {
            $template = EmailTemplate::find((int) request()->query('template'));
            if ($template) {
                $this->channel = 'email';
                $this->email_template_id = $template->id;
                $this->subject = $template->subject ?? '';
                $this->body_html = $template->body_html ?? '';
                $this->bodyBlocks = is_array($template->blocks) ? $template->blocks : [];
            }
        }

        // Pre-load a message template ("Use in campaign" from the SMS/message
        // templates list → /campaigns/create?message_template=ID). Channel is
        // derived from the template so an email-channel template opens an
        // email campaign with its subject + HTML body.
        if (! $campaign && request()->query('message_template')) {
            $template = MessageTemplate::find((int) request()->query('message_template'));
            if ($template) {
                if (in_array($template->channel, ['email', 'both'], true) && $template->body_html) {
                    $this->channel = 'email';
                    $this->subject = $template->subject ?? '';
                    $this->body_html = $template->body_html ?? '';
                } else {
                    $this->channel = 'sms';
                    $this->message_template_id = $template->id;
                    $this->body = $template->body ?? '';
                }
            }
        }

        // Default the sender to the configured mail "from" when not already set.
        if ($this->from_email === '') {
            $this->from_email = (string) (\App\Models\Setting::get(\App\Support\MailConfigurator::KEY_FROM_ADDRESS) ?: config('mail.from.address', ''));
        }
        if ($this->from_name === '') {
            $this->from_name = (string) (\App\Models\Setting::get(\App\Support\MailConfigurator::KEY_FROM_NAME) ?: config('mail.from.name', ''));
        }

        $this->editorMode = ! empty($this->bodyBlocks) || $this->body_html === '' ? 'visual' : 'html';
        $this->testEmail = auth()->user()->email ?? '';
        $this->updateAudienceCount();
    }

    public function getTitleProperty(): string
    {
        return $this->campaign ? 'Edit Campaign' : 'Create Campaign';
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function getStepsProperty(): array
    {
        return [
            1 => ['key' => 'basics', 'label' => 'Basics'],
            2 => ['key' => 'content', 'label' => 'Content'],
            3 => ['key' => 'audience', 'label' => 'Audience'],
            4 => ['key' => 'schedule', 'label' => 'Schedule'],
        ];
    }

    public function nextStep(): void
    {
        $this->validate($this->rulesForStep($this->currentStep));

        $this->currentStep = min(4, $this->currentStep + 1);
        $this->maxStepReached = max($this->maxStepReached, $this->currentStep);
    }

    public function previousStep(): void
    {
        $this->currentStep = max(1, $this->currentStep - 1);
    }

    public function goToStep(int $step): void
    {
        if ($step >= 1 && $step <= $this->maxStepReached) {
            $this->currentStep = $step;
        }
    }

    public function updatedChannel(): void
    {
        $this->updateAudienceCount();
    }

    public function updatedMessageTemplateId(?int $value): void
    {
        if ($value && $this->channel === 'sms') {
            $template = MessageTemplate::find($value);
            if ($template && $this->body === '') {
                $this->body = $template->body ?? '';
            }
        }
    }

    public function updatedEmailTemplateId(?int $value): void
    {
        if ($this->channel !== 'email') {
            return;
        }

        // Cleared the selection — leave existing content untouched.
        if (! $value) {
            return;
        }

        $template = EmailTemplate::find($value);
        if (! $template) {
            return;
        }

        // Selecting a template loads its content into the editor, replacing
        // whatever was there — otherwise switching templates appears to do
        // nothing once a body already exists.
        $this->subject = $template->subject ?: $this->subject;
        $this->body_html = $template->body_html ?? '';
        $this->bodyBlocks = is_array($template->blocks) ? $template->blocks : [];
        $this->editorMode = ! empty($this->bodyBlocks) ? 'visual' : 'html';

        $template->incrementUsage();
    }

    public function updatedAudienceType(): void
    {
        $this->updateAudienceCount();
    }

    public function updatedSegmentId(): void
    {
        $this->updateAudienceCount();
    }

    public function updatedListId(): void
    {
        $this->updateAudienceCount();
    }

    public function updatedPaymentStatus(): void
    {
        $this->updateAudienceCount();
    }

    public function updatedLifecycleStage(): void
    {
        $this->updateAudienceCount();
    }

    public function updateAudienceCount(): void
    {
        $this->audience_count = app(CampaignService::class)->estimateAudienceCount(
            $this->audience_type,
            $this->audienceMeta(),
            $this->channel === 'email',
        );
    }

    /**
     * Live search for customers as the user types in the specific-customers
     * picker box (requires at least 2 characters).
     */
    public function updatedCustomerSearch(): void
    {
        $term = trim($this->customerSearch);
        if (strlen($term) < 2) {
            $this->customerSearchResults = [];

            return;
        }

        $like = '%'.$term.'%';
        $emailOnly = $this->channel === 'email';

        $this->customerSearchResults = Customer::query()
            ->whereNotIn('id', $this->customer_ids ?: [0])
            ->when($emailOnly,
                fn ($q) => $q->whereNotNull('email')->where('email', '!=', ''),
                fn ($q) => $q->whereNotNull('phone')->where('phone', '!=', ''),
            )
            ->where(function ($q) use ($like) {
                $q->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('account_number', 'like', $like);
            })
            ->limit(10)
            ->get(['id', 'first_name', 'last_name', 'email', 'phone'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => trim(($c->first_name ?? '').' '.($c->last_name ?? '')) ?: ($c->email ?: $c->phone),
                'detail' => $emailOnly ? $c->email : $c->phone,
            ])
            ->toArray();
    }

    public function addSpecificCustomer(int $customerId): void
    {
        if (in_array($customerId, $this->customer_ids, true)) {
            return;
        }

        if (! Customer::whereKey($customerId)->exists()) {
            return;
        }

        $this->customer_ids[] = $customerId;
        $this->customerSearch = '';
        $this->customerSearchResults = [];
        $this->updateAudienceCount();
    }

    public function removeSpecificCustomer(int $customerId): void
    {
        $this->customer_ids = array_values(array_filter(
            $this->customer_ids,
            fn ($id) => (int) $id !== $customerId,
        ));
        $this->updateAudienceCount();
    }

    /**
     * Selected customers' display info, for rendering the picker chip list.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSelectedCustomersListProperty(): array
    {
        if (empty($this->customer_ids)) {
            return [];
        }

        return Customer::whereIn('id', $this->customer_ids)
            ->get(['id', 'first_name', 'last_name', 'email', 'phone'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => trim(($c->first_name ?? '').' '.($c->last_name ?? '')) ?: ($c->email ?: $c->phone),
                'detail' => $this->channel === 'email' ? $c->email : $c->phone,
            ])
            ->toArray();
    }

    /**
     * Human-readable estimate of how long this campaign will take to send,
     * given the audience size, send rate, and batching settings.
     */
    public function getEstimatedSendDurationProperty(): string
    {
        $audience = (int) $this->audience_count;
        if ($audience <= 0) {
            return '';
        }

        $rate = max(1, (int) $this->sends_per_minute);
        $seconds = (int) ceil($audience / max(0.01, $rate / 60));

        if ($this->batch_size > 0 && $this->batch_delay_seconds > 0 && $audience > $this->batch_size) {
            $batches = (int) ceil($audience / $this->batch_size);
            $seconds += max(0, $batches - 1) * $this->batch_delay_seconds;
        }

        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        $parts = [];
        if ($h > 0) {
            $parts[] = $h.'h';
        }
        if ($m > 0 || $h > 0) {
            $parts[] = $m.'m';
        }
        $parts[] = $s.'s';

        return implode(' ', $parts);
    }

    /**
     * Send a test/preview email to the given address. Rate limited to 5/hour.
     */
    public function sendTestEmail(EmailSendService $emailSend): void
    {
        $this->testResult = null;
        $this->testSending = true;

        try {
            $this->validate(['testEmail' => 'required|email:rfc|max:255']);

            if (trim($this->subject) === '') {
                $this->testResult = 'error:Please enter a subject line before sending a test.';

                return;
            }
            if (trim($this->body_html) === '') {
                $this->testResult = 'error:Please add email content before sending a test.';

                return;
            }

            $key = 'campaign-test-email:'.auth()->id();
            if (RateLimiter::tooManyAttempts($key, 5)) {
                $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);
                $this->testResult = "error:You have reached the limit of 5 test emails per hour. Try again in {$minutes} minute(s).";

                return;
            }

            $emailSend->sendCampaignEmail(
                to: $this->testEmail,
                subject: '[TEST] '.$this->subject,
                htmlBody: HtmlSanitizer::sanitize($this->body_html),
                previewText: $this->preview_text ?: null,
                fromEmail: $this->from_email ?: null,
                fromName: $this->from_name ?: null,
            );

            RateLimiter::hit($key, 3600);
            $remaining = 5 - RateLimiter::attempts($key);
            $this->testResult = "success:Test email sent to {$this->testEmail}. ({$remaining} test(s) remaining this hour)";
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->testResult = 'error:'.collect($e->errors())->flatten()->first();
        } catch (\Throwable $e) {
            Log::error('Campaign test email failed', ['error' => $e->getMessage()]);
            $this->testResult = 'error:Failed to send test email. '.$e->getMessage();
        } finally {
            $this->testSending = false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function audienceMeta(): array
    {
        return match ($this->audience_type) {
            'segment' => ['segment_id' => $this->segment_id],
            'list' => ['list_id' => $this->list_id],
            'customers' => ['customer_ids' => array_values(array_map('intval', $this->customer_ids))],
            'payment_status' => ['payment_status' => $this->payment_status],
            'lifecycle' => ['lifecycle_stage' => $this->lifecycle_stage],
            default => [],
        };
    }

    protected function rules(): array
    {
        return array_merge(
            $this->rulesForStep(1),
            $this->rulesForStep(2),
            $this->rulesForStep(3),
            $this->rulesForStep(4),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function rulesForStep(int $step): array
    {
        return match ($step) {
            1 => array_merge(
                [
                    'name' => 'required|string|max:255',
                    'channel' => 'required|in:sms,email',
                    'type' => 'required|in:regular,ab_test',
                ],
                $this->channel === 'email' && $this->type === 'ab_test' ? [
                    'subjectA' => 'required|string|max:255',
                    'subjectB' => 'required|string|max:255',
                    'splitPercentage' => 'required|integer|min:10|max:90',
                ] : [],
            ),
            2 => $this->channel === 'email'
                ? [
                    'subject' => 'required|string|max:255',
                    'body_html' => 'required|string',
                    'from_email' => 'nullable|email|max:255',
                    'from_name' => 'nullable|string|max:255',
                ]
                : [
                    'body' => 'required|string|max:1000',
                ],
            3 => array_merge(
                ['audience_type' => 'required|in:all,segment,list,customers,payment_status,lifecycle'],
                $this->audience_type === 'segment' ? ['segment_id' => 'required|integer|exists:segments,id'] : [],
                $this->audience_type === 'list' ? ['list_id' => 'required|integer|exists:customer_lists,id'] : [],
                $this->audience_type === 'customers' ? ['customer_ids' => 'required|array|min:1'] : [],
            ),
            4 => [
                'scheduled_at' => 'nullable|date',
                'sends_per_minute' => 'integer|min:1|max:600',
                'batch_size' => 'integer|min:0|max:5000',
                'batch_delay_seconds' => 'integer|min:0|max:3600',
            ],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildCampaignData(string $status): array
    {
        $isEmail = $this->channel === 'email';

        return [
            'name' => $this->name,
            'channel' => $this->channel,
            // A/B testing only applies to the email channel.
            'type' => $isEmail ? $this->type : 'regular',
            'subject' => $isEmail ? $this->subject : null,
            'preview_text' => $isEmail ? $this->preview_text : null,
            'from_name' => $isEmail ? ($this->from_name ?: null) : null,
            'from_email' => $isEmail ? ($this->from_email ?: null) : null,
            'message_template_id' => $this->channel === 'sms' ? $this->message_template_id : null,
            'email_template_id' => $isEmail ? $this->email_template_id : null,
            'body' => $this->channel === 'sms' ? $this->body : null,
            // Sanitize stored HTML to prevent stored XSS at render time.
            'body_html' => $isEmail ? HtmlSanitizer::sanitize($this->body_html) : null,
            'audience_type' => $this->audience_type,
            'audience_meta' => $this->audienceMeta(),
            'scheduled_at' => $this->scheduled_at,
            'sends_per_minute' => $this->sends_per_minute,
            'batch_size' => max(0, (int) $this->batch_size),
            'batch_delay_seconds' => max(0, (int) $this->batch_delay_seconds),
            'status' => $status,
        ];
    }

    /**
     * Persist (or clear) the A/B test variant rows for a campaign.
     */
    protected function syncAbVariants(Campaign $campaign): void
    {
        $campaign->abTestVariants()->delete();

        if ($this->channel !== 'email' || $this->type !== 'ab_test') {
            return;
        }

        $html = HtmlSanitizer::sanitize($this->body_html);

        AbTestVariant::create([
            'campaign_id' => $campaign->id,
            'variant' => 'A',
            'subject' => $this->subjectA,
            'body_html' => $html,
            'percentage' => $this->splitPercentage,
        ]);

        AbTestVariant::create([
            'campaign_id' => $campaign->id,
            'variant' => 'B',
            'subject' => $this->subjectB,
            'body_html' => $html,
            'percentage' => 100 - $this->splitPercentage,
        ]);
    }

    public function saveDraft(CampaignService $campaignService): void
    {
        $this->validate($this->rulesForStep(1));

        $data = $this->buildCampaignData('draft');

        if ($this->campaign) {
            $campaign = $campaignService->updateCampaign($this->campaign, $data);
        } else {
            $campaign = $campaignService->createCampaign($data, auth()->user());
            $this->campaign = $campaign;
        }

        $this->syncAbVariants($campaign);

        session()->flash('success', 'Campaign saved as a draft.');
        $this->redirect(route('campaigns.index'), navigate: true);
    }

    public function save(CampaignService $campaignService): void
    {
        $this->validate($this->rules());

        $status = $this->send_now ? 'scheduled' : ($this->scheduled_at ? 'scheduled' : 'draft');
        $data = $this->buildCampaignData($status);

        if ($this->campaign) {
            $campaign = $campaignService->updateCampaign($this->campaign, $data);
        } else {
            $campaign = $campaignService->createCampaign($data, auth()->user());
        }

        $this->syncAbVariants($campaign);

        if ($this->send_now) {
            ProcessCampaignJob::dispatch($campaign->id);
            session()->flash('success', 'Campaign saved and sending has started.');
        } else {
            session()->flash('success', 'Campaign saved successfully.');
        }

        $this->redirect(route('campaigns.index'), navigate: true);
    }

    #[\Livewire\Attributes\On('builder-html-ready')]
    public function useBuilderHtml(string $html, array $blocks = []): void
    {
        $this->body_html = $html;
        $this->bodyBlocks = $blocks;
        $this->editorMode = 'visual';
        $this->showEmailBuilder = false;
        session()->flash('success', 'Email content loaded into campaign.');
    }

    #[\Livewire\Attributes\On('builder-close')]
    public function closeBuilder(): void
    {
        $this->showEmailBuilder = false;
    }

    public function render()
    {
        return view('livewire.campaigns.campaign-editor', [
            'smsTemplates' => MessageTemplate::query()
                ->where('is_active', true)
                ->whereIn('channel', ['sms', 'both'])
                ->orderBy('name')
                ->get(),
            'emailTemplates' => EmailTemplate::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'segments' => Segment::query()->orderBy('name')->get(),
            'lists' => CustomerList::query()
                ->withCount('customers')
                ->orderBy('name')
                ->get(),
        ])->title($this->title);
    }
}
