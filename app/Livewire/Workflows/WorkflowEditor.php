<?php

namespace App\Livewire\Workflows;

use App\Models\Workflow;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class WorkflowEditor extends Component
{
    public ?Workflow $workflow = null;

    public string $name = '';

    public string $description = '';

    public string $trigger_type = 'manual';

    public string $schedule_cron = '';

    public bool $is_active = true;

    public string $steps_json = '[]';

    public function mount(?Workflow $workflow = null): void
    {
        $this->workflow = $workflow;

        if ($workflow) {
            $this->fill([
                'name' => $workflow->name,
                'description' => $workflow->description ?? '',
                'trigger_type' => $workflow->trigger_type,
                'schedule_cron' => $workflow->schedule_cron ?? '',
                'is_active' => $workflow->is_active,
                'steps_json' => json_encode($workflow->definition['steps'] ?? [], JSON_PRETTY_PRINT),
            ]);
        } else {
            $this->steps_json = json_encode([
                ['type' => 'send_sms', 'body' => 'Hi {first_name}, payment reminder from MegaSol.'],
            ], JSON_PRETTY_PRINT);
        }
    }

    public function getTitleProperty(): string
    {
        return $this->workflow ? 'Edit Workflow' : 'Create Workflow';
    }

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'trigger_type' => 'required|in:manual,customer_created,payment_due,payment_overdue,scheduled',
            'steps_json' => 'required|json',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $steps = json_decode($this->steps_json, true);

        if (! is_array($steps)) {
            $this->addError('steps_json', 'Steps must be a valid JSON array.');

            return;
        }

        $data = [
            'name' => $this->name,
            'description' => $this->description,
            'trigger_type' => $this->trigger_type,
            'schedule_cron' => $this->schedule_cron ?: null,
            'definition' => ['steps' => $steps],
            'is_active' => $this->is_active,
            'created_by' => auth()->id(),
        ];

        if ($this->workflow) {
            $this->workflow->update($data);
        } else {
            Workflow::create($data);
        }

        session()->flash('success', 'Workflow saved.');
        $this->redirect(route('workflows.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.workflows.workflow-editor')->title($this->title);
    }
}
