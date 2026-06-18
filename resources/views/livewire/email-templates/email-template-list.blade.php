<div class="space-y-6 text-ink">
    {{-- Flash --}}
    @if (session('success'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
         class="p-3 bg-success/10 border border-success/20 text-success rounded-xl text-sm flex items-center justify-between">
        <span>{{ session('success') }}</span>
        <button type="button" @click="show = false" class="text-success hover:opacity-70">&times;</button>
    </div>
    @endif

    {{-- Page header --}}
    <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-ink">{{ __('Email Templates') }}</h1>
            <p class="text-sm text-muted mt-1">{{ __('Browse professional templates to kickstart your campaigns.') }}</p>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            {{-- Search --}}
            <div class="relative">
                <svg class="w-4 h-4 text-muted absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search templates...') }}"
                       class="pl-9 pr-4 py-2 text-sm bg-surface-2 border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent placeholder-muted/60 w-56">
            </div>

            {{-- Create template --}}
            <a href="{{ route('email-templates.create') }}" wire:navigate
               class="btn-primary flex items-center gap-2 text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('New Email Template') }}
            </a>
        </div>
    </div>

    {{-- Category filter pills --}}
    <div class="flex gap-2 overflow-x-auto pb-1 -mx-1 px-1">
        @foreach($categories as $key => $label)
            <button
                wire:click="$set('category', '{{ $key }}')"
                class="flex-shrink-0 inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all duration-150
                    {{ $category === $key
                        ? 'bg-brand text-white shadow-sm'
                        : 'bg-surface-2 text-muted border border-border hover:border-brand/30 hover:text-ink' }}"
                aria-pressed="{{ $category === $key ? 'true' : 'false' }}"
            >
                {{ $label }}
                <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full text-[10px] font-bold
                    {{ $category === $key ? 'bg-white/20 text-white' : 'bg-surface text-muted' }}">
                    {{ $key === 'all' ? $totalCount : ($categoryCounts[$key] ?? 0) }}
                </span>
            </button>
        @endforeach
    </div>

    {{-- Template grid --}}
    @if($templates->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($templates as $template)
                <div
                    wire:key="tpl-{{ $template->id }}"
                    class="group bg-surface-2 rounded-2xl border border-border overflow-hidden hover:shadow-lg hover:-translate-y-1 transition-all duration-200"
                >
                    {{-- Preview thumbnail --}}
                    <div class="relative h-52 overflow-hidden bg-gray-50 dark:bg-gray-800 border-b border-border">
                        <div class="absolute inset-0 overflow-hidden pointer-events-none">
                            @if(!empty($template->blocks))
                                <div class="origin-top-left scale-[0.45] w-[222%]">
                                    {!! \App\Support\EmailBlockRenderer::renderBlocksPreview($template->blocks) !!}
                                </div>
                            @elseif($template->body_html)
                                <iframe srcdoc="{{ $template->body_html }}" class="origin-top-left scale-[0.45] w-[222%] h-[222%] border-0" sandbox="allow-same-origin" tabindex="-1" title="{{ $template->name }}"></iframe>
                            @endif
                        </div>

                        {{-- Hover overlay --}}
                        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition-all duration-200 flex items-center justify-center gap-2 opacity-0 group-hover:opacity-100">
                            <button
                                wire:click="preview({{ $template->id }})"
                                class="px-3 py-2 text-xs font-semibold text-white bg-white/20 backdrop-blur-sm rounded-lg border border-white/30 hover:bg-white/30 transition-colors"
                            >
                                {{ __('Preview') }}
                            </button>
                            <a
                                href="{{ route('email-templates.edit', $template) }}" wire:navigate
                                class="px-3 py-2 text-xs font-semibold text-white bg-white/20 backdrop-blur-sm rounded-lg border border-white/30 hover:bg-white/30 transition-colors"
                            >
                                {{ __('Edit') }}
                            </a>
                            <button
                                wire:click="useTemplate({{ $template->id }})"
                                class="px-3 py-2 text-xs font-semibold text-white bg-brand rounded-lg hover:bg-brand-strong transition-colors shadow-sm"
                            >
                                {{ __('Use') }}
                            </button>
                        </div>
                    </div>

                    {{-- Card info --}}
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h3 class="text-sm font-semibold text-ink truncate">{{ $template->name }}</h3>
                                <span class="inline-block mt-1.5 px-2 py-0.5 text-[10px] font-semibold rounded-full bg-brand/10 text-brand dark:bg-indigo-900/30 dark:text-indigo-400 capitalize">
                                    {{ str_replace('_', ' ', $template->category) }}
                                </span>
                            </div>
                            @if($template->usage_count > 0)
                                <span class="text-[10px] text-muted font-medium flex-shrink-0">
                                    {{ $template->usage_count }} {{ Str::plural('use', $template->usage_count) }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        {{-- Empty state --}}
        <div class="flex flex-col items-center justify-center py-16 px-4 bg-surface-2 rounded-2xl border border-border">
            <div class="w-14 h-14 bg-brand/10 dark:bg-indigo-900/20 rounded-full flex items-center justify-center mb-4">
                <svg class="w-7 h-7 text-brand dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            </div>
            @if($search || $category !== 'all')
                <p class="text-sm font-semibold text-ink">{{ __('No templates found') }}</p>
                <p class="text-xs text-muted mt-1">{{ __('Try adjusting your search or filter criteria.') }}</p>
            @else
                <p class="text-sm font-semibold text-ink">{{ __('No email templates yet') }}</p>
                <p class="text-xs text-muted mt-1 mb-4">{{ __('Create a reusable email template to speed up campaign creation.') }}</p>
                <a href="{{ route('email-templates.create') }}" wire:navigate class="btn-primary text-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    {{ __('New Email Template') }}
                </a>
            @endif
        </div>
    @endif

    {{-- Preview Modal --}}
    @if($previewTemplate)
        <div
            x-data="{ open: true }"
            x-show="open"
            x-on:keydown.escape.window="open = false; $wire.closePreview()"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-label="Template preview"
        >
            {{-- Backdrop --}}
            <div
                class="absolute inset-0 bg-black/60 backdrop-blur-sm"
                x-on:click="open = false; $wire.closePreview()"
            ></div>

            {{-- Modal panel --}}
            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                class="relative bg-surface-2 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[85vh] flex flex-col overflow-hidden border border-border"
            >
                {{-- Modal header --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-border bg-surface/50">
                    <div>
                        <h3 class="text-base font-bold text-ink">{{ $previewTemplate->name }}</h3>
                        <span class="text-xs text-muted capitalize">{{ str_replace('_', ' ', $previewTemplate->category) }} template</span>
                    </div>
                    <button
                        x-on:click="open = false; $wire.closePreview()"
                        class="p-2 rounded-lg text-muted hover:text-ink hover:bg-surface transition-colors"
                        aria-label="Close preview"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Modal body: rendered template (full-fidelity, sandboxed) --}}
                <div class="flex-1 overflow-y-auto p-6 bg-gray-50 dark:bg-gray-800">
                    <div class="max-w-[600px] mx-auto bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden" style="height: 60vh;">
                        <iframe srcdoc="{{ $previewHtml }}" class="w-full h-full border-0" sandbox="allow-same-origin" title="Template preview"></iframe>
                    </div>
                </div>

                {{-- Modal footer --}}
                <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-border bg-surface/50">
                    <a href="{{ route('email-templates.edit', $previewTemplate) }}" wire:navigate class="px-4 py-2 text-sm font-medium text-ink/80 bg-surface-2 border border-border rounded-xl hover:bg-surface transition-colors">
                        {{ __('Edit') }}
                    </a>
                    <button
                        x-on:click="open = false; $wire.closePreview()"
                        class="px-4 py-2 text-sm font-medium text-ink/80 bg-surface-2 border border-border rounded-xl hover:bg-surface transition-colors"
                    >
                        {{ __('Close') }}
                    </button>
                    <button
                        wire:click="useTemplate({{ $previewTemplate->id }})"
                        class="btn-primary text-sm flex items-center gap-2"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        {{ __('Use This Template') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
