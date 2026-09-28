<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Faylni qismlab (HTTP Range) uzatish — audio pleyer surish uchun kerak.
 */
final class FileResponse
{
    public function __construct(
        public readonly string $path,
        public readonly string $mime,
        public readonly int $maxAge = 0,
        public readonly ?string $downloadName = null
    ) {
    }

    public function send(): void
    {
        if (!is_file($this->path)) {
            http_response_code(404);
            return;
        }
        $size = (int) filesize($this->path);
        $start = 0;
        $end = $size - 1;
        $status = 200;

        $range = $_SERVER['HTTP_RANGE'] ?? '';
        if ($range !== '' && preg_match('/^bytes=(\d*)-(\d*)$/', trim($range), $m)) {
            if ($m[1] === '' && $m[2] !== '') {
                $start = max(0, $size - (int) $m[2]);
            } else {
                $start = (int) $m[1];
                if ($m[2] !== '') {
                    $end = min($end, (int) $m[2]);
                }
            }
            if ($start > $end || $start >= $size) {
                http_response_code(416);
                header('Content-Range: bytes */' . $size);
                return;
            }
            $status = 206;
        }

        http_response_code($status);
        header('Content-Type: ' . $this->mime);
        header('Accept-Ranges: bytes');
        header('Content-Length: ' . ($end - $start + 1));
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
        header($this->maxAge > 0 ? 'Cache-Control: private, max-age=' . $this->maxAge : 'Cache-Control: private, no-store');
        if ($status === 206) {
            header(sprintf('Content-Range: bytes %d-%d/%d', $start, $end, $size));
        }
        if ($this->downloadName !== null) {
            header('Content-Disposition: attachment; filename="' . rawurlencode($this->downloadName) . '"');
        }

        $fh = fopen($this->path, 'rb');
        if ($fh === false) {
            return;
        }
        fseek($fh, $start);
        $remaining = $end - $start + 1;
        while ($remaining > 0 && !feof($fh)) {
            $chunk = fread($fh, (int) min(65536, $remaining));
            if ($chunk === false) {
                break;
            }
            echo $chunk;
            $remaining -= strlen($chunk);
            flush();
        }
        fclose($fh);
    }
}
