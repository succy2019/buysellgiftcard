<?php
/**
 * Lightweight .env loader for DFQ Exchange.
 *
 * Loads key=value pairs from the project-root .env file into the process
 * environment so getenv()/envValue() behave the same for web requests, setup
 * scripts, and CLI migrations. Variables already present in the real
 * environment always take precedence over the .env file.
 */

function loadEnvFile($path) {
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip blanks, comments, and malformed lines.
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);

        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
            continue;
        }

        $value = trim($value);

        // Strip matching single or double quotes.
        $length = strlen($value);
        if ($length >= 2) {
            $first = $value[0];
            $last = $value[$length - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        // Never override values supplied by the real environment.
        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

loadEnvFile(dirname(__DIR__) . '/.env');
