<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    public const MAX_JSON_BYTES = 6 * 1024 * 1024;

    public array $params = [];

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $body,
        public readonly array $files,
        public readonly array $headers,
        public readonly string $ip
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $route = (string) ($_GET['route'] ?? '');
        $path = '/' . trim($route, '/');

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = (string) $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }

        $body = [];
        $contentType = $headers['content-type'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
            if ($length > self::MAX_JSON_BYTES) {
                throw new HttpError(413, 'too_large', "So'rov hajmi juda katta.");
            }
            $raw = (string) file_get_contents('php://input');
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (!is_array($decoded)) {
                    throw new HttpError(400, 'bad_json', "So'rov formati noto'g'ri.");
                }
                $body = $decoded;
            }
        } else {
            $body = $_POST;
        }

        $query = $_GET;
        unset($query['route']);

        return new self(
            $method,
            $path,
            $query,
            $body,
            $_FILES,
            $headers,
            self::clientIp()
        );
    }

    /**
     * Mijoz IP manzili. Sayt teskari proksi (Cloudflare, Nginx) ortida bo'lsa, config'da `client_ip_header`
     * ("HTTP_CF_CONNECTING_IP" yoki "HTTP_X_FORWARDED_FOR") ko'rsatiladi — aks holda hamma o'quvchi bitta IP bo'lib ko'rinadi.
     * Sarlavha faqat proksi ishonchli bo'lsa yoqiladi: aks holda mijoz uni soxtalashtira oladi.
     */
    private static function clientIp(): string
    {
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $header = (string) \App\Config::get('client_ip_header', '');
        if ($header !== '' && !empty($_SERVER[$header])) {
            $first = trim(explode(',', (string) $_SERVER[$header])[0]);
            if (filter_var($first, FILTER_VALIDATE_IP) !== false) {
                return $first;
            }
        }
        return $remote;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function str(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);
        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function param(string $name): string
    {
        return (string) ($this->params[$name] ?? '');
    }

    public function userAgent(): string
    {
        return mb_substr((string) ($this->headers['user-agent'] ?? ''), 0, 300);
    }
}
