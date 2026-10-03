<?php

declare(strict_types=1);

namespace App;

final class Installer
{
    /** Sxema o'zgarganda oshiriladi: yangi versiya yuklanganda yetishmayotgan jadvallar o'zi yaratiladi. */
    public const SCHEMA_VERSION = 2;

    /**
     * Yangi versiyani serverga yuklagandan keyin buyruq qatorisiz ishlashi uchun: birinchi so'rovda jadvallar
     * (CREATE TABLE IF NOT EXISTS — mavjudlariga tegilmaydi) yaratiladi va belgi fayli yoziladi.
     */
    public static function ensureSchema(): void
    {
        $flag = Config::storagePath('schema.v' . self::SCHEMA_VERSION);
        if (is_file($flag)) {
            return;
        }
        self::install();
        @file_put_contents($flag, date('c') . "\n");
    }

    public static function install(): void
    {
        $file = APP_ROOT . '/database/schema.' . (Db::driver() === 'mysql' ? 'mysql' : 'sqlite') . '.sql';
        $sql = (string) file_get_contents($file);
        // Izohlarni olib tashlab, buyruqlarni ';' bo'yicha ajratamiz.
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? '';
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            Db::pdo()->exec($statement);
        }
        foreach (['uploads', 'uploads/audio', 'uploads/images', 'uploads/speaking'] as $dir) {
            $path = Config::storagePath($dir);
            if (!is_dir($path)) {
                mkdir($path, 0775, true);
            }
        }
    }

    public static function createUser(string $role, string $fullName, string $login, string $password, ?string $phone = null): int
    {
        return Db::insert('users', [
            'role' => $role,
            'full_name' => $fullName,
            'login' => Util::normalizeLogin($login),
            'phone' => $phone,
            'password_hash' => Auth::hash($password),
            'status' => 'active',
            'created_at' => time(),
        ]);
    }
}
