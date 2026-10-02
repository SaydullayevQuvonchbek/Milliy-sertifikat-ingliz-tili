<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Mijozning haqiqiy IP manzilini aniqlash.
 *
 * Standart holatda faqat REMOTE_ADDR ishlatiladi. Sayt teskari proksi (hosting nginx'i, Cloudflare) ortida bo'lsa,
 * config'da `client_ip_header` (masalan 'HTTP_X_FORWARDED_FOR') va `trusted_proxies` beriladi.
 *
 * Sarlavha FAQAT so'rov ishonchli proksidan kelgan bo'lsa o'qiladi — aks holda istalgan mijoz sarlavhani o'zi yozib,
 * har so'rovda boshqa "IP" bilan kirish cheklovlarini chetlab o'tardi. X-Forwarded-For zanjiri o'ngdan chapga
 * o'qiladi: har bir proksi o'zidan oldingi manzilni oxiriga qo'shadi, mijoz esa faqat chap qismini soxtalashtira oladi.
 * Ishonchli proksilarni tashlab ketgandan keyingi birinchi manzil — haqiqiy mijoz.
 *
 * Cheklov: mijozning o'zi ishonchli tarmoqda bo'lsa (masalan, 'private' va o'quvchilar ham ichki tarmoqda), zanjirda
 * undan chapdagi — mijoz yozgan — qiymat olinadi. Bunday joylashuvda trusted_proxies ga faqat proksi manzili yoziladi.
 */
final class ClientIp
{
    /** Shu host va ichki tarmoq: hosting nginx'i yoki lokal proksi odatda shu manzillardan ulanadi. */
    public const PRIVATE_RANGES = [
        '127.0.0.0/8', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '169.254.0.0/16',
        '::1/128', 'fc00::/7', 'fe80::/10',
    ];

    /** Cloudflare manzillari (https://www.cloudflare.com/ips/, 2026-10-02 da tekshirilgan). */
    public const CLOUDFLARE_RANGES = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
        '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
        '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
        '2a06:98c0::/29', '2c0f:f248::/32',
    ];

    /**
     * @param array<string,mixed> $server  odatda $_SERVER
     * @param string $header               masalan 'HTTP_X_FORWARDED_FOR'; '' — sarlavha ishlatilmaydi
     * @param list<string>|string $trusted IP, CIDR yoki 'private' / 'cloudflare' kalit so'zlari
     */
    public static function resolve(array $server, string $header, array|string $trusted): string
    {
        $remote = self::normalize((string) ($server['REMOTE_ADDR'] ?? '')) ?? '0.0.0.0';
        if ($header === '' || trim((string) ($server[$header] ?? '')) === '') {
            return $remote;
        }
        $ranges = self::expand($trusted);
        if (!self::inAny($remote, $ranges)) {
            // So'rov proksidan emas, to'g'ridan-to'g'ri kelgan: sarlavhani mijozning o'zi yozgan bo'lishi mumkin.
            return $remote;
        }
        $chain = explode(',', (string) $server[$header]);
        $candidate = $remote;
        for ($i = count($chain) - 1; $i >= 0; $i--) {
            $ip = self::normalize($chain[$i]);
            if ($ip === null) {
                break; // buzilgan zanjir: bundan chapdagi qiymatlarga ishonib bo'lmaydi
            }
            $candidate = $ip;
            if (!self::inAny($ip, $ranges)) {
                return $ip;
            }
        }
        // Zanjirdagi hamma manzil ishonchli tarmoqda (masalan, markazning ichki tarmog'idagi kompyuter).
        return $candidate;
    }

    /**
     * Kalit so'zlarni ochib, tekis ro'yxat qaytaradi.
     *
     * @param list<string>|string $trusted
     * @return list<string>
     */
    public static function expand(array|string $trusted): array
    {
        $out = [];
        foreach ((array) $trusted as $entry) {
            $entry = strtolower(trim((string) $entry));
            if ($entry === 'private') {
                array_push($out, ...self::PRIVATE_RANGES);
            } elseif ($entry === 'cloudflare') {
                array_push($out, ...self::CLOUDFLARE_RANGES);
            } elseif ($entry !== '') {
                $out[] = $entry;
            }
        }
        return $out;
    }

    /** @param list<string> $ranges */
    public static function inAny(string $ip, array $ranges): bool
    {
        foreach ($ranges as $range) {
            if (self::inRange($ip, $range)) {
                return true;
            }
        }
        return false;
    }

    /** IP berilgan manzil yoki CIDR oralig'ida (IPv4 va IPv6). */
    public static function inRange(string $ip, string $range): bool
    {
        [$net, $bits] = str_contains($range, '/') ? explode('/', $range, 2) : [$range, null];
        $ipBin = @inet_pton($ip);
        $netBin = @inet_pton(trim($net));
        if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) {
            return false;
        }
        $max = strlen($ipBin) * 8;
        if ($bits === null) {
            $bits = $max;
        } elseif (!ctype_digit(trim($bits))) {
            return false;
        } else {
            $bits = (int) $bits;
        }
        if ($bits < 0 || $bits > $max) {
            return false;
        }
        $bytes = intdiv($bits, 8);
        if (strncmp($ipBin, $netBin, $bytes) !== 0) {
            return false;
        }
        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $rest)) & 0xFF;
        return (ord($ipBin[$bytes]) & $mask) === (ord($netBin[$bytes]) & $mask);
    }

    /**
     * Sarlavhadagi bitta qiymatni toza IP ga keltiradi: bo'shliq va qo'shtirnoqlar, "[IPv6]:port" va "IPv4:port",
     * "::ffff:1.2.3.4" (IPv4 IPv6 ichida). IP bo'lmasa null.
     */
    public static function normalize(string $value): ?string
    {
        $value = trim($value, " \t\"'");
        if (preg_match('/^\[([0-9A-Fa-f:.]+)\](?::\d+)?$/', $value, $m)) {
            $value = $m[1];
        } elseif (preg_match('/^(\d{1,3}(?:\.\d{1,3}){3}):\d+$/', $value, $m)) {
            $value = $m[1];
        }
        if (filter_var($value, FILTER_VALIDATE_IP) === false) {
            return null;
        }
        if (stripos($value, '::ffff:') === 0 && filter_var(substr($value, 7), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return substr($value, 7);
        }
        return strtolower($value);
    }
}
