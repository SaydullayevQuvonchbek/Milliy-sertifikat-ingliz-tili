<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

/**
 * Zaxira nusxa yordamchilari (bin/backup.php ishlatadi).
 *
 * Nomlar: to'liq nusxa — "backup-<YYYYMMDD-HHMMSS>[-N].zip", faqat baza — "backup-db-<...>.zip". Har tur o'zicha
 * saqlanadi: bir turning nusxalari ikkinchisini o'chirib yubormaydi.
 */
final class Backup
{
    public const PREFIX_FULL = 'backup-';
    public const PREFIX_DB = 'backup-db-';

    /** Yangi nusxa yo'li. Bir soniyada bir nechta bo'lsa, tartib raqami oshib boradi (o'chirilgani qayta olinmaydi). */
    public static function nextPath(string $dir, string $prefix, string $stamp): string
    {
        $dir = rtrim($dir, '/\\');
        $next = 1;
        foreach (self::listOwn($dir, $prefix) as [$fileStamp, $n]) {
            if ($fileStamp === $stamp) {
                $next = max($next, $n + 1);
            }
        }
        return $next > 1 ? "{$dir}/{$prefix}{$stamp}-{$next}.zip" : "{$dir}/{$prefix}{$stamp}.zip";
    }

    /**
     * Shu turdagi eng yangi $keep tasidan boshqasini o'chiradi. Faqat shu skript nomlagan fayllarga tegadi.
     *
     * @return list<string> o'chirilgan fayl nomlari
     */
    public static function prune(string $dir, string $prefix, int $keep): array
    {
        $dir = rtrim($dir, '/\\');
        $own = [];
        foreach (self::listOwn($dir, $prefix) as $path => [$stamp, $n]) {
            $own[$stamp . sprintf('-%06d', $n)] = $path;
        }
        krsort($own, SORT_STRING);
        $deleted = [];
        foreach (array_slice($own, max(1, $keep)) as $path) {
            if (@unlink($path)) {
                $deleted[] = basename($path);
            }
        }
        return $deleted;
    }

    /** @return array<string, array{0: string, 1: int}> yo'l => [sana, tartib raqami] */
    private static function listOwn(string $dir, string $prefix): array
    {
        $pattern = '/^' . preg_quote($prefix, '/') . '(\d{8}-\d{6})(?:-(\d+))?\.zip$/';
        $out = [];
        foreach (glob($dir . '/' . $prefix . '*.zip') ?: [] as $path) {
            if (preg_match($pattern, basename($path), $m)) {
                $out[$path] = [$m[1], (int) ($m[2] ?? 1)];
            }
        }
        return $out;
    }

    /** Dasturni PATH va odatiy papkalardan qidirish (cron'da PATH qisqa bo'ladi). */
    public static function findBinary(array $names): ?string
    {
        $dirs = array_merge(
            explode(PATH_SEPARATOR, (string) getenv('PATH')),
            ['/usr/bin', '/usr/local/bin', '/usr/local/mysql/bin', '/opt/homebrew/bin', '/opt/local/bin']
        );
        foreach ($names as $name) {
            foreach (array_unique(array_filter($dirs)) as $dir) {
                $path = rtrim($dir, '/') . '/' . $name;
                if (@is_file($path) && @is_executable($path)) {
                    return $path;
                }
            }
        }
        return null;
    }

    /** mysqldump bilan dump. Muvaffaqiyatsiz bo'lsa — sababini qaytaradi (null — hammasi joyida). */
    public static function mysqldump(string $binary, array $m, string $file): ?string
    {
        if (!function_exists('exec')) {
            return "exec() funksiyasi hostingda o'chirilgan";
        }
        $errFile = $file . '.err';
        // --no-tablespaces: MySQL 8 da PROCESS huquqi bo'lmagan (shared hosting) foydalanuvchi uchun shart.
        $cmd = sprintf(
            '%s --single-transaction --no-tablespaces --default-character-set=utf8mb4 -h %s -P %d -u %s %s > %s 2> %s',
            escapeshellarg($binary),
            escapeshellarg((string) ($m['host'] ?? 'localhost')),
            (int) ($m['port'] ?? 3306),
            escapeshellarg((string) ($m['username'] ?? '')),
            escapeshellarg((string) ($m['database'] ?? '')),
            escapeshellarg($file),
            escapeshellarg($errFile)
        );
        putenv('MYSQL_PWD=' . (string) ($m['password'] ?? ''));
        exec($cmd, $unused, $code);
        putenv('MYSQL_PWD');
        $err = trim((string) @file_get_contents($errFile));
        @unlink($errFile);
        if ($code !== 0 || !is_file($file) || filesize($file) === 0) {
            @unlink($file);
            return "chiqish kodi {$code}" . ($err !== '' ? ': ' . mb_substr($err, 0, 300) : '');
        }
        return null;
    }

    /**
     * MySQL/MariaDB bazasini PDO orqali SQL faylga yozadi: har jadval uchun DROP + CREATE va INSERT lar.
     * Bitta izchil tranzaksiyada o'qiladi (mysqldump --single-transaction kabi); qatorlar oqim bilan yoziladi.
     * Qaytaradi: yozilgan qatorlar soni.
     */
    public static function phpDump(PDO $pdo, string $file): int
    {
        $out = fopen($file, 'wb');
        if ($out === false) {
            throw new RuntimeException("Faylga yozib bo'lmadi: {$file}");
        }
        $buffered = defined('Pdo\Mysql::ATTR_USE_BUFFERED_QUERY')
            ? constant('Pdo\Mysql::ATTR_USE_BUFFERED_QUERY')
            : PDO::MYSQL_ATTR_USE_BUFFERED_QUERY;
        $rowsTotal = 0;
        $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
        try {
            fwrite($out, '-- Multilevel Mock: baza zaxirasi (PHP dump), ' . date('Y-m-d H:i:s') . "\n"
                . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET UNIQUE_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");
            $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
            foreach ($tables as $row) {
                $q = self::ident((string) $row[0]);
                $create = (string) $pdo->query("SHOW CREATE TABLE {$q}")->fetch(PDO::FETCH_NUM)[1];
                fwrite($out, "DROP TABLE IF EXISTS {$q};\n{$create};\n\n");
                $pdo->setAttribute($buffered, false);
                try {
                    $rowsTotal += self::dumpRows($pdo, $q, $out);
                } finally {
                    $pdo->setAttribute($buffered, true);
                }
                fwrite($out, "\n");
            }
            fwrite($out, "SET FOREIGN_KEY_CHECKS = 1;\nSET UNIQUE_CHECKS = 1;\n");
        } finally {
            $pdo->exec('COMMIT');
            fclose($out);
        }
        return $rowsTotal;
    }

    /** @param resource $out */
    private static function dumpRows(PDO $pdo, string $table, $out): int
    {
        $stmt = $pdo->query("SELECT * FROM {$table}");
        $columns = null;
        $batch = [];
        $size = 0;
        $count = 0;
        $flush = static function () use (&$batch, &$size, &$columns, $out, $table): void {
            if ($batch !== []) {
                fwrite($out, "INSERT INTO {$table} ({$columns}) VALUES\n" . implode(",\n", $batch) . ";\n");
                $batch = [];
                $size = 0;
            }
        };
        while (($r = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $columns ??= implode(', ', array_map(static fn ($c): string => self::ident((string) $c), array_keys($r)));
            $values = [];
            foreach ($r as $v) {
                // Satrlar PDO::quote bilan: yangi qator va qo'shtirnoqlar ekranlanadi, shuning uchun fayldagi har
                // buyruq ";\n" bilan tugaydi. Kasr sonlar to'liq aniqlikda (json_encode — eng qisqa aniq yozuv).
                $values[] = match (true) {
                    $v === null => 'NULL',
                    is_int($v) => (string) $v,
                    is_float($v) => (string) json_encode($v),
                    default => $pdo->quote((string) $v),
                };
            }
            $line = '(' . implode(', ', $values) . ')';
            $batch[] = $line;
            $size += strlen($line);
            $count++;
            if (count($batch) >= 200 || $size > 512 * 1024) {
                $flush();
            }
        }
        $flush();
        $stmt->closeCursor();
        return $count;
    }

    private static function ident(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }
}
