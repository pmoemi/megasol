<div class="space-y-6">
    {{-- Flash --}}
    @if (session('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
         class="p-3 bg-success/10 border border-success/20 text-success rounded-xl text-sm flex items-center justify-between">
        <span>{{ session('success') }}</span>
        <button type="button" @click="show = false" class="text-success hover:opacity-70">&times;</button>
    </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-ink">SMS Templates</h1>
            <p class="text-sm text-muted mt-0.5">Manage reusable SMS message templates for your campaigns</p>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            {{-- Search --}}
            <div class="relative">
                <svg class="w-4 h-4 text-muted absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search templates..."
                       class="pl-9 pr-4 py-2 text-sm bg-surface-2 border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent placeholder-muted/60 w-56">
            </div>

            {{-- Create template --}}
            <a href="{{ route('templates.create') }}" wire:navigate
               class="btn-primary flex items-center gap-2 text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Template
            </a>
        </div>
    </div>

    {{-- Template grid --}}
    @if ($templates->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ($templates as $template)
                <div wire:key="template-{{ $template->id }}"
                     class="group bg-surface-2 rounded-2xl border border-border overflow-hidden hover:shadow-lg hover:-translate-y-1 transition-all duration-200 flex flex-col">
                    {{-- Body snippet preview --}}
                    <div class="relative h-40 p-5 bg-gradient-to-br from-surface to-surface-2 border-b border-border overflow-hidden">
                        @if (in_array($template->channel, ['email', 'both']) && $template->body_html)
                            <div class="absolute inset-0 overflow-hidden pointer-events-none opacity-90">
                                <div class="origin-top-left scale-[0.4] w-[250%]">{!! $template->body_html !!}</div>
                            </div>
                        @else
                            <div class="max-w-[240px]">
                                <div class="bg-brand text-white rounded-2xl rounded-bl-sm px-3 py-2 text-xs leading-relaxed shadow whitespace-pre-wrap line-clamp-5">{{ $template->body }}</div>
                            </div>
                        @endif

                        {{-- Hover overlay --}}
                        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition-all duration-200 flex items-center justify-center gap-2 opacity-0 group-hover:opacity-100">
                            <button wire:click="preview({{ $template->id }})" class="px-3 py-2 text-xs font-semibold text-white bg-white/20 backdrop-blur-sm rounded-lg border border-white/30 hover:bg-white/30 transition-colors">Preview</button>
                            <a href="{{ route('templates.edit', $template) }}" wire:navigate class="px-3 py-2 text-xs font-semibold text-white bg-white/20 backdrop-blur-sm rounded-lg border border-white/30 hover:bg-white/30 transition-colors">Edit</a>
                            <button wire:click="useInCampaign({{ $template->id }})" class="px-3 py-2 text-xs font-semibold text-white bg-brand rounded-lg hover:bg-brand-strong transition-colors shadow-sm">Use</button>
                        </div>
                    </div>

                    {{-- Card info --}}
                    <div class="p-4 flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-ink truncate">{{ $template->name }}</h3>
                            <div class="flex items-center gap-1.5 mt-1.5">
                                <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full bg-brand/10 text-brand capitalize">{{ str_replace('_', ' ', $template->type) }}</span>
                                <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full bg-surface text-muted uppercase">{{ $template->channel }}</span>
                            </div>
                        </div>
                        <button type="button" wire:click="toggleActive({{ $template->id }})"
                                @class(['badge cursor-pointer shrink-0', 'badge-success' => $template->is_active, 'badge-ghost' => ! $template->is_active])>
                            {{ $template->is_active ? 'Active' : 'Inactive' }}
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($templates->hasPages())
            <div>{{ $templates->links() }}</div>
        @endif
    @else
        {{-- Empty state --}}
        <div class="flex flex-col items-center justify-center py-16 px-4 bg-surface-2 rounded-2xl border border-border text-center">
            <div class="w-16 h-16 bg-surface rounded-2xl flex items-center justify-center mb-4 border border-border">
                <svg class="w-8 h-8 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
            </div>
            @if($search)
                <h3 class="text-base font-semibold text-ink mb-1">No templates found</h3>
                <p class="text-sm text-muted mb-4">No templates match "{{ $search }}".</p>
                <button type="button" wire:click="$set('search', '')" class="px-4 py-2 text-sm text-brand border border-brand/30 rounded-xl hover:bg-brand/5 transition-colors">Clear search</button>
            @else
                <h3 class="text-base font-semibold text-ink mb-1">No templates yet</h3>
                <p class="text-sm text-muted mb-4">Create a reusable SMS template to speed up campaign creation.</p>
                <a href="{{ route('templates.create') }}" wire:navigate class="btn-primary text-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New Template
                </a>
            @endif
        </div>
    @endif

    {{-- Preview modal --}}
    @if ($previewTemplate)
        <div x-data="{ open: true }" x-show="open"
             x-on:keydown.escape.window="open = false; $wire.closePreview()"
             class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" x-on:click="open = false; $wire.closePreview()"></div>

            <div class="relative bg-surface-2 rounded-2xl shadow-2xl w-full max-w-md flex flex-col overflow-hidden border border-border">
                <div class="flex items-center justify-between px-6 py-4 border-b border-border bg-surface/50">
                    <div>
                        <h3 class="text-base font-bold text-ink">{{ $previewTemplate->name }}</h3>
                        <span class="text-xs text-muted capitalize">{{ str_replace('_', ' ', $previewTemplate->type) }} · {{ $previewTemplate->channel }}</span>
                    </div>
                    <button x-on:click="open = false; $wire.closePreview()" class="p-2 rounded-lg text-muted hover:text-ink hover:bg-surface transition-colors" aria-label="Close">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="p-6 bg-gray-50 dark:bg-gray-800">
                    @if (in_array($previewTemplate->channel, ['email', 'both']) && $previewTemplate->body_html)
                        {{-- Email HTML preview --}}
                        @if ($previewTemplate->subject)
                            <div class="mb-3 px-3 py-2 bg-surface-2 border border-border rounded-lg text-sm">
                                <span class="text-muted">Subject:</span> <span class="font-medium text-ink">{{ $previewTemplate->subject }}</span>
                            </div>
                        @endif
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden" style="height: 420px;">
                            <iframe srcdoc="{{ $previewTemplate->body_html }}" class="w-full h-full border-0" sandbox="allow-same-origin" title="Email preview"></iframe>
                        </div>
                    @else
                        {{-- SMS bubble preview --}}
                        <div class="max-w-[300px] mx-auto">
                            <div class="bg-brand text-white rounded-2xl rounded-bl-sm px-4 py-3 text-sm leading-relaxed shadow whitespace-pre-wrap">{{ $previewTemplate->body }}</div>
                            <p class="text-[11px] text-muted mt-2 text-center">{{ strlen($previewTemplate->body) }} characters · {{ (int) ceil(max(1, strlen($previewTemplate->body)) / 160) }} SMS segment(s)</p>
                        </div>
                    @endif
                    <p class="text-[11px] text-muted mt-4">Merge tags like <span class="font-mono">{first_name}</span>, <span class="font-mono">{balance}</span> are replaced per recipient when sent.</p>
                </div>

                <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-border bg-surface/50">
                    <a href="{{ route('templates.edit', $previewTemplate) }}" wire:navigate class="px-4 py-2 text-sm font-medium text-ink/80 bg-surface-2 border border-border rounded-xl hover:bg-surface transition-colors">Edit</a>
                    <button x-on:click="open = false; $wire.closePreview()" class="px-4 py-2 text-sm font-medium text-ink/80 bg-surface-2 border border-border rounded-xl hover:bg-surface transition-colors">Close</button>
                    <button wire:click="useInCampaign({{ $previewTemplate->id }})" class="btn-primary text-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Use in campaign
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
