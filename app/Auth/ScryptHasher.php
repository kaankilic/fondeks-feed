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

    public function make($value, array $options = []): string
    {
        $process = new Process(['node', base_path('scripts/hash-password.mjs'), $value]);
        $process->mustRun();

        return $process->getOutput();
    }

    public function check($value, $hashedValue, array $options = []): bool
    {
        if (!$hashedValue || !str_starts_with($hashedValue, 'scrypt:')) {
            return false;
        }

        $process = new Process(['node', base_path('scripts/verify-password.mjs'), $value, $hashedValue]);
        $process->run();

        return trim($process->getOutput()) === 'true';
    }

    public function needsRehash($hashedValue, array $options = []): bool
    {
        return false;
    }
}
