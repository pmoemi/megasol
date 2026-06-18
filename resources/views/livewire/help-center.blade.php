<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-ink">Help Center</h1>
        <p class="text-sm text-muted mt-1">Find answers and learn how to use {{ config('app.name') }}.</p>
    </div>

    <div class="flex flex-col lg:flex-row gap-6">
        {{-- Categories sidebar --}}
        <aside class="lg:w-56 flex-shrink-0">
            <div class="bg-surface-2 border border-border rounded-2xl p-4 lg:sticky lg:top-24 space-y-1">
                <h3 class="text-xs font-semibold text-muted uppercase tracking-wider px-3 mb-2">Categories</h3>
                <button wire:click="selectCategory('')"
                        @class([
                            'w-full flex items-center justify-between px-3 py-2 text-sm rounded-xl transition-colors',
                            'bg-brand/10 text-brand font-medium' => $activeCategory === '',
                            'text-ink/70 hover:bg-surface hover:text-ink' => $activeCategory !== '',
                        ])>
                    <span>All Articles</span>
                </button>
                @foreach($this->categories as $key => $cat)
                    <button wire:click="selectCategory('{{ $key }}')"
                            @class([
                                'w-full flex items-center justify-between px-3 py-2 text-sm rounded-xl transition-colors',
                                'bg-brand/10 text-brand font-medium' => $activeCategory === $key,
                                'text-ink/70 hover:bg-surface hover:text-ink' => $activeCategory !== $key,
                            ])>
                        <span class="flex items-center gap-2 min-w-0">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $cat['icon'] }}"/></svg>
                            <span class="truncate">{{ $cat['label'] }}</span>
                        </span>
                        <span class="text-xs text-muted bg-surface px-1.5 py-0.5 rounded-full shrink-0">{{ $categoryCounts[$key] ?? 0 }}</span>
                    </button>
                @endforeach
            </div>
        </aside>

        {{-- Main content --}}
        <div class="flex-1 min-w-0">
            {{-- Search --}}
            <div class="mb-6 relative">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search"
                       class="w-full pl-10 pr-4 py-3 text-sm bg-surface-2 border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-brand/30 focus:border-transparent"
                       placeholder="Search help articles...">
            </div>

            @if($this->selectedArticle)
                {{-- Article detail --}}
                <div class="bg-surface-2 border border-border rounded-2xl p-6 sm:p-8">
                    <button wire:click="back" class="flex items-center gap-1.5 text-sm text-muted hover:text-ink transition-colors mb-4">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
                        Back to articles
                    </button>

                    <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-brand/10 text-brand mb-3">
                        {{ $this->categories[$this->selectedArticle->category]['label'] ?? $this->selectedArticle->category }}
                    </span>

                    <h1 class="text-2xl font-bold text-ink mb-4">{{ $this->selectedArticle->title }}</h1>

                    <div class="prose prose-sm max-w-none text-ink/80 leading-relaxed">
                        {!! nl2br(e($this->selectedArticle->content)) !!}
                    </div>
                </div>
            @else
                @if($this->articles->isEmpty())
                    <div class="flex flex-col items-center justify-center py-16 px-4 bg-surface-2 rounded-2xl border border-border text-center">
                        <div class="w-14 h-14 bg-brand/10 rounded-full flex items-center justify-center mb-4">
                            <svg class="w-7 h-7 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        </div>
                        <p class="text-sm font-semibold text-ink">No articles found</p>
                        <p class="text-xs text-muted mt-1">Try a different search term or browse by category.</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($this->articles as $article)
                            <button wire:click="selectArticle({{ $article->id }})"
                                    class="w-full text-left bg-surface-2 border border-border rounded-2xl p-5 hover:shadow-md hover:border-brand/30 transition-all group">
                                <div class="flex items-start gap-4">
                                    <div class="flex-1 min-w-0">
                                        <span class="inline-flex px-2 py-0.5 text-[10px] font-medium rounded-full bg-brand/10 text-brand mb-1.5">
                                            {{ $this->categories[$article->category]['label'] ?? $article->category }}
                                        </span>
                                        <h3 class="text-sm font-semibold text-ink group-hover:text-brand transition-colors">{{ $article->title }}</h3>
                                        @if($article->excerpt)
                                            <p class="text-xs text-muted mt-1 line-clamp-2">{{ $article->excerpt }}</p>
                                        @endif
                                    </div>
                                    <svg class="w-4 h-4 text-muted group-hover:text-brand transition-colors shrink-0 mt-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
                                </div>
                            </button>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
