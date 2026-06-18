<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-ink">Activity Log</h1>
            <p class="text-sm text-muted mt-0.5">A chronological record of actions taken across the platform.</p>
        </div>
        <div class="relative">
            <svg class="w-4 h-4 text-muted absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search activity…"
                   class="pl-9 pr-4 py-2 text-sm bg-surface-2 border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent placeholder-muted/60 w-64">
        </div>
    </div>

    {{-- Timeline --}}
    <div class="bg-surface-2 rounded-2xl border border-border overflow-hidden">
        @forelse ($activities as $activity)
            @php
                $name = $activity->causer?->name ?? 'System';
                $initials = collect(explode(' ', trim($name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') ?: 'S';
                $event = $activity->event ?: $activity->log_name ?: 'event';
            @endphp
            <div wire:key="activity-{{ $activity->id }}"
                 class="flex items-start gap-4 px-5 py-4 {{ ! $loop->last ? 'border-b border-border/50' : '' }}">
                <div class="w-9 h-9 rounded-full bg-brand/10 text-brand flex items-center justify-center text-xs font-bold shrink-0">{{ $initials }}</div>
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-medium text-ink">{{ $name }}</span>
                        <span class="badge badge-ghost capitalize">{{ str_replace('_', ' ', $event) }}</span>
                        @if ($activity->subject_type)
                            <span class="text-xs text-muted">{{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }}</span>
                        @endif
                    </div>
                    @if ($activity->description)
                        <p class="text-sm text-muted mt-0.5">{{ $activity->description }}</p>
                    @endif
                </div>
                <time class="text-xs text-muted whitespace-nowrap shrink-0" title="{{ $activity->created_at->format('M j, Y H:i') }}">
                    {{ $activity->created_at->diffForHumans() }}
                </time>
            </div>
        @empty
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <div class="w-14 h-14 bg-surface rounded-2xl flex items-center justify-center mb-4 border border-border">
                    <svg class="w-7 h-7 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <p class="text-sm font-semibold text-ink">No activity recorded</p>
                <p class="text-xs text-muted mt-1">Actions taken across the app will appear here.</p>
            </div>
        @endforelse

        @if ($activities->hasPages())
            <div class="px-4 py-3 border-t border-border/40">{{ $activities->links() }}</div>
        @endif
    </div>
</div>
