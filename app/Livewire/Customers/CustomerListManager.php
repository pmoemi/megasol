<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use App\Models\CustomerList;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Customer Groups')]
class CustomerListManager extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public bool $showAddCustomers = false;

    public ?int $addingToListId = null;

    public string $customerSearch = '';

    /** @var array<int> */
    public array $selectedCustomerIds = [];

    public ?int $confirmDeleteId = null;

    public function createList(): void
    {
        $this->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        if ($this->editingId) {
            $list = CustomerList::findOrFail($this->editingId);
            $list->update([
                'name' => $this->name,
                'description' => $this->description,
            ]);
            session()->flash('success', "Group \"{$this->name}\" updated.");
        } else {
            CustomerList::create([
                'name' => $this->name,
                'description' => $this->description,
            ]);
            session()->flash('success', "Group \"{$this->name}\" created.");
        }

        $this->resetForm();
    }

    public function editList(int $id): void
    {
        $list = CustomerList::findOrFail($id);

        $this->editingId = $id;
        $this->name = $list->name;
        $this->description = $list->description ?? '';
        $this->showForm = true;
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmDeleteId = $id;
    }

    public function deleteList(): void
    {
        if ($this->confirmDeleteId) {
            $list = CustomerList::findOrFail($this->confirmDeleteId);
            $list->customers()->detach();
            $list->delete();
            session()->flash('success', "Group \"{$list->name}\" deleted.");
        }

        $this->confirmDeleteId = null;
    }

    public function openAddCustomers(int $listId): void
    {
        $this->addingToListId = $listId;
        $this->customerSearch = '';
        $this->selectedCustomerIds = [];
        $this->showAddCustomers = true;
    }

    public function toggleCustomer(int $customerId): void
    {
        if (in_array($customerId, $this->selectedCustomerIds, true)) {
            $this->selectedCustomerIds = array_values(array_diff($this->selectedCustomerIds, [$customerId]));
        } else {
            $this->selectedCustomerIds[] = $customerId;
        }
    }

    public function addSelectedCustomers(): void
    {
        if (! $this->addingToListId || empty($this->selectedCustomerIds)) {
            return;
        }

        $list = CustomerList::findOrFail($this->addingToListId);
        $existingIds = $list->customers()->pluck('customers.id')->all();
        $newIds = array_diff($this->selectedCustomerIds, $existingIds);

        if (! empty($newIds)) {
            $list->customers()->syncWithoutDetaching($newIds);
        }

        $added = count($newIds);
        session()->flash('success', "{$added} customer(s) added to \"{$list->name}\".");
        $this->showAddCustomers = false;
        $this->selectedCustomerIds = [];
    }

    public function removeCustomer(int $listId, int $customerId): void
    {
        $list = CustomerList::findOrFail($listId);
        $list->customers()->detach($customerId);
        session()->flash('success', 'Customer removed from group.');
    }

    public function resetForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->name = '';
        $this->description = '';
    }

    public function render()
    {
        $lists = CustomerList::query()
            ->withCount('customers')
            ->orderBy('name')
            ->get();

        $availableCustomers = collect();
        if ($this->showAddCustomers && strlen($this->customerSearch) >= 2) {
            $term = '%'.$this->customerSearch.'%';
            $availableCustomers = Customer::query()
                ->where(function ($q) use ($term) {
                    $q->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('account_number', 'like', $term);
                })
                ->orderBy('first_name')
                ->limit(20)
                ->get();
        }

        return view('livewire.customers.customer-list-manager', [
            'lists' => $lists,
            'availableCustomers' => $availableCustomers,
        ]);
    }
}
