<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use App\Http\HttpError;
use finfo;

/** Fayllarni xavfsiz qabul qilish va saqlash (veb-ildizdan tashqarida). */
final class Uploads
{
    /** Aniqlangan MIME → [kengaytma, uzatishdagi Content-Type]. */
    private const AUDIO = [
        'audio/mpeg' => ['mp3', 'audio/mpeg'],
        'audio/mp3' => ['mp3', 'audio/mpeg'],
        'audio/mp4' => ['m4a', 'audio/mp4'],
        'audio/x-m4a' => ['m4a', 'audio/mp4'],
        'audio/m4a' => ['m4a', 'audio/mp4'],
        'video/mp4' => ['m4a', 'audio/mp4'],
        'audio/aac' => ['aac', 'audio/aac'],
        'audio/x-hx-aac-adts' => ['aac', 'audio/aac'],
        'audio/wav' => ['wav', 'audio/wav'],
        'audio/x-wav' => ['wav', 'audio/wav'],
        'audio/wave' => ['wav', 'audio/wav'],
        'audio/ogg' => ['ogg', 'audio/ogg'],
        'application/ogg' => ['ogg', 'audio/ogg'],
        'audio/webm' => ['webm', 'audio/webm'],
        'video/webm' => ['webm', 'audio/webm'],
    ];

    private const IMAGE = [
        'image/png' => ['png', 'image/png'],
        'image/jpeg' => ['jpg', 'image/jpeg'],
        'image/webp' => ['webp', 'image/webp'],
        'image/gif' => ['gif', 'image/gif'],
    ];

    private const LIMITS = ['audio' => 60 * 1024 * 1024, 'image' => 8 * 1024 * 1024, 'speaking' => 15 * 1024 * 1024];

    public static function assetPath(string $kind, string $file): string
    {
        return Config::storagePath(($kind === 'audio' ? 'uploads/audio/' : 'uploads/images/') . basename($file));
    }

    public static function speakingPath(string $file): string
    {
        return Config::storagePath('uploads/speaking/' . basename($file));
    }

    /** @return array{file:string,mime:string,size:int,original_name:string} */
    public static function storeAsset(array $file, int $mockId, string $kind): array
    {
        $map = $kind === 'audio' ? self::AUDIO : self::IMAGE;
        [$tmp, $size] = self::check($file, self::LIMITS[$kind]);
        [$ext, $mime] = self::detect($tmp, $map, $kind === 'audio' ? 'Audio' : 'Rasm');
        $name = sprintf('m%d_%s.%s', $mockId, bin2hex(random_bytes(10)), $ext);
        self::move($tmp, self::assetPath($kind, $name));
        return [
            'file' => $name,
            'mime' => $mime,
            'size' => $size,
            'original_name' => mb_substr(basename((string) ($file['name'] ?? '')), 0, 200),
        ];
    }

    /** @return array{file:string,mime:string,size:int} */
    public static function storeSpeaking(array $file, int $attemptId, int $qNo): array
    {
        [$tmp, $size] = self::check($file, self::LIMITS['speaking']);
        [$ext, $mime] = self::detect($tmp, self::AUDIO, 'Audio');
        $name = sprintf('a%d_q%d_%s.%s', $attemptId, $qNo, bin2hex(random_bytes(8)), $ext);
        self::move($tmp, self::speakingPath($name));
        return ['file' => $name, 'mime' => $mime, 'size' => $size];
    }

    /** @return array{0:string,1:int} */
    private static function check(array $file, int $limit): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new HttpError(413, 'too_large', 'Fayl hajmi server ruxsat bergan chegaradan katta.');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new HttpError(422, 'upload_failed', 'Fayl yuklanmadi. Qayta urinib ko\'ring.');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($tmp === '' || !is_file($tmp)) {
            throw new HttpError(422, 'upload_failed', 'Fayl yuklanmadi. Qayta urinib ko\'ring.');
        }
        if ($size <= 0) {
            throw new HttpError(422, 'empty_file', "Fayl bo'sh.");
        }
        if ($size > $limit) {
            throw new HttpError(413, 'too_large', 'Fayl hajmi ' . round($limit / 1048576) . ' MB dan oshmasligi kerak.');
        }
        return [$tmp, $size];
    }

    /** @return array{0:string,1:string} */
    private static function detect(string $tmp, array $map, string $label): array
    {
        $detected = (string) (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!isset($map[$detected])) {
            throw new HttpError(415, 'bad_type', "{$label} formati qo'llab-quvvatlanmaydi ({$detected}).");
        }
        return $map[$detected];
    }

    private static function move(string $tmp, string $dest): void
    {
        $dir = dirname($dest);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $ok = is_uploaded_file($tmp) ? move_uploaded_file($tmp, $dest) : (getenv('MOCK_TESTING') === '1' && copy($tmp, $dest));
        if (!$ok) {
            throw new HttpError(500, 'store_failed', 'Faylni saqlab bo\'lmadi.');
        }
    }
}
