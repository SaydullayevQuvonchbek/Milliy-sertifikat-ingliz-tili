<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Audit;
use App\Auth;
use App\Db;
use App\Http\HttpError;
use App\Http\Request;
use App\Util;
use PDOException;

final class AdminUserController
{
    public static function list(Request $r): array
    {
        Auth::require('admin');
        $role = $r->str('role');
        $q = $r->str('q');
        $page = max(1, $r->int('page', 1));
        $perPage = 50;

        $where = [];
        $params = [];
        if (in_array($role, ['admin', 'expert', 'student'], true)) {
            $where[] = 'role = ?';
            $params[] = $role;
        }
        if ($q !== '') {
            // "!" — SQLite va MySQL'da bir xil ishlaydigan qochish belgisi.
            $where[] = "(full_name LIKE ? ESCAPE '!' OR login LIKE ? ESCAPE '!')";
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $total = (int) Db::val("SELECT COUNT(*) FROM users {$sqlWhere}", $params);
        $rows = Db::all(
            "SELECT id, role, full_name, login, phone, status, created_at, last_login_at,
                    (SELECT COUNT(*) FROM attempts a WHERE a.user_id = users.id) AS attempts
             FROM users {$sqlWhere} ORDER BY id DESC LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage),
            $params
        );
        return ['users' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public static function create(Request $r): array
    {
        $admin = Auth::require('admin');
        $role = $r->str('role', 'student');
        if (!in_array($role, ['admin', 'expert', 'student'], true)) {
            throw new HttpError(422, 'validation', "Rol noto'g'ri.");
        }
        $name = self::cleanName($r->str('full_name'));
        $login = self::loginFor($role, $r->str('login'));
        $password = (string) $r->input('password', '');
        if ($password === '') {
            $password = Util::randomPassword();
        }
        if (mb_strlen($password) < 6) {
            throw new HttpError(422, 'validation', "Parol kamida 6 ta belgidan iborat bo'lishi kerak.");
        }
        $id = self::insert($role, $name, $login, $password);
        Audit::log((int) $admin['id'], 'user_create', 'user:' . $id, ['role' => $role]);
        return ['user' => Db::one('SELECT id, role, full_name, login, status FROM users WHERE id = ?', [$id]), 'password' => $password];
    }

    /**
     * Ko'plab o'quvchini bir yo'la qo'shish. Har qator: "Ism Familiya; +998901234567".
     * Parollar avtomatik yaratiladi va bir marta ko'rsatiladi.
     */
    public static function bulk(Request $r): array
    {
        $admin = Auth::require('admin');
        $lines = preg_split('/\r\n|\r|\n/', (string) $r->input('text', '')) ?: [];
        $created = [];
        $errors = [];
        foreach ($lines as $index => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', preg_split('/[;\t,]/', $line, 2) ?: []);
            try {
                if (count($parts) < 2) {
                    throw new HttpError(422, 'validation', "Format: Ism Familiya; telefon.");
                }
                $name = self::cleanName($parts[0]);
                $login = self::loginFor('student', $parts[1]);
                $password = Util::randomPassword();
                $id = self::insert('student', $name, $login, $password);
                $created[] = ['id' => $id, 'full_name' => $name, 'login' => $login, 'password' => $password];
            } catch (HttpError $e) {
                $errors[] = ['line' => $index + 1, 'text' => mb_substr($line, 0, 120), 'message' => $e->getMessage()];
            }
        }
        if ($created !== []) {
            Audit::log((int) $admin['id'], 'user_bulk', '', ['count' => count($created)]);
        }
        return ['created' => $created, 'errors' => $errors];
    }

    public static function update(Request $r): array
    {
        $admin = Auth::require('admin');
        $id = (int) $r->param('id');
        $user = Db::one('SELECT * FROM users WHERE id = ?', [$id]);
        if ($user === null) {
            throw new HttpError(404, 'not_found', 'Foydalanuvchi topilmadi.');
        }
        $changes = [];
        if ($r->input('full_name') !== null) {
            $changes['full_name'] = self::cleanName($r->str('full_name'));
        }
        if ($r->input('status') !== null) {
            $status = $r->str('status');
            if (!in_array($status, ['active', 'blocked'], true)) {
                throw new HttpError(422, 'validation', "Holat noto'g'ri.");
            }
            if ($id === (int) $admin['id'] && $status === 'blocked') {
                throw new HttpError(422, 'validation', "O'zingizni bloklay olmaysiz.");
            }
            $changes['status'] = $status;
        }
        if ($r->input('login') !== null) {
            $changes['login'] = self::loginFor($user['role'], $r->str('login'));
            if ($user['role'] === 'student') {
                $changes['phone'] = $changes['login'];
            }
        }
        try {
            Db::update('users', $changes, 'id = ?', [$id]);
        } catch (PDOException $e) {
            if (Db::isUniqueViolation($e)) {
                throw new HttpError(409, 'exists', 'Bu login band.');
            }
            throw $e;
        }
        Audit::log((int) $admin['id'], 'user_update', 'user:' . $id, array_keys($changes));
        return ['user' => Db::one('SELECT id, role, full_name, login, status FROM users WHERE id = ?', [$id])];
    }

    public static function resetPassword(Request $r): array
    {
        $admin = Auth::require('admin');
        $id = (int) $r->param('id');
        if (!Db::val('SELECT COUNT(*) FROM users WHERE id = ?', [$id])) {
            throw new HttpError(404, 'not_found', 'Foydalanuvchi topilmadi.');
        }
        $password = (string) $r->input('password', '');
        if ($password === '') {
            $password = Util::randomPassword();
        }
        if (mb_strlen($password) < 6) {
            throw new HttpError(422, 'validation', "Parol kamida 6 ta belgidan iborat bo'lishi kerak.");
        }
        Db::exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $id]);
        Audit::log((int) $admin['id'], 'user_password_reset', 'user:' . $id);
        return ['password' => $password];
    }

    public static function attempts(Request $r): array
    {
        Auth::require('admin');
        $id = (int) $r->param('id');
        $rows = Db::all(
            'SELECT a.id, a.mock_id, m.title, a.attempt_no, a.status, a.stage, a.violations, a.overall, a.level, a.started_at, a.finished_at
             FROM attempts a JOIN mocks m ON m.id = a.mock_id WHERE a.user_id = ? ORDER BY a.id DESC',
            [$id]
        );
        return ['attempts' => $rows];
    }

    private static function cleanName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', Util::cleanText($name, 120)) ?? '');
        if (mb_strlen($name) < 3) {
            throw new HttpError(422, 'validation', "Ism kamida 3 ta harfdan iborat bo'lishi kerak.");
        }
        return $name;
    }

    private static function loginFor(string $role, string $login): string
    {
        if ($role === 'student') {
            $phone = Util::normalizePhone($login);
            if ($phone === null) {
                throw new HttpError(422, 'validation', "Telefon raqami noto'g'ri: \"{$login}\".");
            }
            return $phone;
        }
        $login = Util::normalizeLogin($login);
        if (!preg_match('/^(?:[a-z][a-z0-9_.-]{2,39}|\+998\d{9})$/', $login)) {
            throw new HttpError(422, 'validation', "Login lotin harfi bilan boshlanib, 3–40 belgidan iborat bo'lishi kerak.");
        }
        return $login;
    }

    private static function insert(string $role, string $name, string $login, string $password): int
    {
        try {
            return Db::insert('users', [
                'role' => $role,
                'full_name' => $name,
                'login' => $login,
                'phone' => str_starts_with($login, '+') ? $login : null,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'status' => 'active',
                'created_at' => time(),
            ]);
        } catch (PDOException $e) {
            if (Db::isUniqueViolation($e)) {
                throw new HttpError(409, 'exists', "Bu login allaqachon mavjud: {$login}.");
            }
            throw $e;
        }
    }
}
