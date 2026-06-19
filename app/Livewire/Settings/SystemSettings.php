<?php

namespace App\Livewire\Settings;

use App\Services\System\CacheService;
use App\Services\System\MigrationService;
use App\Support\CronJobHelper;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.settings', ['title' => 'System'])]
class SystemSettings extends Component
{
    /** @var array<string, mixed> */
    public array $migrationStatus = [];

    /** @var array<string, mixed> */
    public array $cacheStatus = [];

    public bool $webMigrationsEnabled = true;

    public bool $webCacheClearEnabled = true;

    public ?string $statusMessage = null;

    public bool $statusIsError = false;

    public ?string $migrationOutput = null;

    public ?string $cacheOutput = null;

    public string $appUrl = '';

    public string $artisanPath = '';

    public string $phpBinary = '/usr/local/bin/php';

    public string $queueConnection = 'sync';

    /** @var array<int, array{label: string, schedule: string, command: string, description: string, required: bool}> */
    public array $cronJobs = [];

    /** @var array<int, array{command: string, frequency: string}> */
    public array $scheduledTasks = [];

    public function mount(MigrationService $migrations, CacheService $caches): void
    {
        abort_unless($this->canManageSystem(), 403);

        $this->refreshMigrationStatus($migrations);
        $this->refreshCacheStatus($caches);
        $this->refreshCronJobs();
    }

    protected function refreshCronJobs(): void
    {
        $this->appUrl = rtrim((string) config('app.url'), '/');
        $this->artisanPath = base_path('artisan');
        $this->phpBinary = PHP_BINARY !== '' ? PHP_BINARY : '/usr/local/bin/php';
        $this->queueConnection = (string) config('queue.default', 'sync');
        $this->cronJobs = CronJobHelper::commands($this->phpBinary, $this->artisanPath);
        $this->scheduledTasks = CronJobHelper::scheduledTasks();
    }

    public function runMigrations(MigrationService $migrations): void
    {
        abort_unless($this->canManageSystem(), 403);

        $this->migrationOutput = null;
        $this->statusMessage = null;
        $this->statusIsError = false;

        try {
            $result = $migrations->runPending();
            $this->migrationStatus = $result['status'];
            $this->migrationOutput = $result['output'] !== '' ? $result['output'] : null;

            if ($result['migrated_count'] > 0) {
                $this->statusMessage = "Ran {$result['migrated_count']} migration(s) successfully.";
            } else {
                $this->statusMessage = 'Database is already up to date.';
            }
        } catch (\Throwable $e) {
            $this->statusIsError = true;
            $this->statusMessage = $e->getMessage();
            $this->refreshMigrationStatus($migrations);
        }
    }

    public function clearCache(string $type, CacheService $caches): void
    {
        abort_unless($this->canManageSystem(), 403);

        $this->cacheOutput = null;
        $this->statusMessage = null;
        $this->statusIsError = false;

        try {
            $result = $caches->clear($type);
            $this->cacheStatus = $result['status'];
            $this->cacheOutput = $result['output'] !== '' ? $result['output'] : null;

            $label = CacheService::CLEAR_TYPES[$type] ?? $type;
            $this->statusMessage = "{$label} cleared successfully.";
        } catch (\Throwable $e) {
            $this->statusIsError = true;
            $this->statusMessage = $e->getMessage();
            $this->refreshCacheStatus($caches);
        }
    }

    protected function refreshMigrationStatus(MigrationService $migrations): void
    {
        $this->webMigrationsEnabled = $migrations->isEnabled();
        $this->migrationStatus = $migrations->status();
    }

    protected function refreshCacheStatus(CacheService $caches): void
    {
        $this->webCacheClearEnabled = $caches->isEnabled();
        $this->cacheStatus = $caches->status();
    }

    protected function canManageSystem(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->hasRole('Admin') || $user->can('manage settings'));
    }

    public function render()
    {
        return view('livewire.settings.system-settings');
    }
}
