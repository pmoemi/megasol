<div class="space-y-6 text-ink">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
            <a href="{{ route('campaigns.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm text-muted hover:text-ink mb-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back to campaigns
            </a>
            <h1 class="text-2xl font-bold text-ink">{{ $campaign->name }}</h1>
            <p class="text-sm text-muted mt-0.5">
                <span class="badge {{ $campaign->status === 'sent' ? 'badge-success' : 'badge-ghost' }}">{{ ucfirst($campaign->status) }}</span>
                <span class="capitalize">{{ $campaign->channel }}</span> campaign
                @if($campaign->sent_at) · sent {{ $campaign->sent_at->diffForHumans() }} @endif
            </p>
        </div>
        @if($campaign->status === 'draft')
            <a href="{{ route('campaigns.edit', $campaign) }}" wire:navigate class="btn-secondary shrink-0">Edit Campaign</a>
        @endif
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @php
            $cards = [
                ['label' => 'Recipients', 'value' => number_format($stats['recipients']), 'sub' => null],
                ['label' => 'Delivered', 'value' => number_format($stats['delivered']), 'sub' => null],
            ];
            if ($campaign->isEmail()) {
                $cards[] = ['label' => 'Opened', 'value' => number_format($stats['opened']), 'sub' => $stats['open_rate'].'% open rate'];
                $cards[] = ['label' => 'Clicked', 'value' => number_format($stats['clicked']), 'sub' => $stats['click_rate'].'% click rate'];
            } else {
                $cards[] = ['label' => 'Sent', 'value' => number_format($stats['sent']), 'sub' => null];
                $cards[] = ['label' => 'Failed', 'value' => number_format($stats['failed']), 'sub' => null];
            }
        @endphp
        @foreach($cards as $card)
            <div class="bg-surface-2 rounded-2xl border border-border p-5">
                <p class="text-xs font-semibold text-muted uppercase tracking-wider">{{ $card['label'] }}</p>
                <p class="text-2xl font-bold text-ink mt-1">{{ $card['value'] }}</p>
                @if($card['sub'])<p class="text-xs text-muted mt-0.5">{{ $card['sub'] }}</p>@endif
            </div>
        @endforeach
    </div>

    {{-- A/B test breakdown --}}
    @if($campaign->isAbTest() && $variantStats->isNotEmpty())
        <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
            <div class="px-5 py-4 border-b border-border">
                <h2 class="text-base font-semibold text-ink">A/B Test Results</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Variant</th><th>Subject</th><th>Split</th><th>Sent</th><th>Opened</th><th>Clicked</th></tr></thead>
                    <tbody>
                        @foreach($variantStats as $v)
                            <tr>
                                <td class="font-semibold">{{ $v['variant'] }}</td>
                                <td>{{ Str::limit($v['subject'], 50) }}</td>
                                <td>{{ $v['percentage'] }}%</td>
                                <td>{{ number_format($v['sent']) }}</td>
                                <td>{{ number_format($v['opened']) }} <span class="text-xs text-muted">({{ $v['open_rate'] }}%)</span></td>
                                <td>{{ number_format($v['clicked']) }} <span class="text-xs text-muted">({{ $v['click_rate'] }}%)</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Tabs --}}
    <div class="flex items-center gap-1 border-b border-border">
        @php
            $tabs = ['overview' => 'Overview', 'recipients' => 'Recipients'];
            if ($campaign->isEmail()) { $tabs['opens'] = 'Opens'; $tabs['clicks'] = 'Clicks'; }
        @endphp
        @foreach($tabs as $key => $label)
            <button type="button" wire:click="$set('activeTab', '{{ $key }}')" @class([
                'px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors',
                'border-brand text-brand' => $activeTab === $key,
                'border-transparent text-muted hover:text-ink' => $activeTab !== $key,
            ])>{{ $label }}</button>
        @endforeach
    </div>

    {{-- Overview tab: top links --}}
    @if($activeTab === 'overview')
        <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
            <div class="px-5 py-4 border-b border-border">
                <h2 class="text-base font-semibold text-ink">{{ $campaign->isEmail() ? 'Top Clicked Links' : 'Delivery Summary' }}</h2>
            </div>
            @if($campaign->isEmail())
                @if($links->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead><tr><th>URL</th><th class="text-right">Clicks</th></tr></thead>
                            <tbody>
                                @foreach($links as $link)
                                    <tr>
                                        <td class="font-mono text-xs truncate max-w-md">{{ $link->original_url }}</td>
                                        <td class="text-right font-medium">{{ number_format($link->clicks_count) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="px-5 py-8 text-sm text-muted text-center">No link clicks recorded yet.</p>
                @endif
            @else
                <div class="p-5 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                    <div><p class="text-muted">Sent</p><p class="font-semibold text-ink">{{ number_format($stats['sent']) }}</p></div>
                    <div><p class="text-muted">Delivered</p><p class="font-semibold text-ink">{{ number_format($stats['delivered']) }}</p></div>
                    <div><p class="text-muted">Failed</p><p class="font-semibold text-ink">{{ number_format($stats['failed']) }}</p></div>
                    <div><p class="text-muted">Recipients</p><p class="font-semibold text-ink">{{ number_format($stats['recipients']) }}</p></div>
                </div>
            @endif
        </div>
    @endif

    {{-- Recipients tab --}}
    @if($activeTab === 'recipients')
        <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
            <div class="px-5 py-4 border-b border-border flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-ink">Recipients</h2>
                <select wire:model.live="recipientFilter" class="input w-40 text-sm">
                    <option value="all">All statuses</option>
                    <option value="queued">Queued</option>
                    <option value="sent">Sent</option>
                    <option value="delivered">Delivered</option>
                    <option value="failed">Failed</option>
                </select>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Customer</th><th>{{ $campaign->isEmail() ? 'Email' : 'Phone' }}</th><th>Status</th>@if($campaign->isEmail())<th>Opened</th><th>Clicked</th>@endif</tr></thead>
                    <tbody>
                        @forelse($recipients as $r)
                            <tr wire:key="rcpt-{{ $r->id }}">
                                <td class="font-medium">{{ trim(($r->customer->first_name ?? '').' '.($r->customer->last_name ?? '')) ?: '—' }}</td>
                                <td class="text-sm">{{ $campaign->isEmail() ? ($r->email ?: $r->customer?->email) : ($r->phone ?: $r->customer?->phone) }}</td>
                                <td><span class="badge {{ $r->status === 'failed' ? 'badge-danger' : ($r->status === 'sent' || $r->status === 'delivered' ? 'badge-success' : 'badge-ghost') }}">{{ ucfirst($r->status) }}</span></td>
                                @if($campaign->isEmail())
                                    <td class="text-sm">{{ $r->opened_at?->diffForHumans() ?? '—' }}</td>
                                    <td class="text-sm">{{ $r->clicked_at?->diffForHumans() ?? '—' }}</td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-muted">No recipients found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($recipients->hasPages())<div class="px-4 py-3 border-t border-border/40">{{ $recipients->links() }}</div>@endif
        </div>
    @endif

    {{-- Opens tab --}}
    @if($activeTab === 'opens')
        <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
            <div class="px-5 py-4 border-b border-border"><h2 class="text-base font-semibold text-ink">Opened By</h2></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Customer</th><th>Email</th><th>Opened</th></tr></thead>
                    <tbody>
                        @forelse($openedRecipients as $r)
                            <tr wire:key="open-{{ $r->id }}">
                                <td class="font-medium">{{ trim(($r->customer->first_name ?? '').' '.($r->customer->last_name ?? '')) ?: '—' }}</td>
                                <td class="text-sm">{{ $r->email ?: $r->customer?->email }}</td>
                                <td class="text-sm">{{ $r->opened_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-5 py-8 text-center text-sm text-muted">No opens recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($openedRecipients instanceof \Illuminate\Contracts\Pagination\Paginator && $openedRecipients->hasPages())<div class="px-4 py-3 border-t border-border/40">{{ $openedRecipients->links() }}</div>@endif
        </div>
    @endif

    {{-- Clicks tab --}}
    @if($activeTab === 'clicks')
        <div class="space-y-6">
            <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
                <div class="px-5 py-4 border-b border-border"><h2 class="text-base font-semibold text-ink">Top Clicked Links</h2></div>
                @if($links->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead><tr><th>URL</th><th class="text-right">Clicks</th></tr></thead>
                            <tbody>
                                @foreach($links as $link)
                                    <tr><td class="font-mono text-xs truncate max-w-md">{{ $link->original_url }}</td><td class="text-right font-medium">{{ number_format($link->clicks_count) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="px-5 py-8 text-sm text-muted text-center">No clicks recorded yet.</p>
                @endif
            </div>

            <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
                <div class="px-5 py-4 border-b border-border"><h2 class="text-base font-semibold text-ink">Clicked By</h2></div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Customer</th><th>Email</th><th>Clicked</th></tr></thead>
                        <tbody>
                            @forelse($clickedRecipients as $r)
                                <tr wire:key="click-{{ $r->id }}">
                                    <td class="font-medium">{{ trim(($r->customer->first_name ?? '').' '.($r->customer->last_name ?? '')) ?: '—' }}</td>
                                    <td class="text-sm">{{ $r->email ?: $r->customer?->email }}</td>
                                    <td class="text-sm">{{ $r->clicked_at?->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-5 py-8 text-center text-sm text-muted">No clicks recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($clickedRecipients instanceof \Illuminate\Contracts\Pagination\Paginator && $clickedRecipients->hasPages())<div class="px-4 py-3 border-t border-border/40">{{ $clickedRecipients->links() }}</div>@endif
            </div>
        </div>
    @endif
</div>
