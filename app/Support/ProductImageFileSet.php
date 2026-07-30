<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

final class ProductImageFileSet
{
    /**
     * Return the path relative to the products disk for a stored image URL/path.
     */
    public static function relativePath(?string $storedPath): ?string
    {
        if (! is_string($storedPath) || trim($storedPath) === '') {
            return null;
        }

        $path = parse_url(trim($storedPath), PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');
        $diskUrl = (string) config('filesystems.disks.products.url', 'media/img/products/');
        $diskPath = parse_url($diskUrl, PHP_URL_PATH);
        $prefix = trim(str_replace('\\', '/', is_string($diskPath) ? $diskPath : $diskUrl), '/');

        if ($prefix !== '') {
            $prefix .= '/';
            $position = strpos($path, $prefix);

            if ($position === false) {
                return null;
            }

            $path = substr($path, $position + strlen($prefix));
        }

        $path = ltrim($path, '/');

        if ($path === '' || self::containsUnsafeSegment($path)) {
            return null;
        }

        return $path;
    }

    /**
     * Return a stable key shared by the JPG, WebP and thumbnail variants.
     */
    public static function familyKeyFromRelativePath(string $relativePath): ?string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

        if ($relativePath === '' || self::containsUnsafeSegment($relativePath)) {
            return null;
        }

        $family = preg_replace('/(?:-thumb)?\.(?:jpe?g|png|webp)$/i', '', $relativePath);

        if (! is_string($family) || $family === $relativePath || $family === '') {
            return null;
        }

        return $family;
    }

    public static function familyKeyFromStoredPath(?string $storedPath): ?string
    {
        $relativePath = self::relativePath($storedPath);

        return $relativePath === null ? null : self::familyKeyFromRelativePath($relativePath);
    }

    /**
     * Delete every generated variant belonging to a stored product image.
     *
     * @return array<int, string> deleted relative paths
     */
    public static function deleteForStoredPath(?string $storedPath): array
    {
        $relativePath = self::relativePath($storedPath);
        $family = $relativePath === null ? null : self::familyKeyFromRelativePath($relativePath);

        if ($family === null) {
            return [];
        }

        $disk = Storage::disk('products');
        $paths = [
            $family . '.jpg',
            $family . '.jpeg',
            $family . '.png',
            $family . '.webp',
            $family . '-thumb.webp',
        ];
        $existing = array_values(array_filter($paths, static function (string $path) use ($disk): bool {
            return $disk->exists($path);
        }));

        if ($existing !== []) {
            $disk->delete($existing);
        }

        return array_values(array_filter($existing, static function (string $path) use ($disk): bool {
            return ! $disk->exists($path);
        }));
    }

    private static function containsUnsafeSegment(string $path): bool
    {
        return in_array('..', explode('/', $path), true);
    }
}
