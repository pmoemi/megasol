<?php

namespace App\Livewire\Collections;

use App\Models\AgentAssignment;
use App\Models\Customer;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Collections')]
class CollectionsBoard extends Component
{
    use WithPagination;

    #[Url]
    public string $tab = 'unassigned';

    #[Url]
    public string $agentFilter = '';

    public ?int $assignCustomerId = null;

    public ?int $assignAgentId = null;

    public string $assignReason = '';

    public array $tabs = [
        'unassigned' => 'Needs Assignment',
        'open' => 'Open Cases',
        'resolved' => 'Resolved',
    ];

    public function updatingTab(): void
    {
        $this->resetPage();
    }

    public function updatingAgentFilter(): void
    {
        $this->resetPage();
    }

    public function openAssign(int $customerId): void
    {
        $this->assignCustomerId = $customerId;
        $this->assignAgentId = null;
        $this->assignReason = '';
    }

    public function assign(): void
    {
        $this->validate([
            'assignCustomerId' => 'required|integer|exists:customers,id',
            'assignAgentId' => 'required|integer|exists:users,id',
            'assignReason' => 'nullable|string|max:255',
        ]);

        $customer = Customer::findOrFail($this->assignCustomerId);

        AgentAssignment::create([
            'customer_id' => $customer->id,
            'agent_id' => $this->assignAgentId,
            'assigned_by' => auth()->id(),
            'status' => 'assigned',
            'reason' => $this->assignReason ?: null,
            'amount_at_assignment' => $customer->outstanding_balance,
            'assigned_at' => now(),
        ]);

        $customer->update(['assigned_agent_id' => $this->assignAgentId]);

        $this->reset(['assignCustomerId', 'assignAgentId', 'assignReason']);
        $this->dispatch('toast', type: 'success', message: 'Customer assigned to field agent.');
    }

    public function markResolved(int $assignmentId): void
    {
        $assignment = AgentAssignment::findOrFail($assignmentId);
        $assignment->update(['status' => 'resolved', 'resolved_at' => now()]);

        if ($assignment->customer && $assignment->customer->assigned_agent_id === $assignment->agent_id) {
            $assignment->customer->update(['assigned_agent_id' => null]);
        }

        $this->dispatch('toast', type: 'success', message: 'Case resolved.');
    }

    public function render()
    {
        $agents = User::query()->orderBy('name')->get(['id', 'name', 'email']);

        if ($this->tab === 'unassigned') {
            // Customers in trouble (overdue / defaulting) with no open assignment.
            $items = Customer::query()
                ->whereNull('assigned_agent_id')
                ->where(function ($q) {
                    $q->where('payment_status', 'overdue')->orWhere('account_status', 'defaulting');
                })
                ->orderByDesc('outstanding_balance')
                ->paginate(15);

            return view('livewire.collections.collections-board', [
                'mode' => 'customers',
                'items' => $items,
                'agents' => $agents,
            ]);
        }

        $statuses = $this->tab === 'resolved'
            ? ['resolved', 'written_off']
            : AgentAssignment::OPEN_STATUSES;

        $items = AgentAssignment::query()
            ->with(['customer', 'agent'])
            ->whereIn('status', $statuses)
            ->when($this->agentFilter, fn ($q) => $q->where('agent_id', $this->agentFilter))
            ->latest('assigned_at')
            ->paginate(15);

        return view('livewire.collections.collections-board', [
            'mode' => 'assignments',
            'items' => $items,
            'agents' => $agents,
        ]);
    }
}
