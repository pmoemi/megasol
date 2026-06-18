<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Helpers for the public upload disk on shared hosting (flat root deploy).
 *
 * Laravel stores uploads in storage/app/public/ but the web URL /storage/...
 * is served from public/storage/ on cPanel when no symlink exists.
 */
class PublicDisk
{
    /**
     * Copy a public-disk file into public/storage/ so Apache can serve it.
     */
    public static function mirrorToWeb(string $path): void
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $source = Storage::disk('public')->path($path);

        if (! is_file($source)) {
            return;
        }

        $target = public_path('storage/'.$path);
        $dir = dirname($target);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        copy($source, $target);
    }

    /**
     * Remove a file from both storage/app/public and public/storage.
     */
    public static function delete(string $path): void
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        $mirror = public_path('storage/'.$path);

        if (is_file($mirror)) {
            unlink($mirror);
        }
    }

    /**
     * Resolve a readable file path, mirroring to public/storage when needed.
     *
     * @return non-empty-string|null
     */
    public static function resolvePath(string $path): ?string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $canonical = Storage::disk('public')->path($path);
        $web = public_path('storage/'.$path);

        if (is_file($canonical)) {
            if (! is_file($web)) {
                self::mirrorToWeb($path);
            }

            return is_file($web) ? $web : $canonical;
        }

        return is_file($web) ? $web : null;
    }
}
