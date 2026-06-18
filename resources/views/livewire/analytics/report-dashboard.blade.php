<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-ink">Analytics</h1>
            <p class="text-sm text-muted mt-0.5">Messaging performance across SMS and email.</p>
        </div>
        <button type="button" wire:click="exportCampaigns" class="btn-secondary text-sm flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Export Campaign Report
        </button>
    </div>

    {{-- Stat cards --}}
    @php
        $cards = [
            ['label' => 'Customers', 'value' => $totalCustomers, 'tone' => 'text-ink'],
            ['label' => 'SMS Sent', 'value' => $totalSent, 'tone' => 'text-ink'],
            ['label' => 'SMS Delivered', 'value' => $totalDelivered, 'tone' => 'text-success'],
            ['label' => 'SMS Failed', 'value' => $totalFailed, 'tone' => 'text-danger'],
            ['label' => 'Emails Sent', 'value' => $totalEmailsSent, 'tone' => 'text-ink'],
            ['label' => 'Emails Opened', 'value' => $totalEmailsOpened, 'tone' => 'text-brand'],
        ];
    @endphp
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-4">
        @foreach ($cards as $card)
            <div class="bg-surface-2 rounded-2xl border border-border p-5">
                <p class="text-xs font-semibold text-muted uppercase tracking-wider">{{ $card['label'] }}</p>
                <p class="text-2xl font-bold mt-1 {{ $card['tone'] }}">{{ number_format($card['value']) }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="card">
            <div class="card-body">
                <h3 class="text-sm font-semibold text-ink mb-4">SMS Volume (Last 14 Days)</h3>
                @if (count($dailyStats) > 0)
                    <div class="space-y-2">
                        @foreach ($dailyStats as $stat)
                            <div class="flex items-center gap-3">
                                <span class="text-xs text-muted w-24">{{ $stat['date'] }}</span>
                                <div class="flex-1 bg-surface rounded-full h-2 overflow-hidden">
                                    @php $max = max(array_column($dailyStats, 'count')) ?: 1; @endphp
                                    <div class="bg-brand h-full rounded-full" style="width: {{ ($stat['count'] / $max) * 100 }}%"></div>
                                </div>
                                <span class="text-xs font-medium w-8 text-right">{{ $stat['count'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-muted">No SMS data yet.</p>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h3 class="text-sm font-semibold text-ink mb-4">Status Breakdown</h3>
                @if (count($statusBreakdown) > 0)
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th class="text-right">Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($statusBreakdown as $status => $count)
                                <tr>
                                    <td><span class="badge badge-neutral">{{ ucfirst($status) }}</span></td>
                                    <td class="text-right font-medium">{{ number_format($count) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-sm text-muted">No status data yet.</p>
                @endif
            </div>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="card-header px-4 py-3 border-b border-border/40">
            <h3 class="text-sm font-semibold text-ink">Recent Campaigns</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Recipients</th>
                        <th>Created By</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentCampaigns as $campaign)
                        <tr>
                            <td class="font-medium">
                                @if (in_array($campaign->status, ['sent', 'sending']))
                                    <a href="{{ route('campaigns.report', $campaign->id) }}" wire:navigate class="text-brand hover:text-brand-strong">{{ $campaign->name }}</a>
                                @else
                                    {{ $campaign->name }}
                                @endif
                            </td>
                            <td><span class="badge badge-neutral">{{ ucfirst($campaign->status) }}</span></td>
                            <td>{{ $campaign->stats['total'] ?? $campaign->recipients()->count() }}</td>
                            <td>{{ $campaign->creator?->name ?? '—' }}</td>
                            <td>{{ $campaign->created_at->format('M j, Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-8">No campaigns yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
