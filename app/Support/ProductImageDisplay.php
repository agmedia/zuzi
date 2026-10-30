<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

/** Resolve an admin preview URL without changing the stored image used by CRUD. */
final class ProductImageDisplay
{
    public static function url(?string $storedPath): string
    {
        $storedPath = trim((string) $storedPath);

        if ($storedPath === '') {
            return asset('media/avatars/avatar0.jpg');
        }

        $original = asset($storedPath);
        $parts = parse_url($storedPath);

        if (! is_array($parts) || ! self::isLocalImageUrl($parts)) {
            return $original;
        }

        $path = $parts['path'] ?? '';
        $diskPath = parse_url((string) config('filesystems.disks.products.url', 'media/img/products/'), PHP_URL_PATH);
        $prefix = '/' . trim((string) $diskPath, '/') . '/';
        $path = '/' . ltrim($path, '/');

        if (! str_starts_with($path, $prefix) || ! preg_match('/\.(?:jpe?g)$/i', $path)) {
            return $original;
        }

        $relativePath = rawurldecode(substr($path, strlen($prefix)));

        if ($relativePath === '' || str_contains($relativePath, '\\') || str_contains($relativePath, "\0")
            || preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $relativePath)) {
            return $original;
        }

        $webpPath = preg_replace('/\.(?:jpe?g)$/i', '.webp', $relativePath);

        try {
            $disk = Storage::disk('products');

            if (! $disk->exists($webpPath)) {
                return $original;
            }

            // Header inspection reads dimensions, not a full decode of every admin thumbnail.
            $image = @getimagesize($disk->path($webpPath));

            if (! $image || ($image[2] ?? null) !== IMAGETYPE_WEBP || $image[0] < 1 || $image[1] < 1) {
                return $original;
            }
        } catch (Throwable $exception) {
            return $original;
        }

        // Keep the original origin, query string and fragment; only replace the image suffix.
        return asset(preg_replace('/\.(?:jpe?g)(?=[?#]|$)/i', '.webp', $storedPath, 1));
    }

    private static function isLocalImageUrl(array $parts): bool
    {
        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        if (! isset($parts['host'])) {
            return ! isset($parts['scheme']);
        }

        if (isset($parts['scheme']) && ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        foreach ([config('app.url'), config('settings.images_domain'), config('filesystems.disks.products.url')] as $knownUrl) {
            $known = parse_url((string) $knownUrl);

            if (is_array($known) && isset($known['host'])
                && strcasecmp($known['host'], $parts['host']) === 0
                && ($known['port'] ?? null) === ($parts['port'] ?? null)) {
                return true;
            }
        }

        return false;
    }
}
