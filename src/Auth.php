<?php

declare(strict_types=1);

namespace App;

use App\Http\HttpError;
use App\Http\Request;

final class Auth
{
    private const THROTTLE_WINDOW = 900;
    private const THROTTLE_MAX = 8;
    /** Bitta IP'dan jami xato kirishlar (15 daqiqada); config: 'login_ip_limit', 0 — chegara yo'q. */
    private const DEFAULT_IP_LIMIT = 300;
    /** Umumiy IP chegarasi qo'llanmaydigan rollar (ularning login+IP chegarasi baribir ishlaydi). */
    private const STAFF_ROLES = ['admin', 'expert'];

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
        $key = self::loginKey($ip, $normalized);
        // Bitta IP'dan turli loginlarni ketma-ket sinash (credential stuffing) ham chegaralanadi. Markazda yuzlab
        // o'quvchi bitta tashqi IP orqali kiradi — chegara sozlamada ('login_ip_limit') o'zgartiriladi yoki o'chiriladi.
        $ipKey = self::ipKey($ip);
        $since = time() - self::THROTTLE_WINDOW;
        Db::exec('DELETE FROM login_throttle WHERE created_at < ?', [$since]);

        $user = Db::one('SELECT * FROM users WHERE login = ?', [$normalized]);
        // Administrator va ekspertga umumiy IP chegarasi qo'llanmaydi: o'quvchilar xatolari tufayli bloklangan
        // markazdan ham kirib, "Sozlamalar → Kirish bloklari" orqali blokni ocha olsin. Ularning login+IP chegarasi
        // (8 ta) baribir ishlaydi. Natijada IP bloklanganda qaysi login xodimga tegishli ekani bilinib qolishi mumkin —
        // bu xodim loginlari (masalan "admin") odatda sir bo'lmagani uchun maqbul.
        $staff = $user !== null && in_array($user['role'], self::STAFF_ROLES, true);
        $ipLimit = self::ipLimit();
        if (self::countSince($key, $since) >= self::THROTTLE_MAX
            || (!$staff && $ipLimit > 0 && self::countSince($ipKey, $since) >= $ipLimit)) {
            throw new HttpError(429, 'throttled', "Juda ko'p urinish. 15 daqiqadan keyin qayta urinib ko'ring.");
        }

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
     * Muvaffaqiyatli chaqiruv ham hisoblanadi. $max <= 0 — chegara yo'q.
     */
    public static function rateLimit(string $bucket, string $ip, int $max, int $window): void
    {
        if ($max <= 0) {
            return;
        }
        $key = 'rl|' . $bucket . '|' . $ip;
        $since = time() - $window;
        Db::exec('DELETE FROM login_throttle WHERE throttle_key = ? AND created_at < ?', [$key, $since]);
        if (self::countSince($key, $since) >= $max) {
            throw new HttpError(429, 'throttled', "Juda ko'p urinish. Birozdan keyin qayta urinib ko'ring.");
        }
        Db::insert('login_throttle', ['throttle_key' => $key, 'created_at' => time()]);
    }

    // --- Kirish bloklari ---
    // Kalitlar: "ip|<IP>" — IP bo'yicha jami xatolar, "lg|<IP>|<sha1(login)>" — login+IP, "rl|<bucket>|<IP>" — rateLimit.
    // IP ochiq saqlanadi (15 daqiqa), shunda administrator qaysi manzil bloklanganini ko'rib, faqat uni ocha oladi.

    public static function ipKey(string $ip): string
    {
        return 'ip|' . $ip;
    }

    private static function loginKey(string $ip, string $login): string
    {
        return 'lg|' . $ip . '|' . sha1($login);
    }

    private static function countSince(string $key, int $since): int
    {
        return (int) Db::val('SELECT COUNT(*) FROM login_throttle WHERE throttle_key = ? AND created_at >= ?', [$key, $since]);
    }

    /** Bitta IP'dan 15 daqiqada ruxsat etilgan jami xato kirishlar; 0 — chegara yo'q. */
    public static function ipLimit(): int
    {
        return max(0, (int) (Config::get('login_ip_limit') ?? self::DEFAULT_IP_LIMIT));
    }

    /**
     * Administrator uchun holat: berilgan IP'ning xatolari va eng ko'p xato qilgan IP'lar (bloklanganlari belgilanadi).
     */
    public static function throttleStatus(string $ip): array
    {
        $since = time() - self::THROTTLE_WINDOW;
        Db::exec('DELETE FROM login_throttle WHERE created_at < ?', [$since]);
        $limit = self::ipLimit();
        $top = [];
        $rows = Db::all(
            "SELECT throttle_key, COUNT(*) AS n FROM login_throttle WHERE throttle_key LIKE 'ip|%' AND created_at >= ?
             GROUP BY throttle_key ORDER BY n DESC, throttle_key LIMIT 20",
            [$since]
        );
        foreach ($rows as $row) {
            $n = (int) $row['n'];
            $top[] = ['ip' => substr((string) $row['throttle_key'], 3), 'failures' => $n, 'blocked' => $limit > 0 && $n >= $limit];
        }
        // Chegara SQL ichiga son sifatida yoziladi: PDO parametrlari satr bo'lib boradi, SQLite esa COUNT(*) ni
        // satr bilan solishtirganda doim "kichik" deb hisoblaydi.
        $blockedLogins = Db::all(
            "SELECT throttle_key FROM login_throttle WHERE throttle_key LIKE 'lg|%' AND created_at >= ?
             GROUP BY throttle_key HAVING COUNT(*) >= " . (int) self::THROTTLE_MAX,
            [$since]
        );
        return [
            'ip' => $ip,
            'ip_failures' => self::countSince(self::ipKey($ip), $since),
            'ip_limit' => $limit,
            'login_limit' => self::THROTTLE_MAX,
            'window_minutes' => intdiv(self::THROTTLE_WINDOW, 60),
            'top_ips' => $top,
            'blocked_logins' => count($blockedLogins),
        ];
    }

    /**
     * Bloklarni tozalash. $ip berilsa — faqat shu manzil (IP hisobi, undan kelgan login bloklari va rateLimit),
     * aks holda hammasi. Qaytaradi: o'chirilgan yozuvlar soni.
     */
    public static function clearThrottle(?string $ip = null): int
    {
        if ($ip === null) {
            return Db::exec('DELETE FROM login_throttle');
        }
        return Db::exec(
            'DELETE FROM login_throttle WHERE throttle_key = ? OR throttle_key LIKE ? OR throttle_key LIKE ?',
            [self::ipKey($ip), 'lg|' . $ip . '|%', 'rl|%|' . $ip]
        );
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
