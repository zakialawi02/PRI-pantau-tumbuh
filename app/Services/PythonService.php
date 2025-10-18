<?php

namespace App\Services;

class PythonService
{
    /**
     * Resolve the path to the Python executable for the given base path.
     *
     * @param string $basePath The base path to the script directory.
     * @return string|null The path to the Python executable, or null if not found.
     */
    public function resolvePythonPath(string $basePath): ?string
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $candidates = [
                $basePath . '\\venv\\Scripts\\python.exe',
                $basePath . '\\.venv\\Scripts\\python.exe',
            ];
        } else {
            $candidates = [
                $basePath . '/venv/bin/python',
                $basePath . '/.venv/bin/python',
            ];
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Build the process environment with the given overrides.
     *
     * @param array $overrides An array of environment variable overrides.
     * @return array The resulting process environment.
     */
    public function buildProcessEnvironment(array $overrides = []): array
    {
        $baseEnv = [];
        foreach (array_merge($_ENV, $_SERVER) as $key => $value) {
            if (is_string($key) && !array_key_exists($key, $baseEnv)) {
                $baseEnv[$key] = $value;
            }
        }

        foreach ($overrides as $key => $value) {
            $baseEnv[$key] = $value;
        }

        return $baseEnv;
    }
}
