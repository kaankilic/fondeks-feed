<?php

namespace App\Auth;

use Illuminate\Contracts\Hashing\Hasher;
use Symfony\Component\Process\Process;

class ScryptHasher implements Hasher
{
    public function info($hashedValue): array
    {
        return ['alg' => 'scrypt'];
    }

    /**
     * Absolute node path — PHP-FPM runs with clear_env on by default, so the
     * worker has no PATH and a bare "node" would not resolve. Configurable via
     * NODE_BINARY, then common install locations, then PATH as a last resort.
     */
    private function nodeBinary(): string
    {
        $configured = env('NODE_BINARY');
        if ($configured && is_executable($configured)) {
            return $configured;
        }

        foreach (['/usr/bin/node', '/usr/local/bin/node', '/opt/homebrew/bin/node'] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return 'node';
    }

    public function make($value, array $options = []): string
    {
        $process = new Process([$this->nodeBinary(), base_path('scripts/hash-password.mjs'), $value]);
        $process->mustRun();

        return $process->getOutput();
    }

    public function check($value, $hashedValue, array $options = []): bool
    {
        if (!$hashedValue || !str_starts_with($hashedValue, 'scrypt:')) {
            return false;
        }

        $process = new Process([$this->nodeBinary(), base_path('scripts/verify-password.mjs'), $value, $hashedValue]);
        $process->run();

        return trim($process->getOutput()) === 'true';
    }

    public function needsRehash($hashedValue, array $options = []): bool
    {
        return false;
    }
}
