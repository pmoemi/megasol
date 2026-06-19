<?php

namespace App\Livewire\Customers;

use App\Jobs\SendSmsJob;
use App\Models\AgentAssignment;
use App\Models\Customer;
use App\Models\RepaymentSchedule;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\Integrations\PayGroService;
use App\Services\Sms\AfricasTalkingSmsService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class CustomerProfile extends Component
{
    use WithPagination;
    public Customer $customer;

    #[Url]
    public string $tab = 'overview';

    #[Url]
    public string $paymentsSubTab = 'schedule';

    public string $paymentAssetFilter = '';

    public array $paymentsSubTabs = [
        'schedule' => 'Repayment Schedule',
        'history' => 'Payment History',
    ];

    // ── Collections assignment form ──────────────────────────────────────
    public bool $showAssignForm = false;

    public ?int $assignAgentId = null;

    public string $assignReason = '';

    public string $assignNotes = '';

    public ?int $updateAssignmentId = null;

    public string $assignmentStatus = '';

    // ── Send SMS (outbound, two-way conversation) ────────────────────────
    public string $smsBody = '';

    // ── PayGro latest-token lookup ───────────────────────────────────────
    public array $latestPayGroToken = [];

    public string $tokenSmsPhone = '';

    public ?string $payGroTokenStatus = null;

    public bool $payGroTokenStatusIsError = false;

    public array $tabs = [
        'overview' => 'Overview',
        'payments' => 'Payments',
        'tokens' => 'Tokens',
        'assets' => 'Assets',
        'messages' => 'Messages',
        'collections' => 'Collections',
    ];

    public function mount(Customer $customer): void
    {
        $this->customer = $customer;

        // The Schedule tab was merged into Payments — keep old links working.
        if ($this->tab === 'schedule') {
            $this->tab = 'payments';
            $this->paymentsSubTab = 'schedule';
        }

        if (! array_key_exists($this->tab, $this->tabs)) {
            $this->tab = 'overview';
        }

        if (! array_key_exists($this->paymentsSubTab, $this->paymentsSubTabs)) {
            $this->paymentsSubTab = 'schedule';
        }

        $this->tokenSmsPhone = $this->customer->phone ?? '';
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

    public function setPaymentsSubTab(string $subTab): void
    {
        if (array_key_exists($subTab, $this->paymentsSubTabs)) {
            $this->paymentsSubTab = $subTab;
            $this->resetPage('payments');
        }
    }

    public function updatedPaymentAssetFilter(): void
    {
        $this->resetPage('payments');
    }

    protected function refreshPayGroUnits(): void
    {
        try {
            app(PayGroService::class)->syncUnitsForCustomer($this->customer);
            $this->customer->refresh();
        } catch (\Throwable $e) {
            Log::warning('PayGro unit sync failed for customer profile', [
                'customer_id' => $this->customer->id,
                'error' => $e->getMessage(),
            ]);
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

    // ── Send SMS ──────────────────────────────────────────────────────────

    public function sendSms(): void
    {
        $this->validate(['smsBody' => 'required|string|max:918']);

        if (! $this->canSendSms('smsBody')) {
            return;
        }

        $sms = app(AfricasTalkingSmsService::class);
        $phone = $sms->resolveRecipientPhone((string) ($this->customer->phone ?? ''));

        if ($phone === null) {
            $this->addError('smsBody', 'This customer does not have a valid Kenyan mobile number on file.');

            return;
        }

        $smsMessage = SmsMessage::create([
            'customer_id' => $this->customer->id,
            'to' => $phone,
            'body' => $this->smsBody,
            'direction' => 'outbound',
            'status' => 'queued',
            'meta' => ['source' => 'profile', 'sent_by' => auth()->id()],
        ]);

        SendSmsJob::dispatch(
            to: $phone,
            message: $this->smsBody,
            smsMessageId: $smsMessage->id,
            meta: ['source' => 'profile', 'customer_id' => $this->customer->id],
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
            $this->refreshPayGroUnits();

            $token = $payGro->syncLatestFreeTokenForCustomer($this->customer);

            if (! $token) {
                $this->latestPayGroToken = [];
                $this->flashPayGroTokenStatus('No PayGro token was found for this customer\'s unit(s) in payment or free-token history.', true);

                return;
            }

            $this->latestPayGroToken = $token;
            $this->flashPayGroTokenStatus('Latest PayGro token fetched and matched to this customer.', false);
        } catch (\Throwable $e) {
            $this->latestPayGroToken = [];
            $this->flashPayGroTokenStatus($e->getMessage(), true);
        }
    }

    public function sendLatestPayGroTokenSms(PayGroService $payGro, AfricasTalkingSmsService $sms): void
    {
        $this->resetErrorBag();

        $this->validate([
            'tokenSmsPhone' => 'required|string|min:9|max:20',
        ]);

        if (! $sms->isValidKenyanMobileNumber($this->tokenSmsPhone)) {
            $this->addError('tokenSmsPhone', 'Enter a valid Kenyan mobile (e.g. 254725584124 or 0725584124).');

            return;
        }

        $this->tokenSmsPhone = $sms->normalizePhoneNumber($this->tokenSmsPhone);

        if (! $this->canSendSms('payGroTokenStatus', $this->tokenSmsPhone)) {
            return;
        }

        try {
            $token = $this->latestPayGroToken !== []
                ? $this->latestPayGroToken
                : $payGro->syncLatestFreeTokenForCustomer($this->customer);

            if (! $token) {
                $this->latestPayGroToken = [];
                $this->flashPayGroTokenStatus('No PayGro token was found for this customer\'s unit(s). Click Fetch Latest Token first, or run PayGro sync.', true);

                return;
            }

            $this->latestPayGroToken = $token;
            $message = $this->latestPayGroTokenSmsBody($token);

            $result = $this->sendCustomerSmsNow(
                sms: $sms,
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
                to: $this->tokenSmsPhone,
            );

            $status = $result['status'] ?? 'sent';
            $messageId = $result['message_id'] ?? null;
            $suffix = $messageId ? " (ID: {$messageId})" : '';

            $this->flashPayGroTokenStatus('Token SMS accepted ('.$status.') to '.$this->tokenSmsPhone.$suffix.'.', false);
            $this->dispatch('toast', type: 'success', message: 'Token SMS sent to '.$this->tokenSmsPhone.'.');
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if (str_contains($message, '401') || str_contains($message, 'authentication is invalid')) {
                $message = 'Africa\'s Talking rejected the username or API key (401). '
                    .'Check Settings → SMS and save a valid API key.';
            }
            $this->flashPayGroTokenStatus('Failed to send token SMS: '.$message, true);
        }
    }

    protected function canSendSms(string $errorField, ?string $phone = null): bool
    {
        $phone = trim($phone ?? $this->customer->phone ?? '');

        if ($phone === '') {
            $this->addError($errorField, 'A phone number is required to send SMS.');

            return false;
        }

        if ($this->customer->sms_opted_out) {
            $this->addError($errorField, 'This customer has opted out of SMS.');

            return false;
        }

        return true;
    }

    /**
     * @return array{success: bool, message_id: ?string, status: string, raw: mixed, sms_message_id: ?int}
     */
    protected function sendCustomerSmsNow(AfricasTalkingSmsService $sms, string $body, string $source, array $meta = [], ?string $to = null): array
    {
        $to = trim($to ?? $this->customer->phone ?? '');

        $smsMessage = SmsMessage::create([
            'customer_id' => $this->customer->id,
            'to' => $to,
            'body' => $body,
            'direction' => 'outbound',
            'status' => 'queued',
            'meta' => array_merge(['source' => $source], $meta),
        ]);

        return $sms->send(
            to: $to,
            message: $body,
            meta: array_merge(['customer_id' => $this->customer->id, 'source' => $source], $meta),
            existingMessageId: $smsMessage->id,
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
        $name = trim((string) ($this->customer->first_name ?? ''));

        $greeting = $name !== '' ? "Hi {$name}," : 'Hi,';

        return "{$greeting} your latest Megasol token for {$serial} is {$value}. Generated {$date}.";
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
            case 'overview':
                $data['overview'] = app(PayGroService::class)->buildCustomerFinancialOverview($customer);
                break;
            case 'payments':
                if ($this->paymentsSubTab === 'history') {
                    if ($this->paymentAssetFilter !== '' && ! $customer->assets()->whereKey((int) $this->paymentAssetFilter)->exists()) {
                        $this->paymentAssetFilter = '';
                    }

                    $data['paymentAssets'] = $customer->assets()->orderBy('unit_serial')->get();
                    $data['paymentSummary'] = $this->buildPaymentSummary($customer);
                    $data['payments'] = $this->filteredPaymentsQuery($customer)->paginate(15, ['*'], 'payments');
                } else {
                    $data['scheduleUnits'] = $this->buildScheduleUnits($customer);
                }
                break;
            case 'tokens':
                $data['tokens'] = $customer->tokenTransactions()->paginate(15, ['*'], 'tokens');
                $data['tokenSmsMessages'] = $customer->smsMessages()
                    ->where('direction', 'outbound')
                    ->where('meta->source', 'paygro_latest_token')
                    ->latest('created_at')
                    ->limit(25)
                    ->get();
                $data['payGroMatchSerials'] = app(PayGroService::class)->customerPayGroSerials($customer);
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

    /**
     * @return array<int, array{asset: ?\App\Models\CustomerAsset, sales_identifier: ?string, plan_name: ?string, repayment_meta: array{label: string, color: string}, installments: LengthAwarePaginator}>
     */
    protected function buildScheduleUnits(Customer $customer): array
    {
        $assets = $customer->assets()->get()->keyBy('id');
        $perPage = 15;
        $units = [];
        $seenGroups = [];

        $planRows = $customer->repaymentSchedules()
            ->where('entry_type', RepaymentSchedule::ENTRY_PLAN)
            ->orderBy('sales_identifier')
            ->get();

        foreach ($planRows as $planRow) {
            $groupKey = $this->scheduleGroupKey($planRow->sales_identifier, $planRow->customer_asset_id);
            $seenGroups[$groupKey] = true;

            $units[] = $this->buildScheduleUnit(
                $customer,
                $assets,
                $planRow->sales_identifier,
                $planRow->customer_asset_id,
                $planRow->payment_plan_name,
                $perPage,
                $groupKey,
            );
        }

        $orphanGroups = $customer->repaymentSchedules()
            ->where('entry_type', RepaymentSchedule::ENTRY_INSTALLMENT)
            ->get(['sales_identifier', 'customer_asset_id', 'payment_plan_name'])
            ->unique(fn ($row) => $this->scheduleGroupKey($row->sales_identifier, $row->customer_asset_id))
            ->filter(function ($row) use ($seenGroups) {
                $groupKey = $this->scheduleGroupKey($row->sales_identifier, $row->customer_asset_id);

                return ! isset($seenGroups[$groupKey]);
            });

        foreach ($orphanGroups as $row) {
            $groupKey = $this->scheduleGroupKey($row->sales_identifier, $row->customer_asset_id);

            $units[] = $this->buildScheduleUnit(
                $customer,
                $assets,
                $row->sales_identifier,
                $row->customer_asset_id,
                $row->payment_plan_name,
                $perPage,
                $groupKey,
            );
        }

        return $units;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\CustomerAsset>  $assets
     * @return array{asset: ?\App\Models\CustomerAsset, sales_identifier: ?string, plan_name: ?string, repayment_meta: array{label: string, color: string}, installments: LengthAwarePaginator}
     */
    protected function buildScheduleUnit(
        Customer $customer,
        Collection $assets,
        ?string $salesIdentifier,
        ?int $customerAssetId,
        ?string $planName,
        int $perPage,
        string $groupKey,
    ): array {
        $salesId = trim((string) ($salesIdentifier ?? ''));
        $asset = is_numeric($customerAssetId) ? $assets->get((int) $customerAssetId) : null;

        if (! $asset && $salesId !== '') {
            $asset = $assets->first(function ($candidate) use ($salesId) {
                $meta = is_array($candidate->meta) ? $candidate->meta : [];
                $candidateSalesId = trim((string) ($meta['paygro_sales_identifier'] ?? ''));

                return strcasecmp($candidateSalesId, $salesId) === 0;
            });
        }

        $installmentsQuery = $customer->repaymentSchedules()
            ->where('entry_type', RepaymentSchedule::ENTRY_INSTALLMENT)
            ->orderBy('installment_number')
            ->orderBy('due_date');

        if ($salesId !== '') {
            $installmentsQuery->where('sales_identifier', $salesId);
        } elseif ($customerAssetId) {
            $installmentsQuery->where('customer_asset_id', $customerAssetId);
        }

        return [
            'asset' => $asset,
            'sales_identifier' => $salesId !== '' ? $salesId : null,
            'plan_name' => $planName,
            'repayment_meta' => $asset?->repaymentStatusMeta() ?? ['label' => 'Active', 'color' => 'info'],
            'installments' => $installmentsQuery->paginate($perPage, ['*'], $this->schedulePageName($groupKey)),
        ];
    }

    protected function scheduleGroupKey(?string $salesIdentifier, ?int $customerAssetId): string
    {
        $salesId = trim((string) ($salesIdentifier ?? ''));

        if ($salesId !== '') {
            return 'sale:'.$salesId;
        }

        return 'asset:'.(string) ($customerAssetId ?? 'general');
    }

    protected function schedulePageName(string $groupKey): string
    {
        $slug = preg_replace('/[^a-z0-9_]+/', '_', strtolower($groupKey));
        $slug = trim((string) $slug, '_');

        return 'schedule_'.$slug;
    }

    /**
     * @return array{total_amount: float, total_count: int, last_payment_at: ?\Illuminate\Support\Carbon, total_days_credited: int}
     */
    protected function buildPaymentSummary(Customer $customer): array
    {
        $stats = $this->filteredPaymentsQuery($customer)
            ->selectRaw('COUNT(*) as total_count, COALESCE(SUM(amount), 0) as total_amount, COALESCE(SUM(days_credited), 0) as total_days_credited, MAX(paid_at) as last_payment_at')
            ->first();

        return [
            'total_amount' => (float) ($stats->total_amount ?? 0),
            'total_count' => (int) ($stats->total_count ?? 0),
            'last_payment_at' => ($stats->last_payment_at ?? null) ? \Illuminate\Support\Carbon::parse($stats->last_payment_at) : null,
            'total_days_credited' => (int) ($stats->total_days_credited ?? 0),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\CustomerPayment>
     */
    protected function filteredPaymentsQuery(Customer $customer)
    {
        $query = $customer->payments();

        if ($this->paymentAssetFilter === '') {
            return $query;
        }

        $asset = $customer->assets()->find((int) $this->paymentAssetFilter);

        if (! $asset) {
            return $query;
        }

        $meta = is_array($asset->meta) ? $asset->meta : [];
        $serial = trim((string) $asset->unit_serial);
        $salesId = trim((string) ($meta['paygro_sales_identifier'] ?? ''));

        if ($serial === '' && $salesId === '') {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(function ($builder) use ($serial, $salesId) {
            if ($salesId !== '') {
                $builder->orWhere('meta->sales_identifier', $salesId);
            }

            if ($serial !== '') {
                $builder->orWhere('meta->product_serial_number', $serial);
            }
        });
    }
}
