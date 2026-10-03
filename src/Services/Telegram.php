<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use CURLFile;

/**
 * Telegram Bot API bilan ishlash (faqat yuborish: sendMessage, sendVideo, sendDocument).
 *
 * Sozlama config.php dagi 'telegram' bo'limida: bot_token, chat_id (kanal, masalan -1001234567890),
 * speaking_chat_id (ixtiyoriy, Speaking videolari uchun alohida kanal), api_base (relay manzili; standart
 * https://api.telegram.org) va proxy (masalan socks5h://user:pass@host:1080).
 * Rossiyadagi hostingdan api.telegram.org ga ulanib bo'lmasa, api_base yoki proxy orqali chet eldagi server ishlatiladi.
 */
final class Telegram
{
    public const MAX_UPLOAD = 50 * 1024 * 1024;
    /** Testlar uchun: fn(string $method, array $fields): array{0:int,1:string} — HTTP kodi va javob matni. */
    public static $transport = null;

    /** @return array{token:string, chat:string, speaking_chat:string, api_base:string, proxy:string} */
    public static function settings(): array
    {
        $c = (array) Config::get('telegram', []);
        $base = trim((string) ($c['api_base'] ?? ''));
        return [
            'token' => trim((string) ($c['bot_token'] ?? '')),
            'chat' => trim((string) ($c['chat_id'] ?? '')),
            'speaking_chat' => trim((string) ($c['speaking_chat_id'] ?? '')),
            'api_base' => rtrim($base !== '' ? $base : 'https://api.telegram.org', '/'),
            'proxy' => trim((string) ($c['proxy'] ?? '')),
        ];
    }

    public static function configured(): bool
    {
        $s = self::settings();
        return $s['token'] !== '' && $s['chat'] !== '';
    }

    /** Bo'lim videosi qaysi kanalga ketadi: Speaking uchun alohida kanal ko'rsatilgan bo'lsa — o'sha. */
    public static function chatFor(string $section): string
    {
        $s = self::settings();
        return $section === 'S' && $s['speaking_chat'] !== '' ? $s['speaking_chat'] : $s['chat'];
    }

    /** Admin sahifasi uchun: tokenning faqat bot raqami va oxirgi 4 belgisi. */
    public static function maskedToken(): string
    {
        $token = self::settings()['token'];
        if ($token === '') {
            return '';
        }
        $id = strstr($token, ':', true) ?: '';
        return ($id !== '' ? $id . ':' : '') . '…' . substr($token, -4);
    }

    /**
     * API so'rovi. Fayl yuborilsa, $file = [maydon nomi, yo'l, MIME, fayl nomi].
     * @return mixed natija ('result' maydoni)
     */
    public static function call(string $method, array $fields, ?array $file = null, int $timeout = 60): mixed
    {
        $s = self::settings();
        if ($s['token'] === '') {
            throw new TelegramError(0, 'Telegram bot tokeni sozlanmagan (config.php → telegram.bot_token).');
        }
        if (self::$transport !== null) {
            if ($file !== null) {
                $fields[$file[0]] = ['path' => $file[1], 'mime' => $file[2], 'name' => $file[3]];
            }
            [$status, $body] = (self::$transport)($method, $fields);
        } else {
            [$status, $body] = self::curl($s, $method, $fields, $file, $timeout);
        }
        $data = json_decode((string) $body, true);
        if (!is_array($data)) {
            // Odatda relay yoki proxy xato sahifasini qaytargan (Telegram har doim JSON qaytaradi).
            throw new TelegramError(0, "Telegram javobi tushunarsiz (HTTP {$status}) — api_base yoki proxy manzilini tekshiring.");
        }
        if (($data['ok'] ?? false) !== true) {
            throw new TelegramError(
                (int) ($data['error_code'] ?? $status),
                mb_substr((string) ($data['description'] ?? 'Telegram xatosi'), 0, 250),
                (int) ($data['parameters']['retry_after'] ?? 0)
            );
        }
        return $data['result'] ?? null;
    }

    /** @return array{0:int,1:string} */
    private static function curl(array $s, string $method, array $fields, ?array $file, int $timeout): array
    {
        if (!function_exists('curl_init')) {
            throw new TelegramError(0, "PHP'ning curl kengaytmasi yo'q — hosting panelida yoqing.");
        }
        if ($file !== null) {
            $fields[$file[0]] = new CURLFile($file[1], $file[2], $file[3]);
        }
        $ch = curl_init($s['api_base'] . '/bot' . $s['token'] . '/' . $method);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        if ($s['proxy'] !== '') {
            curl_setopt($ch, CURLOPT_PROXY, $s['proxy']);
        }
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($errno !== 0 || $body === false) {
            // Xato matnida token bo'lmaydi (URL chiqarilmaydi).
            $hint = in_array($errno, [CURLE_OPERATION_TIMEDOUT, CURLE_COULDNT_CONNECT], true)
                ? ' Hosting Telegram\'ni bloklagan bo\'lishi mumkin — config.php da api_base (relay) yoki proxy ko\'rsating.'
                : '';
            throw new TelegramError(0, "Telegram'ga ulanib bo'lmadi: {$error}." . $hint);
        }
        return [$status, (string) $body];
    }

    public static function sendMessage(string $chat, string $text): int
    {
        $result = self::call('sendMessage', ['chat_id' => $chat, 'text' => $text, 'disable_web_page_preview' => 'true'], null, 30);
        return (int) ($result['message_id'] ?? 0);
    }

    /**
     * Video yuborish: MP4 (H.264) — sendVideo (Telegram ichida ko'rinadi), boshqasi (WebM, VP9) — sendDocument.
     * @return int message_id
     */
    public static function sendVideoFile(string $chat, string $path, string $mime, bool $asVideo, string $name, string $caption, int $durationSec = 0, ?int $width = null, ?int $height = null): int
    {
        $fields = ['chat_id' => $chat, 'caption' => mb_substr($caption, 0, 1000)];
        if ($asVideo) {
            $fields['supports_streaming'] = 'true';
            if ($durationSec > 0) {
                $fields['duration'] = (string) $durationSec;
            }
            if ($width && $height) {
                $fields['width'] = (string) $width;
                $fields['height'] = (string) $height;
            }
            $result = self::call('sendVideo', $fields, ['video', $path, $mime, $name], 300);
        } else {
            $result = self::call('sendDocument', $fields, ['document', $path, $mime, $name], 300);
        }
        return (int) ($result['message_id'] ?? 0);
    }

    /** Kanal xabariga havola (yopiq kanal: t.me/c/<id>/<xabar>; ochiq kanal: @nom bo'lsa t.me/<nom>/<xabar>). */
    public static function messageLink(?string $chat, ?int $messageId): ?string
    {
        if ($chat === null || $chat === '' || !$messageId) {
            return null;
        }
        if (str_starts_with($chat, '@')) {
            return 'https://t.me/' . substr($chat, 1) . '/' . $messageId;
        }
        if (str_starts_with($chat, '-100')) {
            return 'https://t.me/c/' . substr($chat, 4) . '/' . $messageId;
        }
        return null;
    }
}
