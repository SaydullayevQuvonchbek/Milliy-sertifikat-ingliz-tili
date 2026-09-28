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
    ];

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
