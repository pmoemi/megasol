@php
    $assignmentColors = [
        'assigned' => 'bg-info/15 text-info','in_progress' => 'bg-warning/15 text-warning','promised_to_pay' => 'bg-brand/15 text-brand',
        'resolved' => 'bg-success/15 text-success','escalated' => 'bg-danger/15 text-danger','written_off' => 'bg-surface text-muted',
    ];
@endphp

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-ink">Collections</h1>
            <p class="text-sm text-muted mt-0.5">Assign defaulting customers to field agents and track recovery.</p>
        </div>
        @if($mode === 'assignments')
        <select wire:model.live="agentFilter" class="input max-w-xs">
            <option value="">All agents</option>
            @foreach($agents as $agent)
            <option value="{{ $agent->id }}">{{ $agent->name }}</option>
            @endforeach
        </select>
        @endif
    </div>

    {{-- Tabs --}}
    <div class="border-b border-border">
        <nav class="flex gap-6 -mb-px overflow-x-auto">
            @foreach($tabs as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')"
                    class="pb-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap flex-shrink-0
                           {{ $tab === $key ? 'border-brand text-brand' : 'border-transparent text-muted hover:text-ink/80 hover:border-border' }}">
                {{ $label }}
            </button>
            @endforeach
        </nav>
    </div>

    {{-- ── Needs assignment (raw customers) ── --}}
    @if($mode === 'customers')
    <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
        @if($items->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-surface border-b border-border">
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">Customer</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden sm:table-cell">Phone</th>
                        <th class="text-right text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">Outstanding</th>
                        <th class="text-right text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden md:table-cell">Arrears</th>
                        <th class="w-32 px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    @foreach($items as $customer)
                    <tr class="hover:bg-surface transition-colors" wire:key="cust-{{ $customer->id }}">
                        <td class="px-4 py-3">
                            <a href="{{ route('customers.show', $customer) }}" wire:navigate class="text-sm font-semibold text-ink hover:text-brand transition-colors">{{ $customer->full_name }}</a>
                            <p class="text-xs text-muted font-mono">{{ $customer->account_number ?? '—' }}</p>
                        </td>
                        <td class="px-4 py-3 text-sm text-muted hidden sm:table-cell">{{ $customer->phone ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm font-semibold text-ink text-right whitespace-nowrap">KES {{ number_format((float) $customer->outstanding_balance, 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right hidden md:table-cell {{ $customer->days_in_arrears > 0 ? 'text-danger' : 'text-muted' }}">
                            @if($customer->days_in_arrears > 0)
                                {{ $customer->days_in_arrears }} days
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" wire:click="openAssign({{ $customer->id }})" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-brand bg-brand/5 rounded-lg hover:bg-brand/10 transition-colors">Assign</button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-border">{{ $items->links() }}</div>
        @else
        <div class="p-12 text-center">
            <p class="text-sm font-semibold text-ink">Nothing to assign 🎉</p>
            <p class="text-xs text-muted mt-1">No unassigned overdue or defaulting customers right now.</p>
        </div>
        @endif
    </div>
    @else
    {{-- ── Open / resolved assignments ── --}}
    <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
        @if($items->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-surface border-b border-border">
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">Customer</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">Agent</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3">Status</th>
                        <th class="text-right text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden md:table-cell">Amount</th>
                        <th class="text-left text-xs font-semibold text-muted uppercase tracking-wider px-4 py-3 hidden lg:table-cell">Assigned</th>
                        <th class="w-28 px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/60">
                    @foreach($items as $assignment)
                    <tr class="hover:bg-surface transition-colors" wire:key="asg-{{ $assignment->id }}">
                        <td class="px-4 py-3">
                            @if($assignment->customer)
                            <a href="{{ route('customers.show', $assignment->customer) }}" wire:navigate class="text-sm font-semibold text-ink hover:text-brand transition-colors">{{ $assignment->customer->full_name }}</a>
                            @if($assignment->reason)<p class="text-xs text-muted truncate max-w-xs">{{ $assignment->reason }}</p>@endif
                            @else
                            <span class="text-sm text-muted">Deleted customer</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-ink">{{ $assignment->agent?->name ?? '—' }}</td>
                        <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-medium capitalize {{ $assignmentColors[$assignment->status] ?? 'bg-surface text-muted' }}">{{ str_replace('_', ' ', $assignment->status) }}</span></td>
                        <td class="px-4 py-3 text-sm text-ink text-right hidden md:table-cell whitespace-nowrap">{{ $assignment->amount_at_assignment !== null ? 'KES '.number_format((float) $assignment->amount_at_assignment, 2) : '—' }}</td>
                        <td class="px-4 py-3 text-sm text-muted hidden lg:table-cell whitespace-nowrap">{{ $assignment->assigned_at?->format('M j, Y') }}</td>
                        <td class="px-4 py-3 text-right">
                            @if($assignment->is_open)
                            <button type="button" wire:click="markResolved({{ $assignment->id }})" class="text-sm font-medium text-success hover:underline">Resolve</button>
                            @elseif($assignment->resolved_at)
                            <span class="text-xs text-muted">{{ $assignment->resolved_at->format('M j, Y') }}</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-border">{{ $items->links() }}</div>
        @else
        <div class="p-12 text-center">
            <p class="text-sm font-semibold text-ink">No {{ $tab === 'resolved' ? 'resolved' : 'open' }} cases</p>
            <p class="text-xs text-muted mt-1">Assignments will appear here as field agents work them.</p>
        </div>
        @endif
    </div>
    @endif

    {{-- Assign modal --}}
    @if($assignCustomerId)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.4);">
        <div class="bg-surface-2 rounded-2xl border border-border p-6 max-w-md w-full shadow-xl">
            <h3 class="text-lg font-semibold text-ink mb-4">Assign to Field Agent</h3>
            <div class="space-y-3">
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
                    <input type="text" wire:model="assignReason" placeholder="e.g. 60 days overdue" class="input">
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 mt-5">
                <button type="button" wire:click="$set('assignCustomerId', null)" class="px-4 py-2 text-sm text-muted hover:text-ink">Cancel</button>
                <button type="button" wire:click="assign" class="btn-primary text-sm">Assign</button>
            </div>
        </div>
    </div>
    @endif
</div>
