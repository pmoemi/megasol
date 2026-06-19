<?php

namespace App\Livewire\Customers;

use App\Exports\CustomersExport;
use App\Models\Customer;
use App\Models\CustomerList;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Layout('components.layouts.app')]
#[Title('Customers')]
class CustomerIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortField = 'updated_at';

    #[Url]
    public string $sortDirection = 'desc';

    #[Url]
    public string $paymentStatusFilter = '';

    #[Url]
    public string $accountStatusFilter = '';

    #[Url]
    public string $lifecycleFilter = '';

    #[Url]
    public string $selectedGroup = '';

    /** @var array<int> */
    public array $selectedIds = [];

    public bool $selectAll = false;

    public string $bulkAction = '';

    public ?int $bulkGroupId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
        $this->selectedIds = [];
        $this->selectAll   = false;
    }

    public function updatingPaymentStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingAccountStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingLifecycleFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSelectedGroup(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField     = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function toggleSelectAll(): void
    {
        if ($this->selectAll) {
            $this->selectedIds = $this->buildQuery()
                ->orderBy($this->sortField, $this->sortDirection)
                ->forPage($this->getPage(), 20)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->toArray();
        } else {
            $this->selectedIds = [];
        }
    }

    public function deleteCustomer(int $id): void
    {
        Customer::findOrFail($id)->delete();
        $this->selectedIds = array_filter($this->selectedIds, fn ($i) => (int) $i !== $id);
        $this->dispatch('toast', type: 'success', message: 'Customer deleted.');
    }

    public function addToGroup(int $listId): void
    {
        if (empty($this->selectedIds)) {
            return;
        }

        $list = CustomerList::findOrFail($listId);
        $ids  = array_map('intval', $this->selectedIds);

        // syncWithoutDetaching so we don't remove existing memberships
        $list->customers()->syncWithoutDetaching($ids);

        $this->dispatch('toast', type: 'success', message: count($ids) . ' customer(s) added to "' . $list->name . '".');
        $this->selectedIds = [];
        $this->selectAll   = false;
        $this->bulkAction  = '';
    }

    public function removeFromGroup(int $listId): void
    {
        if (empty($this->selectedIds)) {
            return;
        }

        $list = CustomerList::findOrFail($listId);
        $ids  = array_map('intval', $this->selectedIds);
        $list->customers()->detach($ids);

        $this->dispatch('toast', type: 'success', message: count($ids).' customer(s) removed from "'.$list->name.'".');
        $this->selectedIds = [];
        $this->selectAll   = false;
        $this->bulkAction  = '';
        $this->bulkGroupId = null;
    }

    public function executeBulkAction(): void
    {
        if (! $this->bulkAction || empty($this->selectedIds)) {
            return;
        }

        $ids = array_map('intval', $this->selectedIds);

        if ($this->bulkAction === 'delete') {
            Customer::whereIn('id', $ids)->delete();
            $this->dispatch('toast', type: 'success', message: count($ids).' customer(s) deleted.');
        } elseif ($this->bulkAction === 'export') {
            $this->export(ids: $ids);

            return;
        } elseif ($this->bulkAction === 'add_to_group' && $this->bulkGroupId) {
            $this->addToGroup($this->bulkGroupId);

            return;
        } elseif ($this->bulkAction === 'remove_from_group' && $this->bulkGroupId) {
            $this->removeFromGroup($this->bulkGroupId);

            return;
        }

        $this->selectedIds = [];
        $this->selectAll   = false;
        $this->bulkAction  = '';
        $this->bulkGroupId = null;
    }

    public function export(array $ids = []): BinaryFileResponse
    {
        $exportIds = count($ids) > 0 ? $ids : null;

        return Excel::download(
            new CustomersExport($this->search ?: null, $exportIds),
            'customers-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    private function buildQuery()
    {
        return Customer::query()
            ->when($this->search, function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($q2) use ($term) {
                    $q2->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('account_number', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->when($this->paymentStatusFilter, fn ($q) => $q->where('payment_status', $this->paymentStatusFilter))
            ->when($this->accountStatusFilter, fn ($q) => $q->where('account_status', $this->accountStatusFilter))
            ->when($this->lifecycleFilter, fn ($q) => $q->where('lifecycle_stage', $this->lifecycleFilter))
            ->when($this->selectedGroup, fn ($q) => $q->whereHas('customerLists', fn ($q2) => $q2->where('customer_lists.id', $this->selectedGroup)));
    }

    public function render()
    {
        $customers = $this->buildQuery()
            ->with('customerLists')
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(20);

        $totalCustomers  = Customer::count();
        $customerGroups  = CustomerList::query()
            ->withCount('customers')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('livewire.customers.customer-index', compact('customers', 'totalCustomers', 'customerGroups'));
    }
}
