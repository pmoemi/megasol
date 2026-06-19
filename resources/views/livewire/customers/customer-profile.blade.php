@php
    $paymentStatusColors = ['current' => 'bg-success/15 text-success','due_soon' => 'bg-warning/15 text-warning','overdue' => 'bg-danger/15 text-danger','paid_off' => 'bg-surface text-muted'];
    $acct = $customer->accountStatusMeta();
    $acctColors = ['success' => 'bg-success/15 text-success','danger' => 'bg-danger/15 text-danger','muted' => 'bg-surface text-muted','info' => 'bg-info/15 text-info'];
    $assignmentColors = [
        'assigned' => 'bg-info/15 text-info','in_progress' => 'bg-warning/15 text-warning','promised_to_pay' => 'bg-brand/15 text-brand',
        'resolved' => 'bg-success/15 text-success','escalated' => 'bg-danger/15 text-danger','written_off' => 'bg-surface text-muted',
    ];
    $scheduleColors = ['paid' => 'bg-success/15 text-success','partial' => 'bg-warning/15 text-warning','overdue' => 'bg-danger/15 text-danger','pending' => 'bg-surface text-muted','waived' => 'bg-info/15 text-info'];
    $assetColors = ['active' => 'bg-success/15 text-success','faulty' => 'bg-warning/15 text-warning','repossessed' => 'bg-danger/15 text-danger','returned' => 'bg-surface text-muted','decommissioned' => 'bg-surface text-muted'];
    $repaymentStatusColors = ['success' => 'bg-success/15 text-success', 'danger' => 'bg-danger/15 text-danger', 'info' => 'bg-info/15 text-info'];
    $smsStatusColors = ['queued' => 'bg-warning/15 text-warning','sent' => 'bg-info/15 text-info','success' => 'bg-success/15 text-success','delivered' => 'bg-success/15 text-success','failed' => 'bg-danger/15 text-danger','rejected' => 'bg-danger/15 text-danger'];
    $avatarPalette = ['bg-brand/15 text-brand','bg-info/15 text-info','bg-success/15 text-success','bg-warning/15 text-warning','bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300'];
@endphp

<div class="space-y-6">
    {{-- Back --}}
    <a href="{{ route('customers.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm text-muted hover:text-ink transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        Back to customers
    </a>

    {{-- ── Header ─────────────────────────────────────────────── --}}
    <div class="bg-surface-2 rounded-2xl border border-border p-5 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="flex items-center gap-4 min-w-0">
                <div class="w-16 h-16 {{ $avatarPalette[$customer->id % count($avatarPalette)] }} rounded-2xl flex items-center justify-center text-xl font-bold shrink-0">
                    {{ $customer->initials }}
                </div>
                <div class="min-w-0">
                    <h1 class="text-2xl font-bold text-ink truncate">{{ $customer->full_name }}</h1>
                    <div class="flex flex-wrap items-center gap-2 mt-1.5">
                        @if($customer->account_number)
                        <span class="text-xs font-mono text-muted">{{ $customer->account_number }}</span>
                        @endif
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $acctColors[$acct['color']] }}">{{ $acct['label'] }}</span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $paymentStatusColors[$customer->payment_status] ?? 'bg-surface text-muted' }}">
                            {{ str_replace('_', ' ', ucfirst($customer->payment_status ?? '—')) }}
                        </span>
                        @if($customer->sms_opted_out)
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-danger/15 text-danger">SMS opted out</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button type="button" wire:click="setTab('messages')"
                   class="btn-primary inline-flex items-center gap-1.5 text-sm font-semibold">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                    Send SMS
                </button>
            </div>
        </div>
    </div>

    {{-- ── Tab nav ────────────────────────────────────────────── --}}
    <div class="border-b border-border">
        <nav class="flex gap-6 -mb-px overflow-x-auto">
            @foreach($tabs as $key => $label)
            <button type="button" wire:click="setTab('{{ $key }}')"
                    class="pb-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap flex-shrink-0
                           {{ $tab === $key ? 'border-brand text-brand' : 'border-transparent text-muted hover:text-ink/80 hover:border-border' }}">
                {{ $label }}
            </button>
            @endforeach
        </nav>
    </div>

    {{-- ════════════════════ OVERVIEW ════════════════════ --}}
    @if($tab === 'overview')
    <div class="space-y-6">
        {{-- Stat cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-surface-2 rounded-2xl border border-border p-5">
                <p class="text-xs font-medium text-muted uppercase tracking-wider">Outstanding</p>
                <p class="text-2xl font-bold text-ink mt-1">KES {{ number_format((float) ($overview['outstanding_balance'] ?? $customer->outstanding_balance), 2) }}</p>
                @if($overview['has_plan_balances'] ?? false)
                <p class="text-xs text-muted mt-1">Unlock price minus payments</p>
                @endif
            </div>
            <div class="bg-surface-2 rounded-2xl border border-border p-5">
                <p class="text-xs font-medium text-muted uppercase tracking-wider">Token Balance</p>
                <p class="text-2xl font-bold text-ink mt-1">{{ number_format($customer->token_balance) }} <span class="text-sm font-medium text-muted">days</span></p>
            </div>
            <div class="bg-surface-2 rounded-2xl border border-border p-5">
                <p class="text-xs font-medium text-muted uppercase tracking-wider">Days in Arrears</p>
                <p class="text-2xl font-bold mt-1 {{ $customer->days_in_arrears > 0 ? 'text-danger' : 'text-ink' }}">{{ number_format($customer->days_in_arrears) }}</p>
                <p class="text-xs text-muted mt-1">
                    @if($customer->token_balance > 0)
                        Prepaid tokens active
                    @elseif($customer->isHirePurchaseAccount())
                        Hire Purchase installment arrears
                    @else
                        Days since last payment
                    @endif
                </p>
            </div>
            <div class="bg-surface-2 rounded-2xl border border-border p-5">
                <p class="text-xs font-medium text-muted uppercase tracking-wider">Total Paid</p>
                <p class="text-2xl font-bold text-ink mt-1">KES {{ number_format((float) ($overview['total_paid'] ?? $customer->total_paid), 2) }}</p>
            </div>
        </div>

        @if(! empty($overview['units'] ?? []))
        <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
            <div class="px-5 py-4 border-b border-border">
                <h3 class="text-sm font-semibold text-ink">Balance by Unit</h3>
                <p class="text-xs text-muted mt-0.5">Per-product unlock price and payments from PayGro plans.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-surface border-b border-border">
                            <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">Unit</th>
                            <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden md:table-cell">Plan</th>
                            <th class="text-right text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden sm:table-cell">Unlock Price</th>
                            <th class="text-right text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden sm:table-cell">Paid</th>
                            <th class="text-right text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">Balance</th>
                            <th class="text-right text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden lg:table-cell">Daily</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        @foreach($overview['units'] as $unit)
                        <tr class="hover:bg-surface transition-colors">
                            <td class="px-4 py-3 text-sm font-mono text-ink">{{ $unit['serial'] }}</td>
                            <td class="px-4 py-3 text-sm text-muted hidden md:table-cell">{{ $unit['plan_name'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-right hidden sm:table-cell whitespace-nowrap">KES {{ number_format((float) $unit['unlock_price'], 2) }}</td>
                            <td class="px-4 py-3 text-sm text-right hidden sm:table-cell whitespace-nowrap">KES {{ number_format((float) $unit['paid'], 2) }}</td>
                            <td class="px-4 py-3 text-sm font-semibold text-right whitespace-nowrap">KES {{ number_format((float) $unit['balance'], 2) }}</td>
                            <td class="px-4 py-3 text-sm text-right hidden lg:table-cell whitespace-nowrap">KES {{ number_format((float) $unit['daily_payment'], 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Details --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="bg-surface-2 rounded-2xl border border-border p-5 space-y-3">
                <h3 class="text-sm font-semibold text-ink mb-2">Account Details</h3>
                @foreach([
                    ['Product Type', $customer->product_type],
                    ['Location', $customer->location],
                    ['Phone', $customer->phone],
                    ['Email', $customer->email],
                    ['Next Payment', $customer->next_payment_date?->format('M j, Y')],
                    ['Activated', $customer->activated_at?->format('M j, Y')],
                ] as [$label, $value])
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs text-muted">{{ $label }}</p>
                    <p class="text-sm text-ink text-right truncate">{{ $value ?: '—' }}</p>
                </div>
                @endforeach
            </div>

            <div class="bg-surface-2 rounded-2xl border border-border p-5 space-y-3">
                <h3 class="text-sm font-semibold text-ink mb-2">Status & Collections</h3>
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs text-muted">Account Status</p>
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $acctColors[$acct['color']] }}">{{ $acct['label'] }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs text-muted">Lifecycle Stage</p>
                    <p class="text-sm text-ink capitalize">{{ str_replace('_', ' ', $customer->lifecycle_stage ?? '—') }}</p>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs text-muted">Assigned Field Agent</p>
                    <p class="text-sm text-ink text-right">{{ $customer->assignedAgent?->name ?? 'Unassigned' }}</p>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs text-muted">Units / Assets</p>
                    <p class="text-sm text-ink">{{ $customer->assets()->count() }}</p>
                </div>
                <div class="pt-2">
                    <button type="button" wire:click="setTab('collections')" class="text-sm text-brand font-medium hover:underline">Manage collections →</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ════════════════════ PAYMENTS ════════════════════ --}}
    @if($tab === 'payments')
    <div class="space-y-4">
        {{-- Sub-tab nav --}}
        <div class="border-b border-border">
            <nav class="flex gap-4 -mb-px overflow-x-auto">
                @foreach($paymentsSubTabs as $key => $label)
                <button type="button" wire:click="setPaymentsSubTab('{{ $key }}')"
                        class="pb-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap flex-shrink-0
                               {{ $paymentsSubTab === $key ? 'border-brand text-brand' : 'border-transparent text-muted hover:text-ink/80 hover:border-border' }}">
                    {{ $label }}
                </button>
                @endforeach
            </nav>
        </div>

        {{-- Repayment Schedule --}}
        @if($paymentsSubTab === 'schedule')
        <div class="space-y-3">
            @if(count($scheduleUnits ?? []) > 0)
                @foreach($scheduleUnits as $unit)
                <div class="bg-surface-2 rounded-xl border border-border overflow-hidden">
                    <div class="px-3 py-2.5 border-b border-border flex flex-wrap items-center justify-between gap-2">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                @if($unit['asset'])
                                <span class="text-xs font-mono text-ink">{{ $unit['asset']->unit_serial }}</span>
                                @endif
                                @if($unit['plan_name'])
                                <span class="text-xs text-muted">{{ $unit['plan_name'] }}</span>
                                @endif
                                @if($unit['sales_identifier'] ?? null)
                                <span class="text-xs text-muted font-mono">{{ $unit['sales_identifier'] }}</span>
                                @endif
                            </div>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-medium {{ $repaymentStatusColors[$unit['repayment_meta']['color']] ?? 'bg-surface text-muted' }}">
                            {{ $unit['repayment_meta']['label'] }}
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-surface border-b border-border">
                                    <th class="text-left text-[11px] font-semibold text-muted uppercase tracking-wider px-3 py-2">#</th>
                                    <th class="text-left text-[11px] font-semibold text-muted uppercase tracking-wider px-3 py-2">Due</th>
                                    <th class="text-right text-[11px] font-semibold text-muted uppercase tracking-wider px-3 py-2">Due Amt</th>
                                    <th class="text-right text-[11px] font-semibold text-muted uppercase tracking-wider px-3 py-2 hidden sm:table-cell">Paid</th>
                                    <th class="text-right text-[11px] font-semibold text-muted uppercase tracking-wider px-3 py-2 hidden md:table-cell">Balance</th>
                                    <th class="text-left text-[11px] font-semibold text-muted uppercase tracking-wider px-3 py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                @foreach($unit['installments'] as $s)
                                <tr class="hover:bg-surface transition-colors">
                                    <td class="px-3 py-2 text-xs text-muted">{{ $s->installment_number ?: '—' }}</td>
                                    <td class="px-3 py-2 text-xs text-ink whitespace-nowrap">{{ $s->due_date?->format('M j, Y') }}</td>
                                    <td class="px-3 py-2 text-xs text-ink text-right whitespace-nowrap">KES {{ number_format((float) $s->amount_due, 2) }}</td>
                                    <td class="px-3 py-2 text-xs text-muted text-right hidden sm:table-cell whitespace-nowrap">KES {{ number_format((float) $s->amount_paid, 2) }}</td>
                                    <td class="px-3 py-2 text-xs text-ink text-right hidden md:table-cell whitespace-nowrap">KES {{ number_format($s->balance, 2) }}</td>
                                    <td class="px-3 py-2"><span class="px-1.5 py-0.5 rounded-full text-[11px] font-medium capitalize {{ $scheduleColors[$s->status] ?? 'bg-surface text-muted' }}">{{ $s->status }}</span></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($unit['installments']->hasPages())
                    <div class="px-3 py-2 border-t border-border text-sm">{{ $unit['installments']->links() }}</div>
                    @endif
                </div>
                @endforeach
            @else
                <div class="bg-surface-2 rounded-xl border border-border px-4 py-8 text-center">
                    <p class="text-sm font-medium text-ink">No repayment schedule</p>
                    <p class="text-xs text-muted mt-1 max-w-sm mx-auto">Sync PayGro under <a href="{{ route('settings.paygro') }}" wire:navigate class="text-brand hover:underline">Settings → PayGro</a>.</p>
                </div>
            @endif
        </div>
        @endif

        {{-- Payment History --}}
        @if($paymentsSubTab === 'history')
        <div class="space-y-3">
            {{-- Compact stats + filter --}}
            <div class="bg-surface-2 rounded-xl border border-border px-3 py-2.5">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-2 flex-1 min-w-0">
                        <div>
                            <p class="text-[11px] font-medium text-muted uppercase tracking-wide">Total Paid</p>
                            <p class="text-sm font-semibold text-ink">KES {{ number_format((float) ($paymentSummary['total_amount'] ?? 0), 2) }}</p>
                            <p class="text-[11px] text-muted">{{ number_format($paymentSummary['total_count'] ?? 0) }} txn{{ ($paymentSummary['total_count'] ?? 0) === 1 ? '' : 's' }}</p>
                        </div>
                        <div>
                            <p class="text-[11px] font-medium text-muted uppercase tracking-wide">Last Payment</p>
                            @if($paymentSummary['last_payment_at'] ?? null)
                            <p class="text-sm font-semibold text-ink">{{ $paymentSummary['last_payment_at']->format('M j, Y') }}</p>
                            <p class="text-[11px] text-muted">{{ $paymentSummary['last_payment_at']->format('g:i A') }}</p>
                            @else
                            <p class="text-sm text-muted">—</p>
                            @endif
                        </div>
                        <div>
                            <p class="text-[11px] font-medium text-muted uppercase tracking-wide">Days Credited</p>
                            <p class="text-sm font-semibold text-ink">{{ number_format($paymentSummary['total_days_credited'] ?? 0) }}</p>
                        </div>
                    </div>
                    @if(($paymentAssets ?? collect())->count() > 0)
                    <div class="shrink-0 lg:w-56">
                        <label class="sr-only" for="payment-asset-filter">Filter by asset</label>
                        <select id="payment-asset-filter" wire:model.live="paymentAssetFilter" class="input text-sm py-1.5 h-9">
                            <option value="">All assets</option>
                            @foreach($paymentAssets as $asset)
                            @php
                                $assetMeta = is_array($asset->meta) ? $asset->meta : [];
                                $planName = trim((string) ($assetMeta['paygro_payment_plan'] ?? ''));
                            @endphp
                            <option value="{{ $asset->id }}">
                                {{ $asset->unit_serial ?: 'Unit #'.$asset->id }}@if($planName) — {{ $planName }}@endif
                            </option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                </div>
            </div>

            @if($payments->count() > 0)
            <div class="bg-surface-2 rounded-xl border border-border overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-surface border-b border-border">
                                <th class="text-left text-[11px] font-semibold text-muted uppercase tracking-wider px-3 py-2">Date</th>
                                <th class="text-left text-[11px] font-semibold text-muted uppercase tracking-wider px-3 py-2 hidden md:table-cell">Unit</th>
                                <th class="text-right text-[11px] font-semibold text-muted uppercase tracking-wider px-3 py-2">Amount</th>
                                <th class="text-right text-[11px] font-semibold text-muted uppercase tracking-wider px-3 py-2 hidden sm:table-cell">Days</th>
                                <th class="text-left text-[11px] font-semibold text-muted uppercase tracking-wider px-3 py-2 hidden lg:table-cell">Type</th>
                                <th class="text-left text-[11px] font-semibold text-muted uppercase tracking-wider px-3 py-2 hidden xl:table-cell">Method</th>
                                <th class="text-left text-[11px] font-semibold text-muted uppercase tracking-wider px-3 py-2 hidden xl:table-cell">Ref</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            @foreach($payments as $p)
                            @php
                                $pMeta = is_array($p->meta) ? $p->meta : [];
                                $typeLabel = trim((string) ($pMeta['payment_type_text'] ?? '')) ?: str_replace('_', ' ', ucfirst($p->type));
                                $methodLabel = trim((string) ($pMeta['payment_source_name'] ?? '')) ?: ($p->method ? ucfirst($p->method) : '—');
                                $unitSerial = trim((string) ($pMeta['product_serial_number'] ?? ''));
                            @endphp
                            <tr class="hover:bg-surface transition-colors">
                                <td class="px-3 py-2 text-xs text-ink whitespace-nowrap">
                                    {{ $p->paid_at?->format('M j, Y g:i A') ?? '—' }}
                                </td>
                                <td class="px-3 py-2 text-xs text-muted hidden md:table-cell font-mono max-w-[140px] truncate" title="{{ $unitSerial }}">{{ $unitSerial ?: '—' }}</td>
                                <td class="px-3 py-2 text-xs font-semibold text-ink text-right whitespace-nowrap">KES {{ number_format((float) $p->amount, 2) }}</td>
                                <td class="px-3 py-2 text-xs text-right hidden sm:table-cell whitespace-nowrap">
                                    @if($p->days_credited)
                                    <span class="text-success font-medium">+{{ $p->days_credited }}</span>
                                    @else
                                    <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-xs text-muted hidden lg:table-cell">{{ $typeLabel }}</td>
                                <td class="px-3 py-2 text-xs text-muted hidden xl:table-cell">{{ $methodLabel }}</td>
                                <td class="px-3 py-2 text-xs text-muted hidden xl:table-cell font-mono max-w-[100px] truncate" title="{{ $p->reference }}">{{ $p->reference ?? '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-3 py-2 border-t border-border text-sm">{{ $payments->links() }}</div>
            </div>
            @else
                <div class="bg-surface-2 rounded-xl border border-border px-4 py-8 text-center">
                    <p class="text-sm font-medium text-ink">No payments recorded</p>
                    <p class="text-xs text-muted mt-1 max-w-sm mx-auto">
                        @if($paymentAssetFilter !== '')
                            No payments match this asset.
                        @else
                            Sync PayGro under <a href="{{ route('settings.paygro') }}" wire:navigate class="text-brand hover:underline">Settings → PayGro</a>.
                        @endif
                    </p>
                </div>
            @endif
        </div>
        @endif
    </div>
    @endif

    {{-- ════════════════════ TOKENS ════════════════════ --}}
    @if($tab === 'tokens')
    <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3 px-5 py-4 border-b border-border">
            <div>
                <h3 class="text-sm font-semibold text-ink">Token / Credit Ledger</h3>
                <p class="text-xs text-muted mt-0.5">Current balance: <span class="font-semibold text-ink">{{ number_format($customer->token_balance) }} days</span></p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="fetchLatestPayGroToken" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium text-ink/80 bg-surface border border-border rounded-xl hover:bg-surface-2 transition-colors" wire:loading.attr="disabled" wire:target="fetchLatestPayGroToken,sendLatestPayGroTokenSms">
                    <span wire:loading.remove wire:target="fetchLatestPayGroToken">Fetch Latest Token</span>
                    <span wire:loading wire:target="fetchLatestPayGroToken">Fetching…</span>
                </button>
                <button type="button" wire:click="sendLatestPayGroTokenSms" class="btn-primary inline-flex items-center gap-1.5 text-sm" wire:loading.attr="disabled" wire:target="fetchLatestPayGroToken,sendLatestPayGroTokenSms" @disabled($customer->sms_opted_out)>
                    <span wire:loading.remove wire:target="sendLatestPayGroTokenSms">Send Token SMS</span>
                    <span wire:loading wire:target="sendLatestPayGroTokenSms">Queueing…</span>
                </button>
            </div>
        </div>
        @if(($payGroMatchSerials ?? collect())->isEmpty())
        <div class="px-5 py-3 border-b border-border bg-warning/10">
            <p class="text-xs text-warning">
                No PayGro units found for this customer. Ensure they exist in PayGro with a matching name, then run
                <a href="{{ route('settings.paygro') }}" wire:navigate class="underline font-medium">PayGro sync</a>.
            </p>
        </div>
        @else
        <div class="px-5 py-3 border-b border-border bg-surface/40">
            <p class="text-xs text-muted">
                Matching unit serials:
                <span class="font-mono text-ink">{{ ($payGroMatchSerials ?? collect())->join(', ') }}</span>
            </p>
        </div>
        @endif
        @if($payGroTokenStatus || $latestPayGroToken)
        <div class="px-5 py-3 border-b border-border bg-surface/40">
            @if($payGroTokenStatus)
            <p class="text-xs {{ $payGroTokenStatusIsError ? 'text-danger' : 'text-success' }}">{{ $payGroTokenStatus }}</p>
            @endif
            @error('payGroTokenStatus') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror

            @if($latestPayGroToken)
            <p class="text-xs text-muted mt-2">
                Latest token
                @if(($latestPayGroToken['token_source'] ?? '') === 'payment')
                <span class="text-ink">(from payment)</span>
                @endif
                :
                <span class="font-semibold text-ink font-mono">{{ $latestPayGroToken['generated_token_value'] ?? '—' }}</span>
                for
                <span class="font-mono text-ink">{{ $latestPayGroToken['product_serial_number'] ?? '—' }}</span>
                @if(($latestPayGroToken['token_source'] ?? '') === 'payment')
                paid {{ $latestPayGroToken['token_generation_date_display'] ?? '—' }}.
                @else
                generated {{ $latestPayGroToken['token_generation_date_display'] ?? '—' }}.
                @endif
            </p>
            @endif
        </div>
        @endif
        @if($tokens->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-surface border-b border-border">
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">Date</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">Type</th>
                        <th class="text-right text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">Tokens</th>
                        <th class="text-right text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden sm:table-cell">Days</th>
                        <th class="text-right text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden md:table-cell">Balance</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden lg:table-cell">Description</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    @foreach($tokens as $t)
                    <tr class="hover:bg-surface transition-colors">
                        <td class="px-4 py-3 text-sm text-ink whitespace-nowrap">{{ $t->occurred_at?->format('M j, Y') }}</td>
                        <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-medium bg-surface text-muted capitalize">{{ $t->type }}</span></td>
                        <td class="px-4 py-3 text-sm font-semibold text-right whitespace-nowrap {{ $t->tokens < 0 ? 'text-danger' : 'text-success' }}">{{ $t->tokens > 0 ? '+' : '' }}{{ number_format($t->tokens) }}</td>
                        <td class="px-4 py-3 text-sm text-muted text-right hidden sm:table-cell">{{ $t->days ? ($t->days > 0 ? '+' : '').$t->days : '—' }}</td>
                        <td class="px-4 py-3 text-sm text-ink text-right hidden md:table-cell">{{ number_format($t->balance_after) }}</td>
                        <td class="px-4 py-3 text-sm text-muted hidden lg:table-cell">{{ $t->description ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-border">{{ $tokens->links() }}</div>
        @else
        <div class="p-12 text-center">
            <p class="text-sm font-semibold text-ink">No token activity</p>
            <p class="text-xs text-muted mt-1">Token purchases and credits will appear here.</p>
        </div>
        @endif

        <div class="border-t border-border">
            <div class="px-5 py-4 border-b border-border bg-surface/40">
                <h4 class="text-sm font-semibold text-ink">Sent Token SMS History</h4>
                <p class="text-xs text-muted mt-0.5">Latest PayGro tokens sent to this customer, with SMS delivery status.</p>
            </div>
            @if($tokenSmsMessages->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-surface border-b border-border">
                            <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">Queued</th>
                            <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">Token</th>
                            <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden md:table-cell">Unit Serial</th>
                            <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">Delivery</th>
                            <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden lg:table-cell">Provider ID</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        @foreach($tokenSmsMessages as $sms)
                        @php
                            $paygroToken = is_array($sms->meta ?? null)
                                ? ($sms->meta['paygro_token'] ?? [])
                                : [];
                        @endphp
                        <tr class="hover:bg-surface transition-colors">
                            <td class="px-4 py-3 text-sm text-ink whitespace-nowrap">{{ $sms->created_at?->format('M j, Y g:i A') }}</td>
                            <td class="px-4 py-3">
                                <p class="text-sm font-semibold text-ink font-mono">{{ $paygroToken['generated_token_value'] ?? '—' }}</p>
                                @if($paygroToken['token_generation_date'] ?? null)
                                <p class="text-xs text-muted mt-0.5">Generated {{ \Carbon\Carbon::parse($paygroToken['token_generation_date'])->format('M j, Y g:i A') }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-muted font-mono hidden md:table-cell">{{ $paygroToken['product_serial_number'] ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium capitalize {{ $smsStatusColors[$sms->status] ?? 'bg-surface text-muted' }}">{{ $sms->status }}</span>
                                <p class="text-xs text-muted mt-1">
                                    @if($sms->delivered_at)
                                        Delivered {{ $sms->delivered_at->format('M j, Y g:i A') }}
                                    @elseif($sms->sent_at)
                                        Sent {{ $sms->sent_at->format('M j, Y g:i A') }}
                                    @elseif($sms->error_message)
                                        {{ Str::limit($sms->error_message, 60) }}
                                    @else
                                        Waiting for provider update
                                    @endif
                                </p>
                            </td>
                            <td class="px-4 py-3 text-xs text-muted font-mono hidden lg:table-cell">{{ $sms->provider_message_id ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="p-8 text-center">
                <p class="text-sm font-semibold text-ink">No token SMS sent yet</p>
                <p class="text-xs text-muted mt-1">When you send a PayGro token SMS, its delivery status will appear here.</p>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- ════════════════════ ASSETS ════════════════════ --}}
    @if($tab === 'assets')
    <div class="space-y-4">
        <div>
            <h3 class="text-sm font-semibold text-ink">Units & Assets</h3>
            <p class="text-xs text-muted mt-0.5">Synced from PayGro — serial numbers, plans, and allocation dates update automatically.</p>
        </div>

        @if($assets->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($assets as $a)
            @php
                $meta = is_array($a->meta) ? $a->meta : [];
                $saleId = $meta['paygro_sales_identifier'] ?? null;
                $planName = $meta['paygro_payment_plan'] ?? null;
            @endphp
            <div class="bg-surface-2 rounded-2xl border border-border p-5" wire:key="asset-{{ $a->id }}">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold text-ink truncate">{{ $a->product_name ?: 'Unit' }}</h3>
                        <p class="text-xs font-mono text-muted mt-0.5">{{ $a->unit_serial }}</p>
                        @if($saleId)
                        <p class="text-xs text-muted mt-0.5">Sale ID: <span class="font-mono">{{ $saleId }}</span></p>
                        @endif
                        @if($planName)
                        <p class="text-xs text-muted mt-0.5">{{ $planName }}</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        @php $unitRepayment = $a->repaymentStatusMeta(); @endphp
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $repaymentStatusColors[$unitRepayment['color']] ?? 'bg-surface text-muted' }}">{{ $unitRepayment['label'] }}</span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium capitalize {{ $assetColors[$a->status] ?? 'bg-surface text-muted' }}">{{ $a->status }}</span>
                    </div>
                </div>
                <div class="space-y-2">
                    @if($a->model)
                    <div class="flex items-center justify-between"><p class="text-xs text-muted">Model</p><p class="text-sm text-ink">{{ $a->model }}</p></div>
                    @endif
                    <div class="flex items-center justify-between"><p class="text-xs text-muted">Installed</p><p class="text-sm text-ink">{{ $a->installation_date?->format('M j, Y') ?? '—' }}</p></div>
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-muted">Warranty</p>
                        <p class="text-sm text-right">
                            @if($a->warranty_expiry)
                                <span class="{{ $a->is_under_warranty ? 'text-success' : 'text-danger' }}">{{ $a->warranty_expiry->format('M j, Y') }}</span>
                                <span class="text-xs text-muted">({{ $a->is_under_warranty ? 'active' : 'expired' }})</span>
                            @else — @endif
                        </p>
                    </div>
                </div>
                @if($a->notes)
                <p class="text-xs text-muted mt-3 pt-3 border-t border-border/60">{{ $a->notes }}</p>
                @endif
            </div>
            @endforeach
        </div>
        @else
        <div class="bg-surface-2 rounded-2xl border border-border p-12 text-center">
            <p class="text-sm font-semibold text-ink">No units synced yet</p>
            <p class="text-xs text-muted mt-1">Run <a href="{{ route('settings.paygro') }}" wire:navigate class="text-brand font-medium hover:underline">PayGro sync</a> to import units assigned to this customer.</p>
        </div>
        @endif
    </div>
    @endif

    {{-- ════════════════════ MESSAGES ════════════════════ --}}
    @if($tab === 'messages')
    <div class="space-y-4">
        {{-- Send SMS composer --}}
        <div class="bg-surface-2 rounded-2xl border border-border p-5">
            <h3 class="text-sm font-semibold text-ink mb-1">Send SMS</h3>
            @if($customer->sms_opted_out)
                <p class="text-xs text-warning mt-0.5 mb-3">This customer has opted out of SMS (replied STOP). Sending is disabled.</p>
            @else
                <p class="text-xs text-muted mt-0.5 mb-3">Send a message to {{ $customer->phone ?: 'this customer' }}.</p>
            @endif
            <div class="flex flex-col sm:flex-row gap-2">
                <textarea wire:model="smsBody" rows="2" maxlength="918" placeholder="Type your message..." class="input flex-1 @error('smsBody') !border-danger @enderror" @disabled($customer->sms_opted_out)></textarea>
                <button type="button" wire:click="sendSms" class="btn-primary shrink-0 self-start" wire:loading.attr="disabled" wire:target="sendSms" @disabled($customer->sms_opted_out)>
                    <span wire:loading.remove wire:target="sendSms">Send</span>
                    <span wire:loading wire:target="sendSms">Sending…</span>
                </button>
            </div>
            @error('smsBody') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
        </div>

    <div class="bg-surface-2 rounded-2xl border border-border p-5">
        @if($timeline->count() > 0)
        <div class="relative">
            <div class="absolute left-4 top-0 bottom-0 w-px bg-border"></div>
            <div class="space-y-4">
                @foreach($timeline as $event)
                <div class="relative flex gap-4 pl-2" wire:key="msg-{{ $loop->index }}">
                    <div @class([
                        'relative z-10 flex-shrink-0 w-8 h-8 rounded-full border-2 flex items-center justify-center',
                        'bg-info/10 border-info/30' => $event['direction'] === 'inbound',
                        'bg-brand/10 border-brand/30' => $event['direction'] !== 'inbound',
                    ])>
                        @if($event['channel'] === 'email')
                        <svg class="w-3.5 h-3.5 {{ $event['direction'] === 'inbound' ? 'text-info' : 'text-brand' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        @else
                        <svg class="w-3.5 h-3.5 {{ $event['direction'] === 'inbound' ? 'text-info' : 'text-brand' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0 pb-4">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-medium text-ink truncate">{{ $event['title'] }}</p>
                            <time class="text-xs text-muted shrink-0" title="{{ \Carbon\Carbon::parse($event['date'])->format('M j, Y g:i A') }}">{{ \Carbon\Carbon::parse($event['date'])->diffForHumans() }}</time>
                        </div>
                        <p class="text-xs text-muted mt-0.5">{{ Str::limit($event['body'], 160) }}</p>
                        <div class="flex items-center gap-1.5 mt-1">
                            <span class="inline-flex items-center gap-1 text-[10px] text-muted bg-surface px-1.5 py-0.5 rounded-full">
                                <span class="w-1.5 h-1.5 rounded-full {{ $event['direction'] === 'inbound' ? 'bg-info' : 'bg-brand' }}"></span>
                                {{ ucfirst($event['channel']) }} · {{ ucfirst($event['direction']) }}
                            </span>
                            @if($event['intent'])
                            <span class="text-[10px] text-muted bg-surface px-1.5 py-0.5 rounded-full capitalize">{{ str_replace('_', ' ', $event['intent']) }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @else
        <div class="p-8 text-center">
            <p class="text-sm font-semibold text-ink">No messages yet</p>
            <p class="text-xs text-muted mt-1">SMS and email history with this customer will appear here, including inbound replies.</p>
        </div>
        @endif
    </div>
    </div>
    @endif

    {{-- ════════════════════ COLLECTIONS ════════════════════ --}}
    @if($tab === 'collections')
    <div class="space-y-4">
        {{-- Current assignment / assign action --}}
        <div class="bg-surface-2 rounded-2xl border border-border p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-ink">Field Agent Assignment</h3>
                    <p class="text-xs text-muted mt-0.5">
                        @if($customer->assignedAgent)
                            Currently assigned to <span class="font-medium text-ink">{{ $customer->assignedAgent->name }}</span>
                        @else
                            Not currently assigned to a field agent.
                        @endif
                    </p>
                </div>
                @if(!$showAssignForm)
                <button type="button" wire:click="$set('showAssignForm', true)" class="btn-primary text-sm">{{ $customer->assignedAgent ? 'Reassign' : 'Assign Agent' }}</button>
                @endif
            </div>

            @if($showAssignForm)
            <div class="mt-4 pt-4 border-t border-border space-y-3 max-w-lg">
                <div>
                    <label class="block text-sm font-medium text-ink/80 mb-1">Field Agent</label>
                    <select wire:model="assignAgentId" class="input @error('assignAgentId') !border-danger @enderror">
                        <option value="">— Select agent —</option>
                        @foreach($agents as $agent)
                        <option value="{{ $agent->id }}">{{ $agent->name }} ({{ $agent->email }})</option>
                        @endforeach
                    </select>
                    @error('assignAgentId') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink/80 mb-1">Reason <span class="text-muted">(optional)</span></label>
                    <input type="text" wire:model="assignReason" placeholder="e.g. 45 days overdue, no response to reminders" class="input">
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink/80 mb-1">Notes <span class="text-muted">(optional)</span></label>
                    <textarea wire:model="assignNotes" rows="2" class="input" placeholder="Context for the field agent..."></textarea>
                </div>
                <div class="flex items-center gap-3">
                    <button type="button" wire:click="assignToAgent" class="btn-primary text-sm">Assign</button>
                    <button type="button" wire:click="$set('showAssignForm', false)" class="text-sm text-muted hover:text-ink">Cancel</button>
                </div>
            </div>
            @endif
        </div>

        {{-- Assignment history --}}
        <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
            <div class="px-5 py-4 border-b border-border"><h3 class="text-sm font-semibold text-ink">Assignment History</h3></div>
            @if($assignments->count() > 0)
            <div class="divide-y divide-border/60">
                @foreach($assignments as $assignment)
                <div class="p-5" wire:key="assignment-{{ $assignment->id }}">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="text-sm font-semibold text-ink">{{ $assignment->agent?->name ?? 'Unknown agent' }}</p>
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium capitalize {{ $assignmentColors[$assignment->status] ?? 'bg-surface text-muted' }}">{{ str_replace('_', ' ', $assignment->status) }}</span>
                            </div>
                            <p class="text-xs text-muted mt-1">
                                Assigned {{ $assignment->assigned_at?->format('M j, Y') }}
                                @if($assignment->assignedBy) by {{ $assignment->assignedBy->name }} @endif
                                @if($assignment->amount_at_assignment) · KES {{ number_format((float) $assignment->amount_at_assignment, 2) }} outstanding @endif
                            </p>
                            @if($assignment->reason)<p class="text-sm text-ink mt-2">{{ $assignment->reason }}</p>@endif
                            @if($assignment->notes)<p class="text-xs text-muted mt-1">{{ $assignment->notes }}</p>@endif
                        </div>
                        @if($assignment->is_open)
                        <div class="shrink-0" x-data="{ open: false }">
                            <div class="relative">
                                <button type="button" @click="open = !open" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm bg-surface border border-border rounded-lg hover:bg-surface-2 transition-colors">
                                    Update status
                                    <svg class="w-3.5 h-3.5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <div x-show="open" @click.outside="open = false" x-transition x-cloak class="absolute right-0 top-full mt-1 z-20 w-48 bg-surface-2 border border-border rounded-xl shadow-lg py-1">
                                    @foreach(['in_progress' => 'In Progress','promised_to_pay' => 'Promised to Pay','resolved' => 'Resolved','escalated' => 'Escalated','written_off' => 'Written Off'] as $statusKey => $statusLabel)
                                    <button type="button" wire:click="updateAssignmentStatus({{ $assignment->id }}, '{{ $statusKey }}')" @click="open = false"
                                            class="w-full text-left px-4 py-2 text-sm text-ink/80 hover:bg-surface transition-colors">{{ $statusLabel }}</button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        @elseif($assignment->resolved_at)
                        <p class="text-xs text-muted shrink-0">Closed {{ $assignment->resolved_at->format('M j, Y') }}</p>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="p-12 text-center">
                <p class="text-sm font-semibold text-ink">No assignments yet</p>
                <p class="text-xs text-muted mt-1">Assign this customer to a field agent to start collections tracking.</p>
            </div>
            @endif
        </div>
    </div>
    @endif
</div>
