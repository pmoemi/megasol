<?php

namespace Tests\Feature;

use App\Livewire\Settings\SystemSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Permission::firstOrCreate(['name' => 'manage settings']);
        $role = Role::firstOrCreate(['name' => 'Admin']);
        $role->givePermissionTo('manage settings');

        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin'.Str::random(4).'@test.com',
            'password' => bcrypt('x'),
            'is_active' => true,
        ]);
        $user->assignRole('Admin');

        return $user;
    }

    public function test_admin_can_clear_caches_from_system_settings(): void
    {
        Artisan::call('view:cache');

        $this->actingAs($this->admin());

        Livewire::test(SystemSettings::class)
            ->call('clearCache', 'view')
            ->assertSet('statusIsError', false)
            ->assertSee('Compiled views cleared successfully');
    }

    public function test_non_admin_cannot_access_system_settings(): void
    {
        $user = User::create([
            'name' => 'Agent',
            'email' => 'agent'.Str::random(4).'@test.com',
            'password' => bcrypt('x'),
            'is_active' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(SystemSettings::class)
            ->assertForbidden();
    }

    public function test_system_settings_shows_copyable_cron_jobs(): void
    {
        config(['app.url' => 'https://megasol.megawattenergiesltd.com']);
        config(['queue.default' => 'sync']);

        $this->actingAs($this->admin());

        Livewire::test(SystemSettings::class)
            ->assertSee('Cron jobs (cPanel)')
            ->assertSee('https://megasol.megawattenergiesltd.com')
            ->assertSee('schedule:run')
            ->assertSee('sms:run-automations')
            ->assertSee('SMS sends immediately');
    }

    public function test_system_settings_shows_queue_worker_cron_when_not_sync(): void
    {
        config(['queue.default' => 'database']);

        $this->actingAs($this->admin());

        Livewire::test(SystemSettings::class)
            ->assertSee('Queue worker')
            ->assertSee('queue:work --stop-when-empty');
    }
}
