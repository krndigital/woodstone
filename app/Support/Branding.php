<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

/** Uploaded logo and favicon (Settings). Falls back to the built-in ones. */
final class Branding
{
    public static function url(string $kind): ?string
    {
        try {
            $path = Settings::get($kind.'_path');
        } catch (Throwable) {
            return null;
        }

        return is_string($path) && $path !== '' ? Storage::disk('public')->url($path) : null;
    }
}
