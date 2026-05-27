<?php

require __DIR__.'/../vendor/autoload.php';

/**
 * Load tests/.env.testing into the env so integration tests can read live
 * config (token, base_url) without committing secrets. The file is
 * gitignored; tests/.env.testing.example is committed as a template.
 *
 * Simple line parser — no vlucas/phpdotenv dependency for a slim SDK.
 */
$envFile = __DIR__.'/.env.testing';
if (! is_file($envFile)) {
    return;
}

foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }
    if (! str_contains($line, '=')) {
        continue;
    }

    [$key, $value] = explode('=', $line, 2);
    $key = trim($key);
    $value = trim($value);
    // Strip wrapping quotes if present
    if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
        $value = substr($value, 1, -1);
    }

    if (getenv($key) === false) {
        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
