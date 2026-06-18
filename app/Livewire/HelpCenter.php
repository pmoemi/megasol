<?php

namespace App\Livewire;

use App\Models\HelpArticle;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app', ['title' => 'Help Center'])]
class HelpCenter extends Component
{
    #[Url]
    public string $search = '';

    #[Url]
    public string $activeCategory = '';

    public ?int $selectedArticleId = null;

    public function updatedSearch(): void
    {
        $this->selectedArticleId = null;
    }

    public function selectCategory(string $category): void
    {
        $this->activeCategory = $this->activeCategory === $category ? '' : $category;
        $this->selectedArticleId = null;
    }

    public function selectArticle(int $id): void
    {
        $this->selectedArticleId = $id;
    }

    public function back(): void
    {
        $this->selectedArticleId = null;
    }

    /**
     * Help categories keyed by slug, each with a label and an inline SVG path.
     *
     * @return array<string, array{label:string, icon:string}>
     */
    public function getCategoriesProperty(): array
    {
        return [
            'getting-started' => ['label' => 'Getting Started', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z'],
            'customers' => ['label' => 'Customers', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
            'campaigns' => ['label' => 'Campaigns', 'icon' => 'M12 19l9 2-9-18-9 18 9-2zm0 0v-8'],
            'sms' => ['label' => 'SMS', 'icon' => 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z'],
            'email' => ['label' => 'Email', 'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
            'templates' => ['label' => 'Templates', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
            'workflows' => ['label' => 'Workflows', 'icon' => 'M16 18l6-6-6-6M8 6l-6 6 6 6'],
            'billing' => ['label' => 'Billing & PayGro', 'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
            'troubleshooting' => ['label' => 'Troubleshooting', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'],
        ];
    }

    public function getArticlesProperty()
    {
        $query = HelpArticle::published()->orderBy('sort_order');

        if ($this->search) {
            $query->search(mb_substr($this->search, 0, 200));
        }

        if ($this->activeCategory) {
            $query->category($this->activeCategory);
        }

        return $query->get();
    }

    public function getSelectedArticleProperty(): ?HelpArticle
    {
        if (! $this->selectedArticleId) {
            return null;
        }

        return HelpArticle::published()->find($this->selectedArticleId);
    }

    public function render()
    {
        return view('livewire.help-center', [
            'categoryCounts' => HelpArticle::published()
                ->selectRaw('category, COUNT(*) as cnt')
                ->groupBy('category')
                ->pluck('cnt', 'category'),
        ]);
    }
}
