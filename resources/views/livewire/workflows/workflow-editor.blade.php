<div>
    <div class="mb-4">
        <a href="{{ route('workflows.index') }}" wire:navigate class="text-sm text-muted hover:text-ink">&larr; Back to workflows</a>
    </div>

    <form wire:submit="save" class="card">
        <div class="card-body space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Workflow Name *</label>
                    <input type="text" wire:model="name" class="input">
                </div>
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Trigger *</label>
                    <select wire:model="trigger_type" class="input">
                        <option value="manual">Manual</option>
                        <option value="customer_created">Customer Created</option>
                        <option value="payment_due">Payment Due</option>
                        <option value="payment_overdue">Payment Overdue</option>
                        <option value="scheduled">Scheduled</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-muted mb-1">Description</label>
                    <textarea wire:model="description" rows="2" class="input"></textarea>
                </div>
                @if ($trigger_type === 'scheduled')
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Cron Schedule</label>
                        <input type="text" wire:model="schedule_cron" class="input" placeholder="0 8 * * *">
                    </div>
                @endif
            </div>

            <div>
                <label class="block text-sm font-medium text-muted mb-1">Steps (JSON) *</label>
                <textarea wire:model="steps_json" rows="12" class="input font-mono text-xs @error('steps_json') !border-danger @enderror"></textarea>
                @error('steps_json') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                <p class="text-xs text-muted mt-2">Step types: <code>send_sms</code>, <code>send_email</code>, <code>delay</code>. Example email step includes <code>subject</code> and <code>body_html</code>.</p>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" wire:model="is_active" id="wf_active" class="w-4 h-4 rounded border-border text-brand">
                <label for="wf_active" class="text-sm text-muted">Active</label>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="btn-primary">Save Workflow</button>
                <a href="{{ route('workflows.index') }}" wire:navigate class="btn-secondary">Cancel</a>
            </div>
        </div>
    </form>
</div>
