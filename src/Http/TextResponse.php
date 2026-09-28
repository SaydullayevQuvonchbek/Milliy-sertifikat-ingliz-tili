<?php

declare(strict_types=1);

namespace App\Http;

/** Matnli javob (masalan, CSV eksport). */
final class TextResponse
{
    public function __construct(
        public readonly string $content,
        public readonly string $mime,
        public readonly ?string $downloadName = null
    ) {
    }

    public function send(): void
    {
        header('Content-Type: ' . $this->mime);
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        if ($this->downloadName !== null) {
            header('Content-Disposition: attachment; filename="' . rawurlencode($this->downloadName) . '"');
        }
        echo $this->content;
    }
}
