<?php

declare(strict_types=1);

namespace App;

/** Umumiy (platforma darajasidagi) sozlamalar. */
final class Settings
{
    public const DEFAULTS = [
        'site_name' => 'Multilevel Mock',
        'registration_open' => true,
        // Bitta o'quvchi bitta mockni ko'pi bilan necha marta ishlashi mumkin (qat'iy yuqori chegara).
        'max_attempts_cap' => 2,
        // Video nazorat (ekran + kamera): video sifati (kbit/s) va bitta faylning uzunligi (daqiqa).
        // 250 kbit/s ≈ 1.9 MB/daqiqa; 10 daqiqalik fayl ≈ 19 MB (Telegram cheklovi — 50 MB).
        'rec_video_kbps' => 250,
        'rec_segment_min' => 10,
        // Telegram'ga yuborilmagan yozma qism videolari necha kundan keyin serverdan o'chiriladi (0 — o'chirilmaydi).
        'rec_keep_days' => 30,
        // Speaking videolari serverda necha kun saqlanadi (0 — doim).
        'rec_speaking_keep_days' => 0,
        // Serverdagi barcha video yozuvlar uchun joy (MB, 0 — cheklanmagan). To'lsa, yangi yozuv qabul qilinmaydi
        // (imtihon videosiz davom etadi) — hosting diski to'lib sayt ishdan chiqmasligi uchun.
        'rec_max_disk_mb' => 10240,
    ];

    /** @var array<string, array{0:int,1:int}> Sonli sozlamalarning chegaralari. */
    public const LIMITS = [
        'rec_video_kbps' => [100, 2000],
        'rec_segment_min' => [2, 20],
        'rec_keep_days' => [0, 3650],
        'rec_speaking_keep_days' => [0, 3650],
        'rec_max_disk_mb' => [0, 10_000_000],
    ];

    public static function int(string $name): int
    {
        [$min, $max] = self::LIMITS[$name] ?? [PHP_INT_MIN, PHP_INT_MAX];
        return max($min, min($max, (int) self::get($name)));
    }

    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            $values = self::DEFAULTS;
            foreach (Db::all('SELECT name, value FROM settings') as $row) {
                if (array_key_exists($row['name'], self::DEFAULTS)) {
                    $values[$row['name']] = json_decode((string) $row['value'], true);
                }
            }
            self::$cache = $values;
        }
        return self::$cache;
    }

    public static function get(string $name): mixed
    {
        return self::all()[$name] ?? null;
    }

    public static function set(string $name, mixed $value): void
    {
        if (!array_key_exists($name, self::DEFAULTS)) {
            return;
        }
        $encoded = Util::json($value);
        Db::tx(static function () use ($name, $encoded): void {
            if (Db::val('SELECT COUNT(*) FROM settings WHERE name = ?', [$name])) {
                Db::exec('UPDATE settings SET value = ? WHERE name = ?', [$encoded, $name]);
            } else {
                Db::insert('settings', ['name' => $name, 'value' => $encoded]);
            }
        });
        self::$cache = null;
    }

    public static function reset(): void
    {
        self::$cache = null;
    }

    public static function attemptsCap(): int
    {
        return max(1, min(2, (int) self::get('max_attempts_cap')));
    }
}
