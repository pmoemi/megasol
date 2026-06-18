@php
    $assetColors = ['active' => 'bg-success/15 text-success','faulty' => 'bg-warning/15 text-warning','repossessed' => 'bg-danger/15 text-danger','returned' => 'bg-surface text-muted','decommissioned' => 'bg-surface text-muted'];
@endphp

<div class="space-y-6">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-ink">Inventory</h1>
            <p class="text-sm text-muted mt-0.5">Track solar units in stock and assigned to customers, with installation and warranty details.</p>
        </div>
        @if(!$showForm)
        <button type="button" wire:click="newUnit" class="btn-primary inline-flex items-center gap-1.5 text-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Register Unit
        </button>
        @endif
    </div>

    {{-- Summary cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-surface-2 rounded-2xl border border-border p-4">
            <p class="text-xs text-muted">Total Units</p>
            <p class="text-2xl font-semibold text-ink mt-1">{{ $counts['total'] }}</p>
        </div>
        <div class="bg-surface-2 rounded-2xl border border-border p-4">
            <p class="text-xs text-muted">In Stock</p>
            <p class="text-2xl font-semibold text-ink mt-1">{{ $counts['in_stock'] }}</p>
        </div>
        <div class="bg-surface-2 rounded-2xl border border-border p-4">
            <p class="text-xs text-muted">Assigned</p>
            <p class="text-2xl font-semibold text-ink mt-1">{{ $counts['assigned'] }}</p>
        </div>
        <div class="bg-surface-2 rounded-2xl border border-border p-4">
            <p class="text-xs text-muted">Faulty</p>
            <p class="text-2xl font-semibold text-ink mt-1">{{ $counts['faulty'] }}</p>
        </div>
    </div>

    {{-- Register / edit form --}}
    @if($showForm)
    <div class="bg-surface-2 rounded-2xl border border-border p-5">
        <h4 class="text-sm font-semibold text-ink mb-4">{{ $editingAssetId ? 'Edit Unit' : 'Register a Unit' }}</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-1">Unit Serial *</label>
                <input type="text" wire:model="unitSerial" placeholder="e.g. SN-2A4F9C" class="input @error('unitSerial') !border-danger @enderror">
                @error('unitSerial') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-1">Product Name</label>
                <input type="text" wire:model="productName" placeholder="e.g. Solar Home Plus" class="input">
            </div>
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-1">Model</label>
                <input type="text" wire:model="model" placeholder="e.g. MS-300W" class="input">
            </div>
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-1">Status</label>
                <select wire:model="assetStatus" class="input">
                    @foreach(['active' => 'Active','faulty' => 'Faulty','repossessed' => 'Repossessed','returned' => 'Returned','decommissioned' => 'Decommissioned'] as $k => $v)
                    <option value="{{ $k }}">{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-1">Installation Date</label>
                <input type="date" wire:model="installationDate" class="input @error('installationDate') !border-danger @enderror">
                @error('installationDate') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-1">Warranty Expiry</label>
                <input type="date" wire:model="warrantyExpiry" class="input @error('warrantyExpiry') !border-danger @enderror">
                @error('warrantyExpiry') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-ink/80 mb-1">Notes</label>
                <textarea wire:model="notes" rows="2" class="input" placeholder="Condition, location in warehouse..."></textarea>
            </div>
        </div>
        <div class="flex items-center gap-3 mt-4">
            <button type="button" wire:click="saveUnit" class="btn-primary text-sm">{{ $editingAssetId ? 'Update Unit' : 'Add to Inventory' }}</button>
            <button type="button" wire:click="resetForm" class="text-sm text-muted hover:text-ink">Cancel</button>
        </div>
    </div>
    @endif

    {{-- Assign-to-customer form --}}
    @if($assigningAssetId)
    <div class="bg-surface-2 rounded-2xl border border-border p-5">
        <h4 class="text-sm font-semibold text-ink mb-4">Assign Unit to Customer</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-ink/80 mb-1">Customer *</label>
                <select wire:model="assignCustomerId" class="input @error('assignCustomerId') !border-danger @enderror">
                    <option value="">Select a customer...</option>
                    @foreach($customers as $c)
                    <option value="{{ $c->id }}">{{ $c->first_name }} {{ $c->last_name }} ({{ $c->account_number }})</option>
                    @endforeach
                </select>
                @error('assignCustomerId') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="flex items-center gap-3 mt-4">
            <button type="button" wire:click="assignToCustomer" class="btn-primary text-sm">Assign</button>
            <button type="button" wire:click="closeAssign" class="text-sm text-muted hover:text-ink">Cancel</button>
        </div>
    </div>
    @endif

    {{-- Filters --}}
    <div class="flex flex-wrap items-center gap-3">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search serial, product, model..." class="input max-w-xs">
        <select wire:model.live="assignment" class="input max-w-[160px]">
            <option value="">All Units</option>
            <option value="in_stock">In Stock</option>
            <option value="assigned">Assigned</option>
        </select>
        <select wire:model.live="status" class="input max-w-[160px]">
            <option value="">Any Status</option>
            @foreach(['active' => 'Active','faulty' => 'Faulty','repossessed' => 'Repossessed','returned' => 'Returned','decommissioned' => 'Decommissioned'] as $k => $v)
            <option value="{{ $k }}">{{ $v }}</option>
            @endforeach
        </select>
    </div>

    {{-- Units table --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Serial</th>
                        <th>Product</th>
                        <th>Model</th>
                        <th>Status</th>
                        <th>Customer</th>
                        <th>Installed</th>
                        <th>Warranty</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assets as $a)
                    <tr wire:key="unit-{{ $a->id }}">
                        <td class="font-mono text-xs">{{ $a->unit_serial }}</td>
                        <td>{{ $a->product_name ?: '—' }}</td>
                        <td>{{ $a->model ?: '—' }}</td>
                        <td>
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium capitalize {{ $assetColors[$a->status] ?? 'bg-surface text-muted' }}">{{ $a->status }}</span>
                        </td>
                        <td>
                            @if($a->customer)
                                <a href="{{ route('customers.show', $a->customer) }}" wire:navigate class="text-brand hover:text-brand-strong">{{ $a->customer->first_name }} {{ $a->customer->last_name }}</a>
                            @else
                                <span class="text-xs text-muted">In stock</span>
                            @endif
                        </td>
                        <td>{{ $a->installation_date?->format('M j, Y') ?? '—' }}</td>
                        <td>
                            @if($a->warranty_expiry)
                                <span class="{{ $a->is_under_warranty ? 'text-success' : 'text-danger' }}">{{ $a->warranty_expiry->format('M j, Y') }}</span>
                            @else — @endif
                        </td>
                        <td>
                            <div class="relative" x-data="{ open: false }">
                                <button type="button" @click="open = !open" class="p-1 text-muted hover:text-ink rounded-lg hover:bg-surface transition-colors" aria-label="Unit actions">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v.01M12 12v.01M12 19v.01"/></svg>
                                </button>
                                <div x-show="open" @click.outside="open = false" x-transition x-cloak class="absolute right-0 top-7 z-20 w-44 bg-surface-2 border border-border rounded-xl shadow-lg py-1">
                                    <button type="button" wire:click="editUnit({{ $a->id }})" @click="open = false" class="w-full text-left px-4 py-2 text-sm text-ink/80 hover:bg-surface transition-colors">Edit</button>
                                    @if($a->customer_id)
                                        <button type="button" wire:click="unassignUnit({{ $a->id }})" wire:confirm="Return unit {{ $a->unit_serial }} to stock?" @click="open = false" class="w-full text-left px-4 py-2 text-sm text-ink/80 hover:bg-surface transition-colors">Return to Stock</button>
                                    @else
                                        <button type="button" wire:click="openAssign({{ $a->id }})" @click="open = false" class="w-full text-left px-4 py-2 text-sm text-ink/80 hover:bg-surface transition-colors">Assign to Customer</button>
                                    @endif
                                    <button type="button" wire:click="deleteUnit({{ $a->id }})" wire:confirm="Remove unit {{ $a->unit_serial }} from inventory?" @click="open = false" class="w-full text-left px-4 py-2 text-sm text-danger hover:bg-danger/10 transition-colors">Remove</button>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-8">No units found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($assets->hasPages())
            <div class="px-4 py-3 border-t border-border/40">{{ $assets->links() }}</div>
        @endif
    </div>
</div>
