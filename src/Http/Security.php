<?php

declare(strict_types=1);

namespace App\Http;

/**
 * HTML sahifalar uchun xavfsizlik sarlavhalari. Apache'da ularni public/.htaccess qo'yadi (matni shu yerdagi
 * bilan bir xil bo'lishi testda tekshiriladi); lokal serverda (bin/dev-router.php) shu sinf qo'yadi —
 * shuning uchun E2E testlar CSP buzilishlarini ham ushlaydi.
 */
final class Security
{
    /**
     * Sahifadagi barcha kod va resurslar o'z domenidan: ichki skript yo'q, tashqi manba yo'q.
     * blob: — mikrofon sinov yozuvini eshitish va yuklab olingan audio uchun.
     */
    public const CSP = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; "
        . "media-src 'self' blob:; connect-src 'self'; font-src 'self'; object-src 'none'; base-uri 'none'; "
        . "form-action 'self'; frame-ancestors 'none'";

    /** @return array<string,string> */
    public static function htmlHeaders(): array
    {
        return [
            'Content-Security-Policy' => self::CSP,
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
            'Permissions-Policy' => 'camera=(), geolocation=(), microphone=(self), fullscreen=(self)',
        ];
    }
}
