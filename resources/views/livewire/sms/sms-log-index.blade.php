@php
    $smsStatusColors = [
        'queued' => 'bg-warning/15 text-warning',
        'sent' => 'bg-info/15 text-info',
        'success' => 'bg-success/15 text-success',
        'processed' => 'bg-info/15 text-info',
        'submitted' => 'bg-info/15 text-info',
        'buffered' => 'bg-info/15 text-info',
        'delivered' => 'bg-success/15 text-success',
        'failed' => 'bg-danger/15 text-danger',
        'rejected' => 'bg-danger/15 text-danger',
        'unknown' => 'bg-warning/15 text-warning',
    ];
@endphp

<div class="space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-ink">SMS Logs</h1>
            <p class="text-sm text-muted mt-0.5">All sent and received SMS messages with delivery status and provider IDs.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('settings.sms') }}" wire:navigate class="btn-secondary text-sm">SMS Settings</a>
            <a href="{{ route('analytics') }}" wire:navigate class="btn-secondary text-sm">Analytics</a>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach([
            ['label' => 'Matching', 'value' => $stats['total'], 'tone' => 'text-ink'],
            ['label' => 'Successful', 'value' => $stats['success'], 'tone' => 'text-success'],
            ['label' => 'Failed', 'value' => $stats['failed'], 'tone' => 'text-danger'],
            ['label' => 'Today', 'value' => $stats['today'], 'tone' => 'text-brand'],
        ] as $card)
        <div class="bg-surface-2 rounded-2xl border border-border p-4">
            <p class="text-xs font-semibold text-muted uppercase tracking-wider">{{ $card['label'] }}</p>
            <p class="text-2xl font-bold mt-1 {{ $card['tone'] }}">{{ number_format($card['value']) }}</p>
        </div>
        @endforeach
    </div>

    <div class="bg-surface-2 rounded-2xl border border-border p-4">
        <div class="flex flex-col xl:flex-row xl:items-end gap-3">
            <div class="flex-1 min-w-[12rem]">
                <label class="block text-xs font-medium text-muted mb-1">Search</label>
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Phone, message, customer, provider ID…" class="input w-full">
            </div>
            <div class="w-full sm:w-40">
                <label class="block text-xs font-medium text-muted mb-1">Direction</label>
                <select wire:model.live="direction" class="select w-full">
                    <option value="outbound">Outbound (sent)</option>
                    <option value="inbound">Inbound (received)</option>
                    <option value="">All</option>
                </select>
            </div>
            <div class="w-full sm:w-40">
                <label class="block text-xs font-medium text-muted mb-1">Status</label>
                <select wire:model.live="status" class="select w-full">
                    <option value="">All statuses</option>
                    <option value="success">Success</option>
                    <option value="sent">Sent</option>
                    <option value="delivered">Delivered</option>
                    <option value="queued">Queued</option>
                    <option value="failed">Failed</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div class="w-full sm:w-44">
                <label class="block text-xs font-medium text-muted mb-1">Source</label>
                <select wire:model.live="source" class="select w-full">
                    <option value="">All sources</option>
                    @foreach($sources as $sourceOption)
                    <option value="{{ $sourceOption }}">{{ ucfirst(str_replace('_', ' ', $sourceOption)) }}</option>
                    @endforeach
                </select>
            </div>
            <label class="inline-flex items-center gap-2 text-sm text-ink pb-2.5 cursor-pointer">
                <input type="checkbox" wire:model.live="hideTests" class="rounded border-border text-brand focus:ring-brand/30">
                Hide tests
            </label>
            @if($search !== '' || $status !== '' || $source !== '' || $hideTests || $direction !== 'outbound')
            <button type="button" wire:click="clearFilters" class="btn-secondary text-sm pb-2.5">Clear</button>
            @endif
        </div>
    </div>

    <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
        @if($messages->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-surface border-b border-border">
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">When</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">To / From</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">Customer</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden lg:table-cell">Message</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">Source</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">Status</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden xl:table-cell">Provider ID</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    @foreach($messages as $sms)
                    <tr wire:key="sms-log-{{ $sms->id }}" class="hover:bg-surface transition-colors">
                        <td class="px-4 py-3 text-sm text-ink whitespace-nowrap">
                            {{ $sms->created_at?->format('M j, Y g:i A') }}
                            @if($sms->sent_at && ! $sms->created_at?->equalTo($sms->sent_at))
                            <p class="text-xs text-muted mt-0.5">Sent {{ $sms->sent_at->format('g:i A') }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <p class="text-sm font-mono text-ink">{{ $sms->direction === 'inbound' ? ($sms->from ?: '—') : ($sms->to ?: '—') }}</p>
                            <p class="text-xs text-muted mt-0.5 capitalize">{{ $sms->direction }}</p>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            @if($sms->customer)
                            <a href="{{ route('customers.show', $sms->customer) }}" wire:navigate class="font-medium text-brand hover:underline">
                                {{ $sms->customer->full_name }}
                            </a>
                            @if($sms->customer->account_number)
                            <p class="text-xs text-muted font-mono mt-0.5">{{ $sms->customer->account_number }}</p>
                            @endif
                            @else
                            <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-muted hidden lg:table-cell max-w-xs">
                            <p class="line-clamp-2">{{ $sms->body }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-xs font-medium text-ink">{{ $sms->sourceLabel() }}</span>
                            @if($sms->isTestMessage())
                            <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase bg-warning/15 text-warning">Test</span>
                            @endif
                            @if($sms->campaign)
                            <p class="text-xs text-muted mt-0.5 truncate max-w-[10rem]" title="{{ $sms->campaign->name }}">{{ $sms->campaign->name }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium capitalize {{ $smsStatusColors[$sms->status] ?? 'bg-surface text-muted' }}">{{ $sms->status }}</span>
                            @if($sms->error_message)
                            <p class="text-xs text-danger mt-1 max-w-[12rem]" title="{{ $sms->error_message }}">{{ Str::limit($sms->error_message, 48) }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs font-mono text-muted hidden xl:table-cell">
                            {{ $sms->provider_message_id ?: '—' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-border">{{ $messages->links() }}</div>
        @else
        <div class="flex flex-col items-center justify-center py-16 text-center px-4">
            <div class="w-14 h-14 bg-surface rounded-2xl flex items-center justify-center mb-4 border border-border">
                <svg class="w-7 h-7 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
            </div>
            <p class="text-sm font-semibold text-ink">No SMS logs found</p>
            <p class="text-xs text-muted mt-1">Try adjusting your filters, or send a test from Settings → SMS.</p>
        </div>
        @endif
    </div>
</div>
