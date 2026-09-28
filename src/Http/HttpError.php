<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

final class HttpError extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $extra = []
    ) {
        parent::__construct($message);
    }
}
