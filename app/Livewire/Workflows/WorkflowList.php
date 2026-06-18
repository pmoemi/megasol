<?php

namespace App\Livewire\Workflows;

use App\Models\Workflow;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Workflows')]
class WorkflowList extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = 'all';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function toggleStatus(int $id): void
    {
        $workflow = Workflow::findOrFail($id);
        $workflow->update(['is_active' => ! $workflow->is_active]);
        $this->dispatch('toast', type: 'success', message: 'Workflow ' . ($workflow->is_active ? 'activated' : 'paused') . '.');
    }

    public function duplicateWorkflow(int $id): void
    {
        $original  = Workflow::findOrFail($id);
        $duplicate = $original->replicate();
        $duplicate->name      = $original->name . ' (Copy)';
        $duplicate->is_active = false;
        $duplicate->last_run_at = null;
        $duplicate->save();
        $this->dispatch('toast', type: 'success', message: 'Workflow duplicated.');
    }

    public function deleteWorkflow(int $id): void
    {
        Workflow::findOrFail($id)->delete();
        $this->dispatch('toast', type: 'success', message: 'Workflow deleted.');
    }

    public function render()
    {
        $workflows = Workflow::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->when($this->statusFilter === 'active',   fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->with('creator')
            ->orderByDesc('updated_at')
            ->paginate(15);

        return view('livewire.workflows.workflow-list', compact('workflows'));
    }
}
