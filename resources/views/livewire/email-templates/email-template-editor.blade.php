<div>
    <div class="mb-4">
        <a href="{{ route('email-templates.index') }}" wire:navigate class="text-sm text-muted hover:text-ink">&larr; Back to email templates</a>
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
                    <label class="block text-sm font-medium text-muted mb-1">Category</label>
                    <select wire:model="category" class="input">
                        <option value="general">General</option>
                        <option value="payment">Payment</option>
                        <option value="welcome">Welcome</option>
                        <option value="promotion">Promotion</option>
                        <option value="notification">Notification</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-muted mb-1">Subject *</label>
                    <input type="text" wire:model="subject" class="input @error('subject') !border-danger @enderror">
                    @error('subject') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-muted mb-1">HTML Body *</label>
                <textarea wire:model="body_html" rows="12" class="input font-mono text-xs @error('body_html') !border-danger @enderror"></textarea>
                @error('body_html') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                <p class="text-xs text-muted mt-1">Merge tags: {first_name}, {last_name}, {email}, {account_number}, {balance}, {next_payment_date}</p>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" wire:model="is_active" id="is_active" class="w-4 h-4 rounded border-border text-brand">
                <label for="is_active" class="text-sm text-muted">Active</label>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="btn-primary">Save Template</button>
                <a href="{{ route('email-templates.index') }}" wire:navigate class="btn-secondary">Cancel</a>
            </div>
        </div>
    </form>
</div>
