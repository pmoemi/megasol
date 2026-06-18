<?php

namespace App\Livewire\Inventory;

use App\Models\Customer;
use App\Models\CustomerAsset;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Inventory')]
class InventoryIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $assignment = '';

    // ── Register / edit unit form ────────────────────────────────────────
    public bool $showForm = false;

    public ?int $editingAssetId = null;

    public string $unitSerial = '';

    public string $productName = '';

    public string $model = '';

    public string $installationDate = '';

    public string $warrantyExpiry = '';

    public string $assetStatus = 'active';

    public string $notes = '';

    // ── Assign-to-customer form ──────────────────────────────────────────
    public ?int $assigningAssetId = null;

    public ?int $assignCustomerId = null;

    protected function rules(): array
    {
        return [
            'unitSerial' => 'required|string|max:255',
            'productName' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'installationDate' => 'nullable|date',
            'warrantyExpiry' => 'nullable|date',
            'assetStatus' => 'required|in:active,faulty,repossessed,returned,decommissioned',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingAssignment(): void
    {
        $this->resetPage();
    }

    public function newUnit(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function editUnit(int $assetId): void
    {
        $asset = CustomerAsset::findOrFail($assetId);

        $this->editingAssetId = $asset->id;
        $this->unitSerial = $asset->unit_serial;
        $this->productName = $asset->product_name ?? '';
        $this->model = $asset->model ?? '';
        $this->installationDate = $asset->installation_date?->format('Y-m-d') ?? '';
        $this->warrantyExpiry = $asset->warranty_expiry?->format('Y-m-d') ?? '';
        $this->assetStatus = $asset->status;
        $this->notes = $asset->notes ?? '';
        $this->showForm = true;
    }

    public function saveUnit(): void
    {
        $this->validate();

        $payload = [
            'unit_serial' => $this->unitSerial,
            'product_name' => $this->productName ?: null,
            'model' => $this->model ?: null,
            'installation_date' => $this->installationDate ?: null,
            'warranty_expiry' => $this->warrantyExpiry ?: null,
            'status' => $this->assetStatus,
            'notes' => $this->notes ?: null,
        ];

        if ($this->editingAssetId) {
            CustomerAsset::findOrFail($this->editingAssetId)->update($payload);
            $message = 'Unit updated.';
        } else {
            CustomerAsset::create($payload);
            $message = 'Unit added to inventory.';
        }

        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function deleteUnit(int $assetId): void
    {
        CustomerAsset::whereKey($assetId)->delete();
        $this->dispatch('toast', type: 'success', message: 'Unit removed from inventory.');
    }

    public function resetForm(): void
    {
        $this->reset([
            'showForm', 'editingAssetId', 'unitSerial', 'productName',
            'model', 'installationDate', 'warrantyExpiry', 'notes',
        ]);
        $this->assetStatus = 'active';
        $this->resetValidation();
    }

    // ── Assign to customer ────────────────────────────────────────────────

    public function openAssign(int $assetId): void
    {
        $this->assigningAssetId = $assetId;
        $this->assignCustomerId = null;
    }

    public function closeAssign(): void
    {
        $this->reset(['assigningAssetId', 'assignCustomerId']);
    }

    public function assignToCustomer(): void
    {
        $this->validate([
            'assignCustomerId' => 'required|integer|exists:customers,id',
        ]);

        $asset = CustomerAsset::findOrFail($this->assigningAssetId);
        $asset->update([
            'customer_id' => $this->assignCustomerId,
            'installation_date' => $asset->installation_date ?? now()->format('Y-m-d'),
        ]);

        $this->closeAssign();
        $this->dispatch('toast', type: 'success', message: 'Unit assigned to customer.');
    }

    public function unassignUnit(int $assetId): void
    {
        CustomerAsset::whereKey($assetId)->update(['customer_id' => null]);
        $this->dispatch('toast', type: 'success', message: 'Unit returned to stock.');
    }

    public function render()
    {
        $query = CustomerAsset::query()->with('customer');

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('unit_serial', 'like', $term)
                    ->orWhere('product_name', 'like', $term)
                    ->orWhere('model', 'like', $term);
            });
        }

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        if ($this->assignment === 'in_stock') {
            $query->inStock();
        } elseif ($this->assignment === 'assigned') {
            $query->assigned();
        }

        /** @var LengthAwarePaginator $assets */
        $assets = $query->latest('id')->paginate(15);

        $counts = [
            'total' => CustomerAsset::count(),
            'in_stock' => CustomerAsset::inStock()->count(),
            'assigned' => CustomerAsset::assigned()->count(),
            'faulty' => CustomerAsset::where('status', 'faulty')->count(),
        ];

        $customers = Customer::query()->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'account_number']);

        return view('livewire.inventory.inventory-index', [
            'assets' => $assets,
            'counts' => $counts,
            'customers' => $customers,
        ]);
    }
}
