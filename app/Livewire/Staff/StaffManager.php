<?php

namespace App\Livewire\Staff;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

#[Layout('components.layouts.app', ['title' => 'Staff Management'])]
class StaffManager extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $role = 'Agent';

    public bool $is_active = true;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', Password::defaults()],
            'role' => ['required', 'string', 'exists:roles,name'],
            'is_active' => 'boolean',
        ];
    }

    public function openForm(): void
    {
        $this->reset(['name', 'email', 'password', 'role', 'is_active']);
        $this->role = 'Agent';
        $this->is_active = true;
        $this->showForm = true;
    }

    public function createStaff(): void
    {
        $this->validate();

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'is_active' => $this->is_active,
        ]);

        $user->assignRole($this->role);

        $this->showForm = false;
        session()->flash('success', 'Staff member created successfully.');
    }

    public function toggleActive(int $id): void
    {
        $user = User::findOrFail($id);

        if ($user->id === auth()->id()) {
            return;
        }

        $user->update(['is_active' => ! $user->is_active]);
    }

    public function render()
    {
        $staff = User::query()
            ->with('roles')
            ->orderBy('name')
            ->paginate(15);

        $roles = Role::query()->orderBy('name')->pluck('name');

        return view('livewire.staff.staff-manager', compact('staff', 'roles'));
    }
}
