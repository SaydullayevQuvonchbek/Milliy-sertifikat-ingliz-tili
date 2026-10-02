<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Config;
use App\Db;
use App\Http\HttpError;
use App\Http\Request;
use App\Settings;
use App\Util;
use PDOException;

final class AuthController
{
    public static function me(Request $r): array
    {
        $user = Auth::user();
        return [
            'user' => $user ? Auth::publicUser($user) : null,
            'csrf' => Auth::csrfToken(),
            'site' => [
                'name' => Settings::get('site_name'),
                'registration_open' => (bool) Settings::get('registration_open'),
            ],
        ];
    }

    public static function login(Request $r): array
    {
        $login = $r->str('login');
        $password = (string) $r->input('password', '');
        if ($login === '' || $password === '') {
            throw new HttpError(422, 'validation', 'Login va parolni kiriting.');
        }
        $user = Auth::attempt($login, $password, $r->ip);
        Auth::login($user);
        return ['user' => Auth::publicUser($user), 'csrf' => Auth::csrfToken()];
    }

    public static function register(Request $r): array
    {
        if (!Settings::get('registration_open')) {
            throw new HttpError(403, 'registration_closed', "Ro'yxatdan o'tish yopiq. Login va parolni administratordan oling.");
        }
        // Avtomatik ommaviy ro'yxatdan o'tishning oldini olish (bitta markaz IP'si ostida yuzlab o'quvchi bo'lishi
        // mumkin — chegara config'da 'register_ip_limit', 0 — o'chirilgan).
        Auth::rateLimit('register', $r->ip, max(0, (int) (Config::get('register_ip_limit') ?? 100)), 900);
        $name = trim(preg_replace('/\s+/u', ' ', Util::cleanText($r->str('full_name'), 120)) ?? '');
        $phone = Util::normalizePhone($r->str('phone'));
        $password = (string) $r->input('password', '');

        if (mb_strlen($name) < 5 || !str_contains($name, ' ')) {
            throw new HttpError(422, 'validation', 'Ism va familiyangizni to\'liq kiriting.');
        }
        if ($phone === null) {
            throw new HttpError(422, 'validation', "Telefon raqami noto'g'ri. Masalan: +998 90 123 45 67.");
        }
        if (mb_strlen($password) < 6) {
            throw new HttpError(422, 'validation', "Parol kamida 6 ta belgidan iborat bo'lishi kerak.");
        }
        try {
            $id = Db::insert('users', [
                'role' => 'student',
                'full_name' => $name,
                'login' => $phone,
                'phone' => $phone,
                'password_hash' => Auth::hash($password),
                'status' => 'active',
                'created_at' => time(),
            ]);
        } catch (PDOException $e) {
            if (Db::isUniqueViolation($e)) {
                throw new HttpError(409, 'exists', "Bu telefon raqami bilan allaqachon ro'yxatdan o'tilgan. Tizimga kiring.");
            }
            throw $e;
        }
        $user = Db::one('SELECT * FROM users WHERE id = ?', [$id]);
        Auth::login($user);
        return ['user' => Auth::publicUser($user), 'csrf' => Auth::csrfToken()];
    }

    public static function logout(Request $r): array
    {
        Auth::logout();
        return ['ok' => true];
    }

    public static function changePassword(Request $r): array
    {
        $user = Auth::require();
        $row = Db::one('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
        if (!password_verify((string) $r->input('current', ''), (string) $row['password_hash'])) {
            throw new HttpError(422, 'validation', "Joriy parol noto'g'ri.");
        }
        $new = (string) $r->input('password', '');
        if (mb_strlen($new) < 6) {
            throw new HttpError(422, 'validation', "Yangi parol kamida 6 ta belgidan iborat bo'lishi kerak.");
        }
        Db::exec('UPDATE users SET password_hash = ? WHERE id = ?', [Auth::hash($new), $user['id']]);
        return ['ok' => true];
    }
}
