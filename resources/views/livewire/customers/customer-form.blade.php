<div>
    <div class="mb-4">
        <a href="{{ route('customers.index') }}" wire:navigate class="text-sm text-muted hover:text-ink">&larr; Back to customers</a>
    </div>

    <form wire:submit="save" class="card">
        <div class="card-body space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">First Name *</label>
                    <input type="text" wire:model="first_name" class="input @error('first_name') !border-danger @enderror">
                    @error('first_name') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Last Name</label>
                    <input type="text" wire:model="last_name" class="input">
                </div>
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Phone *</label>
                    <input type="text" wire:model="phone" class="input @error('phone') !border-danger @enderror">
                    @error('phone') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Email</label>
                    <input type="email" wire:model="email" class="input">
                </div>
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Account Number</label>
                    <input type="text" wire:model="account_number" class="input">
                </div>
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Product Type</label>
                    <input type="text" wire:model="product_type" class="input">
                </div>
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Location</label>
                    <input type="text" wire:model="location" class="input">
                </div>
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Payment Status</label>
                    <select wire:model="payment_status" class="input">
                        <option value="current">Current</option>
                        <option value="due_soon">Due Soon</option>
                        <option value="overdue">Overdue</option>
                        <option value="paid_off">Paid Off</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Next Payment Date</label>
                    <input type="date" wire:model="next_payment_date" class="input">
                </div>
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Outstanding Balance</label>
                    <input type="number" step="0.01" wire:model="outstanding_balance" class="input">
                </div>
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Lifecycle Stage</label>
                    <select wire:model="lifecycle_stage" class="input">
                        <option value="new">New</option>
                        <option value="active">Active</option>
                        <option value="at_risk">At Risk</option>
                        <option value="loyal">Loyal</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Activated At</label>
                    <input type="datetime-local" wire:model="activated_at" class="input">
                </div>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="btn-primary">Save Customer</button>
                <a href="{{ route('customers.index') }}" wire:navigate class="btn-secondary">Cancel</a>
            </div>
        </div>
    </form>
</div>
