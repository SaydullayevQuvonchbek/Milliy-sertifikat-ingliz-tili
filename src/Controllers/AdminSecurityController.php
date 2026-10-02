<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Audit;
use App\Auth;
use App\Config;
use App\Http\ClientIp;
use App\Http\HttpError;
use App\Http\Request;

/** Kirish bloklari: holatni ko'rish, tozalash va IP aniqlash diagnostikasi (proksi sozlamasini tekshirish uchun). */
final class AdminSecurityController
{
    public static function throttle(Request $r): array
    {
        Auth::require('admin');
        return self::payload($r);
    }

    /**
     * Tozalash: scope = 'all' (hammasi), 'mine' (administratorning o'z IP manzili) yoki 'ip' (+ ip maydoni).
     */
    public static function clear(Request $r): array
    {
        $admin = Auth::require('admin');
        $scope = $r->str('scope', 'all');
        if ($scope === 'all') {
            $ip = null;
        } elseif ($scope === 'mine') {
            $ip = $r->ip;
        } elseif ($scope === 'ip') {
            $ip = ClientIp::normalize($r->str('ip'));
            if ($ip === null) {
                throw new HttpError(422, 'validation', "IP manzil noto'g'ri.");
            }
        } else {
            throw new HttpError(422, 'validation', "Noma'lum amal.");
        }
        $removed = Auth::clearThrottle($ip);
        Audit::log((int) $admin['id'], 'throttle_clear', $ip ?? 'all', ['removed' => $removed]);
        return ['removed' => $removed] + self::payload($r);
    }

    private static function payload(Request $r): array
    {
        $header = (string) Config::get('client_ip_header', '');
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $trusted = ClientIp::expand(Config::get('trusted_proxies') ?? ['private']);
        $headerValue = $header !== '' ? trim((string) ($_SERVER[$header] ?? '')) : '';
        return [
            'throttle' => Auth::throttleStatus($r->ip),
            'ip' => [
                'detected' => $r->ip,
                'remote_addr' => $remote,
                'header' => $header,
                'header_value' => mb_substr($headerValue, 0, 200),
                // Sarlavha haqiqatan ishlatildimi: sozlangan, so'rovda bor va so'rov ishonchli proksidan kelgan.
                'header_used' => $header !== '' && $headerValue !== ''
                    && ClientIp::inAny(ClientIp::normalize($remote) ?? '', $trusted),
            ],
        ];
    }
}
