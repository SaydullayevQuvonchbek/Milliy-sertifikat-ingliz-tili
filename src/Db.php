<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;
use Throwable;

final class Db
{
    private static ?PDO $pdo = null;
    private static string $driver = 'sqlite';
    private static int $depth = 0;

    public static function connect(?array $cfg = null): PDO
    {
        $cfg ??= (array) Config::get('db', []);
        $driver = (string) ($cfg['driver'] ?? 'sqlite');
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if ($driver === 'mysql') {
            $m = (array) ($cfg['mysql'] ?? []);
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $m['host'] ?? 'localhost',
                (int) ($m['port'] ?? 3306),
                $m['database'] ?? '',
                $m['charset'] ?? 'utf8mb4'
            );
            $pdo = new PDO($dsn, (string) ($m['username'] ?? ''), (string) ($m['password'] ?? ''), $options);
        } else {
            $driver = 'sqlite';
            $path = (string) ($cfg['sqlite_path'] ?? APP_ROOT . '/storage/database.sqlite');
            if ($path !== ':memory:') {
                $dir = dirname($path);
                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }
            }
            $pdo = new PDO('sqlite:' . $path, null, null, $options);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA busy_timeout = 10000');
            if ($path !== ':memory:') {
                $pdo->exec('PRAGMA journal_mode = WAL');
                $pdo->exec('PRAGMA synchronous = NORMAL');
            }
        }

        self::$pdo = $pdo;
        self::$driver = $driver;
        self::$depth = 0;
        return $pdo;
    }

    public static function pdo(): PDO
    {
        return self::$pdo ?? self::connect();
    }

    public static function driver(): string
    {
        self::pdo();
        return self::$driver;
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function all(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function val(string $sql, array $params = []): mixed
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return $value === false ? null : $value;
    }

    public static function exec(string $sql, array $params = []): int
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $cols),
            implode(', ', array_fill(0, count($cols), '?'))
        );
        self::exec($sql, array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $params = []): int
    {
        if ($data === []) {
            return 0;
        }
        $sets = implode(', ', array_map(static fn (string $c): string => $c . ' = ?', array_keys($data)));
        return self::exec("UPDATE {$table} SET {$sets} WHERE {$where}", [...array_values($data), ...$params]);
    }

    /**
     * Tranzaksiya. SQLite'da BEGIN IMMEDIATE ishlatiladi — yozish qulfi darhol olinadi,
     * shuning uchun parallel so'rovlar bir-birining ustiga yozmaydi.
     */
    public static function tx(callable $fn): mixed
    {
        $pdo = self::pdo();
        if (self::$depth > 0) {
            self::$depth++;
            try {
                return $fn();
            } finally {
                self::$depth--;
            }
        }

        if (self::$driver === 'sqlite') {
            $pdo->exec('BEGIN IMMEDIATE');
        } else {
            $pdo->beginTransaction();
        }
        self::$depth = 1;

        try {
            $result = $fn();
            if (self::$driver === 'sqlite') {
                $pdo->exec('COMMIT');
            } else {
                $pdo->commit();
            }
            return $result;
        } catch (Throwable $e) {
            try {
                if (self::$driver === 'sqlite') {
                    $pdo->exec('ROLLBACK');
                } elseif ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (Throwable) {
                // Asl xatolik muhimroq.
            }
            throw $e;
        } finally {
            self::$depth = 0;
        }
    }

    /** MySQL'da qatorni qulflash; SQLite'da BEGIN IMMEDIATE yetarli. */
    public static function forUpdate(): string
    {
        return self::driver() === 'mysql' ? ' FOR UPDATE' : '';
    }

    public static function isUniqueViolation(PDOException $e): bool
    {
        return (string) $e->getCode() === '23000';
    }
}
