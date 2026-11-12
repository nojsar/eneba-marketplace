<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Reads configuration from environment variables.
 *
 * On Render the variables are set in the dashboard. Locally they live in
 * backend/.env (git-ignored), which load() copies into the environment.
 */
final class Env
{
    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return; // normal in production: there is no .env file there
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim(trim($value), '"\'');

            // A variable that is already set (e.g. by the shell) wins over the file.
            if (getenv($key) === false) {
                putenv("$key=$value");
            }
        }
    }

    public static function get(string $key): string
    {
        return self::optional($key) ?? throw new RuntimeException("Missing environment variable $key");
    }

    /** For settings only some features need (e.g. Stripe keys): null if not set. */
    public static function optional(string $key): ?string
    {
        $value = getenv($key);

        return $value === false || $value === '' ? null : $value;
    }
}
