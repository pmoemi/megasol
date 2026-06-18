<?php

namespace App\Http\Controllers;

use App\Support\PublicDisk;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicStorageController extends Controller
{
    /**
     * Serve files from the public disk when the storage symlink is missing
     * or when the server blocks direct /storage access (flat root deploy).
     */
    public function show(?string $path = null): BinaryFileResponse
    {
        $path = $this->resolvePublicDiskPath($path);

        if ($path === '' || str_contains($path, '..')) {
            abort(404);
        }

        $fullPath = PublicDisk::resolvePath($path);

        abort_unless($fullPath !== null, 404);

        return response()->file($fullPath, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    /**
     * Resolve the public-disk relative path from the request URI.
     */
    protected function resolvePublicDiskPath(?string $path): string
    {
        $uriPath = rawurldecode(parse_url(request()->getRequestUri(), PHP_URL_PATH) ?? '');
        $fromUri = ltrim((string) preg_replace('#^/storage/#', '', $uriPath), '/');

        $path = ltrim(rawurldecode(str_replace('\\', '/', (string) $path)), '/');

        if ($fromUri !== '' && str_contains($fromUri, '/')) {
            return $fromUri;
        }

        if ($fromUri !== '' && ($path === '' || PublicDisk::resolvePath($path) === null)) {
            return $fromUri;
        }

        return $path;
    }
}
