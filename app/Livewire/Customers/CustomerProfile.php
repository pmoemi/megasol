<?php

namespace App\Livewire\Customers;

use App\Jobs\SendSmsJob;
use App\Models\AgentAssignment;
use App\Models\Customer;
use App\Models\CustomerAsset;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\Integrations\PayGroService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class CustomerProfile extends Component
{
    public Customer $customer;

    #[Url]
    public string $tab = 'overview';

    // ── Collections assignment form ──────────────────────────────────────
    public bool $showAssignForm = false;

    public ?int $assignAgentId = null;

    public string $assignReason = '';

    public string $assignNotes = '';

    public ?int $updateAssignmentId = null;

    public string $assignmentStatus = '';

    // ── Asset (unit) registration form ───────────────────────────────────
    public bool $showAssetForm = false;

    public ?int $editingAssetId = null;

    public string $assetUnitSerial = '';

    public string $assetProductName = '';

    public string $assetModel = '';

    public string $assetInstallationDate = '';

    public string $assetWarrantyExpiry = '';

    public string $assetStatus = 'active';

    public string $assetNotes = '';

    // ── Send SMS (outbound, two-way conversation) ────────────────────────
    public string $smsBody = '';

    // ── PayGro latest-token lookup ───────────────────────────────────────
    public array $latestPayGroToken = [];

    public ?string $payGroTokenStatus = null;

    public bool $payGroTokenStatusIsError = false;

    public array $tabs = [
        'overview' => 'Overview',
        'payments' => 'Payments',
        'tokens' => 'Tokens',
        'schedule' => 'Schedule',
        'assets' => 'Assets',
        'messages' => 'Messages',
        'collections' => 'Collections',
    ];

    public function mount(Customer $customer): void
    {
        $this->customer = $customer;

        if (! array_key_exists($this->tab, $this->tabs)) {
            $this->tab = 'overview';
        }
    }

    public function getTitleProperty(): string
    {
        return $this->customer->full_name.' — Customer 360';
    }

    public function setTab(string $tab): void
    {
        if (array_key_exists($tab, $this->tabs)) {
            $this->tab = $tab;
        }
    }

    // ── Collections actions ──────────────────────────────────────────────

    public function assignToAgent(): void
    {
        $this->validate([
            'assignAgentId' => 'required|integer|exists:users,id',
            'assignReason' => 'nullable|string|max:255',
            'assignNotes' => 'nullable|string|max:1000',
        ]);

        AgentAssignment::create([
            'customer_id' => $this->customer->id,
            'agent_id' => $this->assignAgentId,
            'assigned_by' => auth()->id(),
            'status' => 'assigned',
            'reason' => $this->assignReason ?: null,
            'notes' => $this->assignNotes ?: null,
            'amount_at_assignment' => $this->customer->outstanding_balance,
            'assigned_at' => now(),
        ]);

        $this->customer->update(['assigned_agent_id' => $this->assignAgentId]);

        $this->reset(['showAssignForm', 'assignAgentId', 'assignReason', 'assignNotes']);
        $this->customer->refresh();
        $this->dispatch('toast', type: 'success', message: 'Customer assigned to field agent.');
    }

    public function updateAssignmentStatus(int $assignmentId, string $status): void
    {
        $assignment = AgentAssignment::where('customer_id', $this->customer->id)->findOrFail($assignmentId);

        $valid = ['assigned', 'in_progress', 'promised_to_pay', 'resolved', 'escalated', 'written_off'];
        if (! in_array($status, $valid, true)) {
            return;
        }

        $assignment->update([
            'status' => $status,
            'resolved_at' => in_array($status, ['resolved', 'written_off'], true) ? now() : null,
        ]);

        // Clear the active agent when the case is closed.
        if (in_array($status, ['resolved', 'written_off'], true)
            && $this->customer->assigned_agent_id === $assignment->agent_id) {
            $this->customer->update(['assigned_agent_id' => null]);
        }

        if ($status === 'written_off') {
            $this->customer->update(['account_status' => 'written_off']);
        }

        $this->customer->refresh();
        $this->dispatch('toast', type: 'success', message: 'Assignment updated.');
    }

    // ── Asset (unit) management ──────────────────────────────────────────

    public function newAsset(): void
    {
        $this->resetAssetForm();
        $this->showAssetForm = true;
    }

    public function editAsset(int $assetId): void
    {
        $asset = $this->customer->assets()->findOrFail($assetId);

        $this->editingAssetId = $asset->id;
        $this->assetUnitSerial = $asset->unit_serial;
        $this->assetProductName = $asset->product_name ?? '';
        $this->assetModel = $asset->model ?? '';
        $this->assetInstallationDate = $asset->installation_date?->format('Y-m-d') ?? '';
        $this->assetWarrantyExpiry = $asset->warranty_expiry?->format('Y-m-d') ?? '';
        $this->assetStatus = $asset->status;
        $this->assetNotes = $asset->notes ?? '';
        $this->showAssetForm = true;
    }

    public function saveAsset(): void
    {
        $validated = $this->validate([
            'assetUnitSerial' => 'required|string|max:255',
            'assetProductName' => 'nullable|string|max:255',
            'assetModel' => 'nullable|string|max:255',
            'assetInstallationDate' => 'nullable|date',
            'assetWarrantyExpiry' => 'nullable|date',
            'assetStatus' => 'required|in:active,faulty,repossessed,returned,decommissioned',
            'assetNotes' => 'nullable|string|max:1000',
        ]);

        $payload = [
            'unit_serial' => $this->assetUnitSerial,
            'product_name' => $this->assetProductName ?: null,
            'model' => $this->assetModel ?: null,
            'installation_date' => $this->assetInstallationDate ?: null,
            'warranty_expiry' => $this->assetWarrantyExpiry ?: null,
            'status' => $this->assetStatus,
            'notes' => $this->assetNotes ?: null,
        ];

        if ($this->editingAssetId) {
            $this->customer->assets()->findOrFail($this->editingAssetId)->update($payload);
            $message = 'Unit updated.';
        } else {
            $this->customer->assets()->create($payload);
            $message = 'Unit assigned to customer.';
        }

        $this->resetAssetForm();
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function deleteAsset(int $assetId): void
    {
        $this->customer->assets()->whereKey($assetId)->delete();
        $this->dispatch('toast', type: 'success', message: 'Unit removed.');
    }

    public function resetAssetForm(): void
    {
        $this->reset([
            'showAssetForm', 'editingAssetId', 'assetUnitSerial', 'assetProductName',
            'assetModel', 'assetInstallationDate', 'assetWarrantyExpiry', 'assetNotes',
        ]);
        $this->assetStatus = 'active';
        $this->resetValidation();
    }

    // ── Send SMS ──────────────────────────────────────────────────────────

    public function sendSms(): void
    {
        $this->validate(['smsBody' => 'required|string|max:918']);

        if (! $this->canSendSms('smsBody')) {
            return;
        }

        $this->queueCustomerSms(
            body: $this->smsBody,
            source: 'profile',
            meta: ['sent_by' => auth()->id()],
        );

        $this->reset('smsBody');
        $this->dispatch('toast', type: 'success', message: 'SMS queued for sending.');
    }

    public function fetchLatestPayGroToken(PayGroService $payGro): void
    {
        $this->resetErrorBag();
        $this->payGroTokenStatus = null;
        $this->payGroTokenStatusIsError = false;

        try {
            $token = $payGro->syncLatestFreeTokenForCustomer($this->customer);

            if (! $token) {
                $this->latestPayGroToken = [];
                $this->flashPayGroTokenStatus('No PayGro token was found for this customer\'s registered unit serials in the past month.', true);

                return;
            }

            $this->latestPayGroToken = $token;
            $this->flashPayGroTokenStatus('Latest PayGro token fetched and matched to this customer.', false);
        } catch (\Throwable $e) {
            $this->latestPayGroToken = [];
            $this->flashPayGroTokenStatus($e->getMessage(), true);
        }
    }

    public function sendLatestPayGroTokenSms(PayGroService $payGro): void
    {
        $this->resetErrorBag();

        if (! $this->canSendSms('payGroTokenStatus')) {
            return;
        }

        try {
            $token = $payGro->syncLatestFreeTokenForCustomer($this->customer);

            if (! $token) {
                $this->latestPayGroToken = [];
                $this->flashPayGroTokenStatus('No PayGro token was found for this customer\'s registered unit serials in the past month.', true);

                return;
            }

            $this->latestPayGroToken = $token;
            $message = $this->latestPayGroTokenSmsBody($token);

            $this->queueCustomerSms(
                body: $message,
                source: 'paygro_latest_token',
                meta: [
                    'sent_by' => auth()->id(),
                    'paygro_token' => [
                        'history_srl_no' => $token['history_srl_no'] ?? null,
                        'product_serial_number' => $token['product_serial_number'] ?? null,
                        'generated_token_value' => $token['generated_token_value'] ?? null,
                        'token_generation_date' => $token['token_generation_date'] ?? null,
                    ],
                ],
            );

            $this->flashPayGroTokenStatus('Latest PayGro token queued for SMS.', false);
            $this->dispatch('toast', type: 'success', message: 'Latest token SMS queued for sending.');
        } catch (\Throwable $e) {
            $this->flashPayGroTokenStatus($e->getMessage(), true);
        }
    }

    protected function canSendSms(string $errorField): bool
    {
        if (! $this->customer->phone) {
            $this->addError($errorField, 'This customer has no phone number on file.');

            return false;
        }

        if ($this->customer->sms_opted_out) {
            $this->addError($errorField, 'This customer has opted out of SMS.');

            return false;
        }

        return true;
    }

    protected function queueCustomerSms(string $body, string $source, array $meta = []): void
    {
        // Pre-create the linked log so the message appears in this customer's
        // timeline immediately and the job updates the same record on send.
        $smsMessage = SmsMessage::create([
            'customer_id' => $this->customer->id,
            'to' => $this->customer->phone,
            'body' => $body,
            'direction' => 'outbound',
            'status' => 'queued',
            'meta' => array_merge(['source' => $source], $meta),
        ]);

        SendSmsJob::dispatch(
            to: $this->customer->phone,
            message: $body,
            smsMessageId: $smsMessage->id,
            meta: ['customer_id' => $this->customer->id],
        );
    }

    /**
     * @param  array<string, mixed>  $token
     */
    protected function latestPayGroTokenSmsBody(array $token): string
    {
        $serial = $token['product_serial_number'] ?? $token['matched_asset_serial'] ?? 'your unit';
        $value = $token['generated_token_value'] ?? '';
        $date = $token['token_generation_date_display'] ?? 'recently';

        return "Your latest Megasol token for {$serial} is {$value}. Generated {$date}.";
    }

    protected function flashPayGroTokenStatus(string $message, bool $isError): void
    {
        $this->payGroTokenStatus = $message;
        $this->payGroTokenStatusIsError = $isError;
    }

    /**
     * Build a unified, reverse-chronological message timeline (SMS + email,
     * inbound + outbound).
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function messageTimeline(): Collection
    {
        $sms = $this->customer->smsMessages()
            ->latest('created_at')->limit(50)->get()
            ->map(fn ($m) => [
                'channel' => 'sms',
                'direction' => $m->direction,
                'title' => $m->direction === 'inbound' ? 'SMS received' : 'SMS sent',
                'body' => $m->body,
                'status' => $m->status,
                'intent' => $m->meta['intent'] ?? null,
                'date' => $m->created_at,
            ]);

        $email = $this->customer->emailMessages()
            ->latest('created_at')->limit(50)->get()
            ->map(fn ($m) => [
                'channel' => 'email',
                'direction' => $m->direction,
                'title' => ($m->direction === 'inbound' ? 'Email received: ' : 'Email sent: ').($m->subject ?? ''),
                'body' => strip_tags((string) $m->body_html),
                'status' => $m->status,
                'intent' => null,
                'date' => $m->created_at,
            ]);

        return $sms->concat($email)
            ->sortByDesc('date')
            ->values()
            ->take(60);
    }

    public function render()
    {
        $customer = $this->customer;

        $data = ['agents' => collect()];

        // Lazy-load only what the active tab needs.
        switch ($this->tab) {
            case 'payments':
                $data['payments'] = $customer->payments()->paginate(15, ['*'], 'payments');
                break;
            case 'tokens':
                $data['tokens'] = $customer->tokenTransactions()->paginate(15, ['*'], 'tokens');
                $data['tokenSmsMessages'] = $customer->smsMessages()
                    ->where('direction', 'outbound')
                    ->where('meta->source', 'paygro_latest_token')
                    ->latest('created_at')
                    ->limit(25)
                    ->get();
                break;
            case 'schedule':
                $data['schedule'] = $customer->repaymentSchedules()->get();
                break;
            case 'assets':
                $data['assets'] = $customer->assets()->get();
                break;
            case 'messages':
                $data['timeline'] = $this->messageTimeline();
                break;
            case 'collections':
                $data['assignments'] = $customer->agentAssignments()->with(['agent', 'assignedBy'])->get();
                $data['agents'] = User::query()->orderBy('name')->get(['id', 'name', 'email']);
                break;
        }

        return view('livewire.customers.customer-profile', $data);
    }
}
