<?php

declare(strict_types=1);

use App\Auth;
use App\Db;
use App\Http\HttpError;
use App\Http\Security;
use App\Installer;

test('kirish: 8 marta xato paroldan keyin bloklanadi, to\'g\'ri parol bilan ham', static function (): void {
    Installer::createUser('student', 'Test Talaba', '+998901112233', 'togri-parol');
    for ($i = 0; $i < 8; $i++) {
        throws(static fn () => Auth::attempt('+998901112233', 'xato' . $i, '10.0.0.1'), 'bad_credentials');
    }
    throws(static fn () => Auth::attempt('+998901112233', 'togri-parol', '10.0.0.1'), 'throttled');
    // Boshqa IP'dan kirish mumkin.
    $user = Auth::attempt('+998901112233', 'togri-parol', '10.0.0.2');
    eq('+998901112233', $user['login']);
});

test('kirish: bitta IP\'dan turli loginlarni sinash umumiy chegaraga uriladi', static function (): void {
    Installer::createUser('student', 'Test Talaba', '+998901112233', 'togri-parol');
    // Har login uchun alohida chegaraga yetmasdan, IP bo'yicha jami 300 ta xato.
    $rows = [];
    for ($i = 0; $i < 300; $i++) {
        $rows[] = [sha1('ip|10.9.9.9'), time()];
    }
    foreach ($rows as [$key, $at]) {
        Db::insert('login_throttle', ['throttle_key' => $key, 'created_at' => $at]);
    }
    throws(static fn () => Auth::attempt('+998901112233', 'togri-parol', '10.9.9.9'), 'throttled');
    eq('+998901112233', Auth::attempt('+998901112233', 'togri-parol', '10.9.9.8')['login']);
});

test('kirish: mavjud bo\'lmagan login va noto\'g\'ri parol bir xil xato beradi', static function (): void {
    Installer::createUser('student', 'Test Talaba', '+998901112233', 'togri-parol');
    throws(static fn () => Auth::attempt('+998900000000', 'x', '10.0.0.3'), 'bad_credentials');
    throws(static fn () => Auth::attempt('+998901112233', 'x', '10.0.0.3'), 'bad_credentials');
});

test('rateLimit: oynada ko\'p urinish 429 beradi', static function (): void {
    for ($i = 0; $i < 3; $i++) {
        Auth::rateLimit('demo', '1.2.3.4', 3, 900);
    }
    throws(static fn () => Auth::rateLimit('demo', '1.2.3.4', 3, 900), 'throttled');
    Auth::rateLimit('demo', '1.2.3.5', 3, 900); // boshqa IP'ga tegmaydi
});

test('CSP: .htaccess dagi matn Security::CSP bilan bir xil', static function (): void {
    $htaccess = (string) file_get_contents(APP_ROOT . '/public/.htaccess');
    ok(str_contains($htaccess, 'Header always set Content-Security-Policy "' . Security::CSP . '"'), '.htaccess dagi CSP src/Http/Security.php bilan mos emas');
    ok(!preg_match("/script-src[^;]*unsafe/", Security::CSP), "script-src da 'unsafe-*' bo'lmasligi kerak");
    ok(str_contains($htaccess, 'X-Frame-Options'), 'X-Frame-Options yo\'q');
});
