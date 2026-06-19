<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class CacheService
{
    /** @var array<string, string> */
    public const CLEAR_TYPES = [
        'all' => 'All caches',
        'view' => 'Compiled views',
        'config' => 'Config cache',
        'route' => 'Route cache',
        'application' => 'Application cache',
    ];

    public function isEnabled(): bool
    {
        return (bool) config('app.allow_web_cache_clear', true);
    }

    /**
     * @return array{
     *     compiled_views: int,
     *     config_cached: bool,
     *     routes_cached: bool,
     *     events_cached: bool
     * }
     */
    public function status(): array
    {
        $viewsPath = storage_path('framework/views');

        return [
            'compiled_views' => File::isDirectory($viewsPath)
                ? count(File::glob($viewsPath.DIRECTORY_SEPARATOR.'*.php') ?: [])
                : 0,
            'config_cached' => File::exists(base_path('bootstrap/cache/config.php')),
            'routes_cached' => File::exists(base_path('bootstrap/cache/routes-v7.php')),
            'events_cached' => File::exists(base_path('bootstrap/cache/events.php')),
        ];
    }

    /**
     * @return array{success: bool, output: string, type: string, status: array}
     */
    public function clear(string $type): array
    {
        if (! $this->isEnabled()) {
            throw new \RuntimeException('Clearing caches from the web UI is disabled on this server.');
        }

        if (! array_key_exists($type, self::CLEAR_TYPES)) {
            throw new \InvalidArgumentException('Unknown cache type: '.$type);
        }

        $command = match ($type) {
            'all' => 'optimize:clear',
            'view' => 'view:clear',
            'config' => 'config:clear',
            'route' => 'route:clear',
            'application' => 'cache:clear',
        };

        Artisan::call($command);
        $output = trim(Artisan::output());

        return [
            'success' => true,
            'output' => $output,
            'type' => $type,
            'status' => $this->status(),
        ];
    }
}
