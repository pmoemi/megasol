<?php

namespace App\Livewire\EmailTemplates;

use App\Models\EmailTemplate;
use App\Support\EmailBlockRenderer;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Email Templates')]
class EmailTemplateList extends Component
{
    #[Url]
    public string $category = 'all';

    public string $search = '';

    /** The template ID currently being previewed in the modal. */
    public ?int $previewId = null;

    /**
     * Category definitions for the filter pills.
     *
     * @return array<string, string>
     */
    public static function categories(): array
    {
        return [
            'all' => 'All Templates',
            'onboarding' => 'Welcome / Onboarding',
            'newsletter' => 'Newsletter',
            'marketing' => 'Promotional',
            'engagement' => 'Follow-up / Re-engagement',
            'transactional' => 'Transactional',
            'events' => 'Event / Webinar',
            'reporting' => 'Reports',
            'product' => 'Product Updates',
        ];
    }

    /**
     * Open the preview modal for a given template.
     */
    public function preview(int $templateId): void
    {
        $this->previewId = $templateId;
    }

    /**
     * Close the preview modal.
     */
    public function closePreview(): void
    {
        $this->previewId = null;
    }

    /**
     * "Use This Template" — redirect to campaign creation with the template
     * pre-loaded (email channel).
     */
    public function useTemplate(int $templateId): void
    {
        $template = EmailTemplate::findOrFail($templateId);
        $template->incrementUsage();

        $this->redirect(route('campaigns.create', ['template' => $templateId]), navigate: true);
    }

    public function render()
    {
        $query = EmailTemplate::query()->where('is_active', true);

        if ($this->category !== 'all') {
            $query->where('category', $this->category);
        }

        if ($this->search) {
            $query->where('name', 'like', '%'.$this->search.'%');
        }

        $templates = $query->orderByDesc('usage_count')->orderByDesc('updated_at')->get();

        // Load preview template if modal is open
        $previewTemplate = null;
        $previewHtml = '';
        if ($this->previewId) {
            $previewTemplate = EmailTemplate::where('is_active', true)->find($this->previewId);
            if ($previewTemplate) {
                // Prefer the compiled HTML (full fidelity); fall back to the
                // block renderer when a template has no stored body_html.
                $previewHtml = $previewTemplate->body_html
                    ?: EmailBlockRenderer::renderBlocksPreview($previewTemplate->blocks ?? []);
            }
        }

        // Category counts for pills
        $categoryCounts = EmailTemplate::query()
            ->where('is_active', true)
            ->selectRaw('category, COUNT(*) as cnt')
            ->groupBy('category')
            ->pluck('cnt', 'category');

        $totalCount = $categoryCounts->sum();

        return view('livewire.email-templates.email-template-list', [
            'templates' => $templates,
            'categories' => self::categories(),
            'categoryCounts' => $categoryCounts,
            'totalCount' => $totalCount,
            'previewTemplate' => $previewTemplate,
            'previewHtml' => $previewHtml,
        ]);
    }
}
