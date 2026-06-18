<?php

namespace App\Livewire\Templates;

use App\Models\MessageTemplate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Message Templates')]
class TemplateList extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    /** The template currently shown in the preview modal. */
    public ?int $previewId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function toggleActive(int $id): void
    {
        $template = MessageTemplate::findOrFail($id);
        $template->update(['is_active' => ! $template->is_active]);
    }

    public function preview(int $id): void
    {
        $this->previewId = $id;
    }

    public function closePreview(): void
    {
        $this->previewId = null;
    }

    /**
     * "Use in campaign" — open the campaign editor with this SMS template
     * pre-loaded (sms channel).
     */
    public function useInCampaign(int $id): void
    {
        $template = MessageTemplate::findOrFail($id);

        $this->redirect(route('campaigns.create', ['message_template' => $template->id]), navigate: true);
    }

    public function render()
    {
        $templates = MessageTemplate::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('name')
            ->paginate(15);

        $previewTemplate = $this->previewId
            ? MessageTemplate::find($this->previewId)
            : null;

        return view('livewire.templates.template-list', compact('templates', 'previewTemplate'));
    }
}
