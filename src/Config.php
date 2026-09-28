<?php

declare(strict_types=1);

namespace App;

final class Config
{
    private static array $data = [];

    public static function load(array $data): void
    {
        self::$data = $data;
    }

    public static function set(string $key, mixed $value): void
    {
        $parts = explode('.', $key);
        $ref = &self::$data;
        foreach ($parts as $part) {
            if (!isset($ref[$part]) || !is_array($ref[$part])) {
                $ref[$part] = [];
            }
            $ref = &$ref[$part];
        }
        $ref = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $current = self::$data;
        foreach (explode('.', $key) as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return $default;
            }
            $current = $current[$part];
        }
        return $current;
    }

    public static function storagePath(string $sub = ''): string
    {
        $base = rtrim((string) self::get('storage_path', APP_ROOT . '/storage'), '/');
        return $sub === '' ? $base : $base . '/' . ltrim($sub, '/');
    }
}
