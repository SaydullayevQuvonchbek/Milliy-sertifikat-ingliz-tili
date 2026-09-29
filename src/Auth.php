<?php

declare(strict_types=1);

namespace App;

use App\Http\HttpError;
use App\Http\Request;

final class Auth
{
    private const THROTTLE_WINDOW = 900;
    private const THROTTLE_MAX = 8;
    private const THROTTLE_MAX_IP = 300;

    private static ?array $user = null;
    private static bool $loaded = false;
    /** Testlar uchun: sessiyasiz foydalanuvchini qo'lda o'rnatish. */
    private static bool $testMode = false;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE || self::$testMode) {
            return;
        }
        $secure = Config::get('secure_cookies', 'auto');
        if ($secure === 'auto') {
            $secure = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', '43200');
        session_name((string) Config::get('session_name', 'mlmock_sid'));
        session_set_cookie_params([
            'lifetime' => 43200,
            'path' => '/',
            'secure' => (bool) $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /** Sessiyani faqat o'qish kerak bo'lsa — qulfni darhol bo'shatamiz (parallel so'rovlar kutib qolmasin). */
    public static function release(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        $uid = (int) ($_SESSION['uid'] ?? 0);
        if ($uid <= 0) {
            return null;
        }
        $user = Db::one('SELECT id, role, full_name, login, phone, status FROM users WHERE id = ?', [$uid]);
        if ($user === null || $user['status'] !== 'active') {
            return null;
        }
        self::$user = $user;
        return $user;
    }

    public static function require(string ...$roles): array
    {
        $user = self::user();
        if ($user === null) {
            throw new HttpError(401, 'unauthorized', 'Tizimga qayta kiring.');
        }
        if ($roles !== [] && !in_array($user['role'], $roles, true)) {
            throw new HttpError(403, 'forbidden', "Bu amal uchun ruxsat yo'q.");
        }
        return $user;
    }

    public static function login(array $user): void
    {
        if (!self::$testMode) {
            session_regenerate_id(true);
        }
        $_SESSION['uid'] = (int) $user['id'];
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
        self::$user = null;
        self::$loaded = false;
        Db::exec('UPDATE users SET last_login_at = ? WHERE id = ?', [time(), $user['id']]);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (!self::$testMode && session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        self::$user = null;
        self::$loaded = true;
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(24));
        }
        return (string) $_SESSION['csrf'];
    }

    public static function checkCsrf(Request $request): void
    {
        if (in_array($request->method, ['GET', 'HEAD'], true) || self::$testMode) {
            return;
        }
        $token = (string) ($request->header('x-csrf-token') ?? '');
        $expected = (string) ($_SESSION['csrf'] ?? '');
        if ($expected === '' || !hash_equals($expected, $token)) {
            throw new HttpError(419, 'csrf', 'Sahifa eskirgan. Sahifani yangilab, qayta urinib ko\'ring.');
        }
    }

    public static function attempt(string $login, string $password, string $ip): array
    {
        $normalized = Util::normalizeLogin($login);
        $key = sha1($ip . '|' . $normalized);
        // Bitta IP'dan turli loginlarni ketma-ket sinash (credential stuffing) ham chegaralanadi. Chegara katta:
        // bitta markazdagi yuzlab o'quvchi umumiy IP orqali kiradi.
        $ipKey = sha1('ip|' . $ip);
        $since = time() - self::THROTTLE_WINDOW;
        Db::exec('DELETE FROM login_throttle WHERE created_at < ?', [$since]);
        $fails = (int) Db::val('SELECT COUNT(*) FROM login_throttle WHERE throttle_key = ? AND created_at >= ?', [$key, $since]);
        $ipFails = (int) Db::val('SELECT COUNT(*) FROM login_throttle WHERE throttle_key = ? AND created_at >= ?', [$ipKey, $since]);
        if ($fails >= self::THROTTLE_MAX || $ipFails >= self::THROTTLE_MAX_IP) {
            throw new HttpError(429, 'throttled', "Juda ko'p urinish. 15 daqiqadan keyin qayta urinib ko'ring.");
        }

        $user = Db::one('SELECT * FROM users WHERE login = ?', [$normalized]);
        // Login mavjud bo'lmasa ham parol tekshiriladi: javob vaqti bo'yicha loginni aniqlab bo'lmasin.
        $hash = $user !== null ? (string) $user['password_hash'] : self::dummyHash();
        if (!password_verify($password, $hash) || $user === null) {
            Db::insert('login_throttle', ['throttle_key' => $key, 'created_at' => time()]);
            Db::insert('login_throttle', ['throttle_key' => $ipKey, 'created_at' => time()]);
            throw new HttpError(422, 'bad_credentials', "Login yoki parol noto'g'ri.");
        }
        if ($user['status'] !== 'active') {
            throw new HttpError(403, 'blocked', 'Hisobingiz bloklangan. Administratorga murojaat qiling.');
        }
        if (self::needsRehash((string) $user['password_hash'])) {
            Db::exec('UPDATE users SET password_hash = ? WHERE id = ?', [self::hash($password), $user['id']]);
        }
        Db::exec('DELETE FROM login_throttle WHERE throttle_key = ?', [$key]);
        return $user;
    }

    /**
     * Parol xeshi. Narx (cost) qat'iy belgilanadi: PHP 8.4 standart narxni 10 dan 12 ga ko'targan (~250 ms/xesh),
     * imtihon boshlanishida yuzlab o'quvchi bir vaqtda kirganda bu kutishni ~4 baravar oshiradi.
     * 10 — OWASP tavsiya etgan eng kam narx; config'da 'password_cost' bilan oshirish mumkin (10–14).
     */
    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => self::cost()]);
    }

    private static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => self::cost()]);
    }

    private static function cost(): int
    {
        return max(10, min(14, (int) Config::get('password_cost', 10)));
    }

    private static function dummyHash(): string
    {
        static $hash = null;
        return $hash ??= self::hash(bin2hex(random_bytes(12)));
    }

    /**
     * Umumiy urinish chegarasi (masalan, ro'yxatdan o'tish): oynada $max martadan ko'p bo'lsa 429.
     * Muvaffaqiyatli chaqiruv ham hisoblanadi.
     */
    public static function rateLimit(string $bucket, string $ip, int $max, int $window): void
    {
        $key = sha1($bucket . '|' . $ip);
        $since = time() - $window;
        Db::exec('DELETE FROM login_throttle WHERE throttle_key = ? AND created_at < ?', [$key, $since]);
        $count = (int) Db::val('SELECT COUNT(*) FROM login_throttle WHERE throttle_key = ? AND created_at >= ?', [$key, $since]);
        if ($count >= $max) {
            throw new HttpError(429, 'throttled', "Juda ko'p urinish. Birozdan keyin qayta urinib ko'ring.");
        }
        Db::insert('login_throttle', ['throttle_key' => $key, 'created_at' => time()]);
    }

    public static function publicUser(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'role' => $user['role'],
            'full_name' => $user['full_name'],
            'login' => $user['login'],
        ];
    }

    // --- Testlar uchun ---

    public static function enableTestMode(): void
    {
        self::$testMode = true;
        $_SESSION = $_SESSION ?? [];
    }

    public static function actAs(?array $user): void
    {
        self::$testMode = true;
        $_SESSION['uid'] = $user['id'] ?? 0;
        self::$user = null;
        self::$loaded = false;
    }
}
