<?php

namespace App\Livewire\Templates;

use App\Models\MessageTemplate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class TemplateEditor extends Component
{
    public ?MessageTemplate $template = null;

    public string $name = '';

    public string $type = 'custom';

    public string $body = '';

    public bool $is_active = true;

    public function mount(?MessageTemplate $template = null): void
    {
        $this->template = $template;

        if ($template) {
            $this->fill([
                'name' => $template->name,
                'type' => $template->type,
                'body' => $template->body,
                'is_active' => $template->is_active,
            ]);
        }
    }

    public function getTitleProperty(): string
    {
        return $this->template ? 'Edit Template' : 'Create Template';
    }

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'type' => 'required|in:payment_reminder,overdue,welcome,seasonal,offer,tip,campaign,custom',
            'body' => 'required|string|max:1000',
            'is_active' => 'boolean',
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->template) {
            $this->template->update($data);
            session()->flash('success', 'Template updated successfully.');
        } else {
            MessageTemplate::create($data);
            session()->flash('success', 'Template created successfully.');
        }

        $this->redirect(route('templates.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.templates.template-editor')
            ->layoutData(['title' => $this->title]);
    }
}
