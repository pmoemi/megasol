<?php

namespace App\Livewire\EmailTemplates;

use App\Models\EmailTemplate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class EmailTemplateEditor extends Component
{
    public ?EmailTemplate $template = null;

    public string $name = '';

    public string $subject = '';

    public string $body_html = '';

    public string $category = 'general';

    public bool $is_active = true;

    public function mount(?EmailTemplate $template = null): void
    {
        $this->template = $template;

        if ($template) {
            $this->fill([
                'name' => $template->name,
                'subject' => $template->subject,
                'body_html' => $template->body_html,
                'category' => $template->category,
                'is_active' => $template->is_active,
            ]);
        }
    }

    public function getTitleProperty(): string
    {
        return $this->template ? 'Edit Email Template' : 'Create Email Template';
    }

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'body_html' => 'required|string',
            'category' => 'required|string|max:50',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'subject' => $this->subject,
            'body_html' => $this->body_html,
            'category' => $this->category,
            'is_active' => $this->is_active,
        ];

        if ($this->template) {
            $this->template->update($data);
        } else {
            EmailTemplate::create($data);
        }

        session()->flash('success', 'Email template saved.');
        $this->redirect(route('email-templates.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.email-templates.email-template-editor')->title($this->title);
    }
}
