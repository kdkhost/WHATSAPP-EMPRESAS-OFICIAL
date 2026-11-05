<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ManagedAsset
{
    public static function replaceIfChanged(?string $previousUrl, ?string $nextUrl): void
    {
        $previousUrl = self::normalizeUrl($previousUrl);
        $nextUrl = self::normalizeUrl($nextUrl);

        if (! $previousUrl || $previousUrl === $nextUrl) {
            return;
        }

        self::deleteByUrl($previousUrl);
    }

    public static function deleteByUrl(?string $url): void
    {
        $path = self::storagePathFromUrl($url);

        if ($path === null) {
            return;
        }

        try {
            Storage::disk('public')->delete($path);
        } catch (\Throwable) {
        }

        try {
            DB::table('uploads')
                ->where('disk', 'public')
                ->where('path', $path)
                ->delete();
        } catch (\Throwable) {
        }
    }

    public static function storagePathFromUrl(?string $url): ?string
    {
        $url = self::normalizeUrl($url);

        if ($url === null) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return null;
        }

        $normalized = str_replace('\\', '/', $path);

        if (str_starts_with($normalized, '/storage/')) {
            $relative = ltrim(substr($normalized, strlen('/storage/')), '/');

            return $relative !== '' ? $relative : null;
        }

        return null;
    }

    protected static function normalizeUrl(?string $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        $url = trim($url);

        return $url !== '' ? $url : null;
    }
}
