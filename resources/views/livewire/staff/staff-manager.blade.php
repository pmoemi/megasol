<div>
    @if (session('success'))
        <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif

    <div class="flex justify-end mb-6">
        <button type="button" wire:click="openForm" class="btn-primary btn-sm">Add Staff Member</button>
    </div>

    @if ($showForm)
        <form wire:submit="createStaff" class="card mb-6">
            <div class="card-body space-y-4">
                <h3 class="text-sm font-semibold text-ink">New Staff Member</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Name *</label>
                        <input type="text" wire:model="name" class="input @error('name') !border-danger @enderror">
                        @error('name') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Email *</label>
                        <input type="email" wire:model="email" class="input @error('email') !border-danger @enderror">
                        @error('email') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Password *</label>
                        <input type="password" wire:model="password" class="input @error('password') !border-danger @enderror">
                        @error('password') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Role *</label>
                        <select wire:model="role" class="input">
                            @foreach ($roles as $roleName)
                                <option value="{{ $roleName }}">{{ $roleName }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" wire:model="is_active" id="staff_active" class="w-4 h-4 rounded border-border text-brand">
                    <label for="staff_active" class="text-sm text-muted">Active</label>
                </div>
                <div class="flex gap-3">
                    <button type="submit" class="btn-primary btn-sm">Create</button>
                    <button type="button" wire:click="$set('showForm', false)" class="btn-secondary btn-sm">Cancel</button>
                </div>
            </div>
        </form>
    @endif

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($staff as $member)
                        <tr wire:key="staff-{{ $member->id }}">
                            <td class="font-medium">{{ $member->name }}</td>
                            <td>{{ $member->email }}</td>
                            <td>
                                @foreach ($member->roles as $role)
                                    <span class="badge badge-info">{{ $role->name }}</span>
                                @endforeach
                            </td>
                            <td>
                                <span @class(['badge', 'badge-success' => $member->is_active, 'badge-neutral' => ! $member->is_active])>
                                    {{ $member->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                @if ($member->id !== auth()->id())
                                    <button type="button" wire:click="toggleActive({{ $member->id }})" class="text-sm text-brand hover:text-brand-strong">
                                        {{ $member->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-8">No staff members found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($staff->hasPages())
            <div class="px-4 py-3 border-t border-border/40">{{ $staff->links() }}</div>
        @endif
    </div>
</div>
