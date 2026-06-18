<div class="space-y-6">
    @if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
         class="p-3 bg-success/10 border border-success/20 text-success rounded-xl text-sm flex items-center justify-between">
        <span>{{ session('success') }}</span>
        <button @click="show = false" class="text-success hover:text-success/80">&times;</button>
    </div>
    @endif

    <div class="flex items-center justify-between gap-4">
        <div>
            <a href="{{ route('customers.index') }}" wire:navigate class="text-sm text-muted hover:text-ink">&larr; Back to customers</a>
            <h1 class="text-2xl font-bold text-ink mt-2">Segments</h1>
            <p class="text-sm text-muted mt-1">Dynamically group customers by product type, location, payment status, and more.</p>
        </div>
        @if(!$showForm)
        <button wire:click="$set('showForm', true)" class="inline-flex items-center gap-2 px-4 py-2.5 bg-brand text-white text-sm font-semibold rounded-xl hover:bg-brand-strong transition-colors whitespace-nowrap">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            New Segment
        </button>
        @endif
    </div>

    {{-- Create / Edit form --}}
    @if($showForm)
    <div class="bg-surface-2 rounded-2xl border border-border p-6">
        <h2 class="text-lg font-semibold text-ink mb-4">{{ $editingId ? 'Edit Segment' : 'Create Segment' }}</h2>

        <div class="space-y-4 max-w-3xl">
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-ink/80 mb-1">Segment Name</label>
                    <input type="text" wire:model="name" placeholder="e.g. Overdue Solar Home customers in Nairobi"
                           class="w-full px-4 py-2.5 text-sm border border-border rounded-xl bg-surface focus:outline-none focus:ring-2 focus:ring-brand/40">
                    @error('name') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink/80 mb-1">Description <span class="text-muted">(optional)</span></label>
                    <input type="text" wire:model="description" placeholder="What is this segment used for?"
                           class="w-full px-4 py-2.5 text-sm border border-border rounded-xl bg-surface focus:outline-none focus:ring-2 focus:ring-brand/40">
                </div>
            </div>

            {{-- Match type --}}
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-2">Match</label>
                <div class="inline-flex items-center gap-1 bg-surface rounded-lg p-1">
                    <button type="button" wire:click="$set('match', 'all')" @class([
                        'px-3 py-1.5 text-xs font-medium rounded-md transition-colors',
                        'bg-surface-2 text-ink shadow-sm' => $match === 'all',
                        'text-muted hover:text-ink/80' => $match !== 'all',
                    ])>All conditions (AND)</button>
                    <button type="button" wire:click="$set('match', 'any')" @class([
                        'px-3 py-1.5 text-xs font-medium rounded-md transition-colors',
                        'bg-surface-2 text-ink shadow-sm' => $match === 'any',
                        'text-muted hover:text-ink/80' => $match !== 'any',
                    ])>Any condition (OR)</button>
                </div>
            </div>

            {{-- Condition rows --}}
            <div class="space-y-3">
                <label class="block text-sm font-medium text-ink/80">Conditions</label>

                @foreach($conditions as $i => $condition)
                    @php
                        $fieldType = $fields[$condition['field']]['type'] ?? 'text';
                        $operators = $operatorsByType[$fieldType] ?? $operatorsByType['text'];
                        $operator = $condition['operator'] ?? array_key_first($operators);
                        $isRange = in_array($operator, $rangeOperators, true);
                        $isList = in_array($operator, $listOperators, true);
                        $isValueless = in_array($operator, $valuelessOperators, true);
                    @endphp
                    <div class="flex flex-wrap items-start gap-2 p-3 bg-surface rounded-xl border border-border" wire:key="condition-{{ $i }}">
                        {{-- Field --}}
                        <select wire:model="conditions.{{ $i }}.field" class="px-3 py-2 text-sm border border-border rounded-lg bg-surface-2 focus:outline-none focus:ring-2 focus:ring-brand/40 min-w-[160px]">
                            @foreach($fields as $key => $meta)
                                <option value="{{ $key }}">{{ $meta['label'] }}</option>
                            @endforeach
                        </select>

                        {{-- Operator --}}
                        <select wire:model="conditions.{{ $i }}.operator" class="px-3 py-2 text-sm border border-border rounded-lg bg-surface-2 focus:outline-none focus:ring-2 focus:ring-brand/40 min-w-[150px]">
                            @foreach($operators as $opKey => $opLabel)
                                <option value="{{ $opKey }}">{{ $opLabel }}</option>
                            @endforeach
                        </select>

                        {{-- Value --}}
                        <div class="flex-1 min-w-[160px]">
                            @if($isValueless)
                                {{-- no value needed --}}
                            @elseif($isList && $fieldType === 'select')
                                <div class="flex flex-wrap gap-2">
                                    @foreach($fields[$condition['field']]['options'] as $optKey => $optLabel)
                                        <label class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs bg-surface-2 border border-border rounded-lg cursor-pointer hover:border-brand/30">
                                            <input type="checkbox" wire:model="conditions.{{ $i }}.value" value="{{ $optKey }}" class="rounded border-border text-brand focus:ring-brand/40">
                                            {{ $optLabel }}
                                        </label>
                                    @endforeach
                                </div>
                            @elseif($isRange && $fieldType === 'date')
                                <div class="flex items-center gap-2">
                                    <input type="date" wire:model="conditions.{{ $i }}.value.0" class="w-full px-3 py-2 text-sm border border-border rounded-lg bg-surface-2 focus:outline-none focus:ring-2 focus:ring-brand/40">
                                    <span class="text-xs text-muted">and</span>
                                    <input type="date" wire:model="conditions.{{ $i }}.value.1" class="w-full px-3 py-2 text-sm border border-border rounded-lg bg-surface-2 focus:outline-none focus:ring-2 focus:ring-brand/40">
                                </div>
                            @elseif($isRange)
                                <div class="flex items-center gap-2">
                                    <input type="number" step="0.01" wire:model="conditions.{{ $i }}.value.0" placeholder="Min" class="w-full px-3 py-2 text-sm border border-border rounded-lg bg-surface-2 focus:outline-none focus:ring-2 focus:ring-brand/40">
                                    <span class="text-xs text-muted">and</span>
                                    <input type="number" step="0.01" wire:model="conditions.{{ $i }}.value.1" placeholder="Max" class="w-full px-3 py-2 text-sm border border-border rounded-lg bg-surface-2 focus:outline-none focus:ring-2 focus:ring-brand/40">
                                </div>
                            @elseif($fieldType === 'select')
                                <select wire:model="conditions.{{ $i }}.value" class="w-full px-3 py-2 text-sm border border-border rounded-lg bg-surface-2 focus:outline-none focus:ring-2 focus:ring-brand/40">
                                    <option value="">— Select —</option>
                                    @foreach($fields[$condition['field']]['options'] as $optKey => $optLabel)
                                        <option value="{{ $optKey }}">{{ $optLabel }}</option>
                                    @endforeach
                                </select>
                            @elseif($fieldType === 'number')
                                <input type="number" step="0.01" wire:model="conditions.{{ $i }}.value" placeholder="Value" class="w-full px-3 py-2 text-sm border border-border rounded-lg bg-surface-2 focus:outline-none focus:ring-2 focus:ring-brand/40">
                            @elseif($fieldType === 'date')
                                <input type="date" wire:model="conditions.{{ $i }}.value" class="w-full px-3 py-2 text-sm border border-border rounded-lg bg-surface-2 focus:outline-none focus:ring-2 focus:ring-brand/40">
                            @else
                                <input type="text" wire:model="conditions.{{ $i }}.value" placeholder="Value" class="w-full px-3 py-2 text-sm border border-border rounded-lg bg-surface-2 focus:outline-none focus:ring-2 focus:ring-brand/40">
                            @endif
                        </div>

                        {{-- Remove --}}
                        <button type="button" wire:click="removeCondition({{ $i }})" class="p-2 text-muted hover:text-danger transition-colors" aria-label="Remove condition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                @endforeach

                <button type="button" wire:click="addCondition" class="inline-flex items-center gap-1.5 text-sm font-medium text-brand hover:text-brand-strong transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Add condition
                </button>
            </div>

            {{-- Live preview count --}}
            <div class="p-4 bg-brand/5 border border-brand/20 rounded-xl">
                <p class="text-2xl font-bold text-ink">{{ number_format($this->previewCount) }}</p>
                <p class="text-sm text-muted">{{ $this->previewCount === 1 ? 'customer' : 'customers' }} currently match this segment</p>
            </div>

            <div class="flex items-center gap-3">
                <button wire:click="createSegment" class="px-5 py-2.5 bg-brand text-white text-sm font-semibold rounded-xl hover:bg-brand-strong transition-colors">
                    {{ $editingId ? 'Update Segment' : 'Create Segment' }}
                </button>
                <button wire:click="resetForm" class="px-4 py-2.5 text-sm text-muted hover:text-ink transition-colors">Cancel</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Segment list --}}
    @if($segments->isEmpty() && !$showForm)
    <div class="bg-surface-2 rounded-2xl border border-border p-12 text-center">
        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-brand/10 text-brand mx-auto mb-4">
            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h18M3 9h18M3 13.5h18M3 18h18" transform="rotate(0)"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 4.5v15M15 4.5v15"/>
            </svg>
        </div>
        <h3 class="text-base font-semibold text-ink mb-1">No segments yet</h3>
        <p class="text-sm text-muted max-w-sm mx-auto mb-4">Create a dynamic segment to automatically target customers by product type, location, payment status, or lifecycle stage.</p>
        <button wire:click="$set('showForm', true)" class="inline-flex items-center gap-2 px-4 py-2 bg-brand text-white text-sm font-medium rounded-xl hover:bg-brand-strong transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            Create Segment
        </button>
    </div>
    @elseif(!$segments->isEmpty())
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($segments as $segment)
        <div class="bg-surface-2 rounded-2xl border border-border p-5 flex flex-col hover:border-brand/30 transition-colors group">
            <div class="flex items-start justify-between mb-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-xl bg-brand/10 flex items-center justify-center text-brand shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h18M3 9h18M3 13.5h18M3 18h18"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 4.5v15M15 4.5v15"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold text-ink truncate">{{ $segment->name }}</h3>
                        <span class="text-xs text-muted">{{ number_format($segment->customers_count) }} {{ $segment->customers_count === 1 ? 'customer' : 'customers' }}</span>
                    </div>
                </div>
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="p-1.5 rounded-lg text-muted hover:text-ink hover:bg-surface transition-colors opacity-0 group-hover:opacity-100">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z"/></svg>
                    </button>
                    <div x-show="open" @click.away="open = false" x-cloak
                         class="absolute right-0 top-8 w-44 bg-surface-2 border border-border rounded-xl shadow-lg z-10 py-1">
                        <a href="{{ route('campaigns.create') }}?audience=segment&segment_id={{ $segment->id }}" wire:navigate @click="open = false"
                           class="block w-full text-left px-3 py-2 text-sm text-ink hover:bg-surface transition-colors">Create Campaign</a>
                        <button wire:click="editSegment({{ $segment->id }})" @click="open = false"
                                class="w-full text-left px-3 py-2 text-sm text-ink hover:bg-surface transition-colors">Edit</button>
                        <button wire:click="confirmDelete({{ $segment->id }})" @click="open = false"
                                class="w-full text-left px-3 py-2 text-sm text-danger hover:bg-danger/5 transition-colors">Delete</button>
                    </div>
                </div>
            </div>
            @if($segment->description)
            <p class="text-xs text-muted mb-3 line-clamp-2">{{ $segment->description }}</p>
            @endif

            {{-- Rule summary --}}
            <div class="flex flex-wrap gap-1.5 mb-3">
                @foreach(($segment->rules['conditions'] ?? []) as $condition)
                    @continue(!isset($fields[$condition['field']]))
                    <span class="inline-flex items-center px-2 py-1 text-[11px] font-medium bg-surface text-ink/70 rounded-lg border border-border">
                        {{ $fields[$condition['field']]['label'] }}
                        {{ $operatorsByType[$fields[$condition['field']]['type']][$condition['operator']] ?? $condition['operator'] }}
                        @if(!in_array($condition['operator'], $valuelessOperators, true))
                            @if(is_array($condition['value']))
                                {{ implode(', ', array_map(fn($v) => $fields[$condition['field']]['options'][$v] ?? $v, $condition['value'])) }}
                            @else
                                {{ $fields[$condition['field']]['options'][$condition['value']] ?? $condition['value'] }}
                            @endif
                        @endif
                    </span>
                @endforeach
                @if(count($segment->rules['conditions'] ?? []) > 1)
                    <span class="inline-flex items-center px-2 py-1 text-[11px] font-semibold text-brand bg-brand/10 rounded-lg uppercase">
                        {{ ($segment->rules['match'] ?? 'all') === 'any' ? 'any' : 'all' }}
                    </span>
                @endif
            </div>

            <div class="mt-auto pt-3 border-t border-border/60 flex items-center gap-2">
                <button wire:click="editSegment({{ $segment->id }})" class="flex-1 text-center py-1.5 text-xs font-medium text-brand bg-brand/5 rounded-lg hover:bg-brand/10 transition-colors">
                    Edit Rules
                </button>
                <a href="{{ route('campaigns.create') }}?audience=segment&segment_id={{ $segment->id }}" wire:navigate
                   class="flex-1 text-center py-1.5 text-xs font-medium text-ink bg-surface rounded-lg hover:bg-surface-2 border border-border transition-colors">
                    Campaign
                </a>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Delete confirmation --}}
    @if($confirmDeleteId)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.4);">
        <div class="bg-surface-2 rounded-2xl border border-border p-6 max-w-sm w-full shadow-xl">
            <h3 class="text-lg font-semibold text-ink mb-2">Delete Segment?</h3>
            <p class="text-sm text-muted mb-5">This removes the segment. Customers themselves are not affected.</p>
            <div class="flex items-center justify-end gap-3">
                <button wire:click="$set('confirmDeleteId', null)" class="px-4 py-2 text-sm text-muted hover:text-ink">Cancel</button>
                <button wire:click="deleteSegment" class="px-4 py-2 bg-danger text-white text-sm font-semibold rounded-xl hover:bg-red-600 transition-colors">Delete</button>
            </div>
        </div>
    </div>
    @endif
</div>
