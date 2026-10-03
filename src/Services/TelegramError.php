<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/** Telegram Bot API xatosi (error_code, description, retry_after). */
final class TelegramError extends RuntimeException
{
    public function __construct(public readonly int $apiCode, string $message, public readonly int $retryAfter = 0)
    {
        parent::__construct($message);
    }
}
