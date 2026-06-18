<div>
    <div class="mb-4">
        <a href="{{ route('templates.index') }}" wire:navigate class="text-sm text-muted hover:text-ink">&larr; Back to templates</a>
    </div>

    <form wire:submit="save" class="card">
        <div class="card-body space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Template Name *</label>
                    <input type="text" wire:model="name" class="input @error('name') !border-danger @enderror">
                    @error('name') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Type *</label>
                    <select wire:model="type" class="input">
                        <option value="payment_reminder">Payment Reminder</option>
                        <option value="overdue">Overdue</option>
                        <option value="welcome">Welcome</option>
                        <option value="seasonal">Seasonal</option>
                        <option value="offer">Offer</option>
                        <option value="tip">Tip</option>
                        <option value="campaign">Campaign</option>
                        <option value="custom">Custom</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-muted mb-1">Message Body *</label>
                <textarea wire:model="body" rows="6" class="input @error('body') !border-danger @enderror"></textarea>
                @error('body') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                <p class="text-xs text-muted mt-1">Tags: {first_name}, {last_name}, {phone}, {account_number}, {balance}, {next_payment_date}</p>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" wire:model="is_active" id="is_active" class="w-4 h-4 rounded border-border text-brand">
                <label for="is_active" class="text-sm text-muted">Active</label>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="btn-primary">Save Template</button>
                <a href="{{ route('templates.index') }}" wire:navigate class="btn-secondary">Cancel</a>
            </div>
        </div>
    </form>
</div>
