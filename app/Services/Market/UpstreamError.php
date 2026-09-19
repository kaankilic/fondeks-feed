<?php

namespace App\Services\Market;

use RuntimeException;

class UpstreamError extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?string $body = null,
    ) {
        parent::__construct($message);
    }
}
