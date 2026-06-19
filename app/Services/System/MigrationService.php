<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class MigrationService
{
    public function isEnabled(): bool
    {
        return (bool) config('app.allow_web_migrations', true);
    }

    /**
     * @return array{
     *     ran_count: int,
     *     pending_count: int,
     *     pending: list<string>,
     *     last_batch: int|null
     * }
     */
    public function status(): array
    {
        $migrator = app('migrator');
        $files = $migrator->getMigrationFiles(database_path('migrations'));
        $ran = $migrator->getRepository()->getRan();
        $pending = array_values(array_diff(array_keys($files), $ran));

        sort($pending);

        return [
            'ran_count' => count($ran),
            'pending_count' => count($pending),
            'pending' => $pending,
            'last_batch' => DB::table('migrations')->max('batch'),
        ];
    }

    /**
     * @return array{
     *     success: bool,
     *     output: string,
     *     migrated_count: int,
     *     status: array
     * }
     */
    public function runPending(): array
    {
        if (! $this->isEnabled()) {
            throw new \RuntimeException('Running migrations from the web UI is disabled on this server.');
        }

        $before = $this->status()['pending_count'];

        Artisan::call('migrate', ['--force' => true]);
        $output = trim(Artisan::output());

        $status = $this->status();

        return [
            'success' => true,
            'output' => $output,
            'migrated_count' => max(0, $before - $status['pending_count']),
            'status' => $status,
        ];
    }
}
