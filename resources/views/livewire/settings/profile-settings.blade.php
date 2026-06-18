<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-ink">Profile</h1>
        <p class="text-sm text-muted mt-1">Your account information.</p>
    </div>

    <div class="bg-surface-2 rounded-2xl border border-border p-6 space-y-4 max-w-lg">
        <div>
            <label class="block text-xs font-semibold text-muted uppercase tracking-wider mb-1.5">Name</label>
            <div class="px-4 py-2.5 text-sm bg-surface border border-border rounded-xl text-ink">{{ $name }}</div>
        </div>
        <div>
            <label class="block text-xs font-semibold text-muted uppercase tracking-wider mb-1.5">Email</label>
            <div class="px-4 py-2.5 text-sm bg-surface border border-border rounded-xl text-ink">{{ $email }}</div>
        </div>
        <p class="text-xs text-muted">Profile editing will be available in a future update.</p>
    </div>
</div>
