<?php

declare(strict_types=1);

namespace App;

final class Util
{
    /** Testlar uchun soxta soat (millisoniya). Ishlab chiqarishda doim null. */
    public static ?int $testNowMs = null;

    public static function nowMs(): int
    {
        return self::$testNowMs ?? (int) floor(microtime(true) * 1000);
    }

    public static function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public static function decode(?string $json, array $default = []): array
    {
        if ($json === null || $json === '') {
            return $default;
        }
        $value = json_decode($json, true);
        return is_array($value) ? $value : $default;
    }

    /** Tasodifiy kod: chalkashtiradigan belgilarsiz (0/O, 1/I). */
    public static function randomCode(int $length = 6): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $max = strlen($alphabet) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }
        return $out;
    }

    public static function randomPassword(int $digits = 6): string
    {
        $out = '';
        for ($i = 0; $i < $digits; $i++) {
            $out .= (string) random_int(0, 9);
        }
        return $out;
    }

    /**
     * Telefon raqamini +998XXXXXXXXX ko'rinishiga keltiradi.
     * Noto'g'ri raqam bo'lsa null qaytaradi.
     */
    public static function normalizePhone(string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', $input) ?? '';
        if (strlen($digits) === 9) {
            $digits = '998' . $digits;
        }
        if (strlen($digits) !== 12 || !str_starts_with($digits, '998')) {
            return null;
        }
        return '+' . $digits;
    }

    /** Kirish nomi: telefon bo'lsa normallashtiriladi, aks holda kichik harflarga o'tkaziladi. */
    public static function normalizeLogin(string $input): string
    {
        $input = trim($input);
        if (preg_match('/^[\d\s()+\-]{9,}$/', $input)) {
            $phone = self::normalizePhone($input);
            if ($phone !== null) {
                return $phone;
            }
        }
        return mb_strtolower($input);
    }

    public static function cleanText(mixed $value, int $max = 1000): string
    {
        if (!is_scalar($value)) {
            return '';
        }
        $text = (string) $value;
        // Boshqaruv belgilarini olib tashlash (yangi qator va tab bundan mustasno).
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
        return mb_substr($text, 0, $max);
    }

    /** Inglizcha matndagi so'zlar soni (frontend'dagi hisoblagich bilan bir xil qoida). */
    public static function wordCount(string $text): int
    {
        $tokens = preg_split('/\s+/u', trim($text)) ?: [];
        $count = 0;
        foreach ($tokens as $token) {
            if ($token !== '' && preg_match('/[\p{L}\p{N}]/u', $token)) {
                $count++;
            }
        }
        return $count;
    }
}
