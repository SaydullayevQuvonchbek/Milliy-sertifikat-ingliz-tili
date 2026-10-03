<?php

declare(strict_types=1);

use App\Auth;
use App\Config;
use App\Controllers\AdminSecurityController;
use App\Db;
use App\Http\ClientIp;
use App\Http\HttpError;
use App\Http\Request;
use App\Http\Security;
use App\Installer;

/** Config qiymatlarini vaqtincha o'zgartirib, test oxirida (xato bo'lsa ham) tiklaydi. */
function with_config(array $values, callable $fn): void
{
    $saved = [];
    foreach ($values as $key => $value) {
        $saved[$key] = Config::get($key, '__unset__');
        Config::set($key, $value);
    }
    try {
        $fn();
    } finally {
        foreach ($saved as $key => $value) {
            Config::set($key, $value === '__unset__' ? null : $value);
        }
    }
}

function fill_ip_failures(string $ip, int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        Db::insert('login_throttle', ['throttle_key' => Auth::ipKey($ip), 'created_at' => time()]);
    }
}

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
    // Har login uchun alohida chegaraga yetmasdan, IP bo'yicha jami 300 ta xato (standart chegara).
    fill_ip_failures('10.9.9.9', 300);
    throws(static fn () => Auth::attempt('+998901112233', 'togri-parol', '10.9.9.9'), 'throttled');
    eq('+998901112233', Auth::attempt('+998901112233', 'togri-parol', '10.9.9.8')['login']);
});

test('kirish: IP chegarasi config\'dan olinadi, 0 — o\'chirilgan', static function (): void {
    Installer::createUser('student', 'Test Talaba', '+998901112233', 'togri-parol');
    with_config(['login_ip_limit' => 5], static function (): void {
        for ($i = 0; $i < 5; $i++) {
            throws(static fn () => Auth::attempt('+99890000000' . $i, 'x', '10.1.1.1'), 'bad_credentials');
        }
        throws(static fn () => Auth::attempt('+998901112233', 'togri-parol', '10.1.1.1'), 'throttled');
    });
    with_config(['login_ip_limit' => 0], static function (): void {
        fill_ip_failures('10.1.1.2', 5000);
        eq('+998901112233', Auth::attempt('+998901112233', 'togri-parol', '10.1.1.2')['login']);
        // Login+IP chegarasi baribir ishlaydi.
        for ($i = 0; $i < 8; $i++) {
            throws(static fn () => Auth::attempt('+998901112233', 'xato', '10.1.1.2'), 'bad_credentials');
        }
        throws(static fn () => Auth::attempt('+998901112233', 'togri-parol', '10.1.1.2'), 'throttled');
    });
    eq(300, Auth::ipLimit(), 'tiklangandan keyin standart qiymat');
});

test('kirish: IP bloklanganda administrator va ekspert kira oladi, o\'quvchi — yo\'q', static function (): void {
    Installer::createUser('student', 'Test Talaba', '+998901112233', 'togri-parol');
    Installer::createUser('admin', 'Administrator', 'admin', 'admin-parol');
    Installer::createUser('expert', 'Ekspert', 'expert1', 'ekspert-parol');
    fill_ip_failures('10.2.2.2', 300);
    throws(static fn () => Auth::attempt('+998901112233', 'togri-parol', '10.2.2.2'), 'throttled');
    eq('admin', Auth::attempt('admin', 'admin-parol', '10.2.2.2')['login']);
    eq('expert1', Auth::attempt('expert1', 'ekspert-parol', '10.2.2.2')['login']);
    // Administratorning o'z login+IP chegarasi (8 ta) saqlanadi.
    for ($i = 0; $i < 8; $i++) {
        throws(static fn () => Auth::attempt('admin', 'xato', '10.2.2.2'), 'bad_credentials');
    }
    throws(static fn () => Auth::attempt('admin', 'admin-parol', '10.2.2.2'), 'throttled');
});

test('kirish: mavjud bo\'lmagan login va noto\'g\'ri parol bir xil xato beradi', static function (): void {
    Installer::createUser('student', 'Test Talaba', '+998901112233', 'togri-parol');
    throws(static fn () => Auth::attempt('+998900000000', 'x', '10.0.0.3'), 'bad_credentials');
    throws(static fn () => Auth::attempt('+998901112233', 'x', '10.0.0.3'), 'bad_credentials');
});

test('rateLimit: oynada ko\'p urinish 429 beradi, 0 — chegara yo\'q', static function (): void {
    for ($i = 0; $i < 3; $i++) {
        Auth::rateLimit('demo', '1.2.3.4', 3, 900);
    }
    throws(static fn () => Auth::rateLimit('demo', '1.2.3.4', 3, 900), 'throttled');
    Auth::rateLimit('demo', '1.2.3.5', 3, 900); // boshqa IP'ga tegmaydi
    for ($i = 0; $i < 20; $i++) {
        Auth::rateLimit('demo0', '1.2.3.4', 0, 900);
    }
    eq(0, (int) Db::val("SELECT COUNT(*) FROM login_throttle WHERE throttle_key LIKE 'rl|demo0|%'"), "0 bo'lsa yozuv qo'shilmaydi");
});

test('kirish bloklari: holat, IP bo\'yicha va to\'liq tozalash', static function (): void {
    Installer::createUser('student', 'Test Talaba', '+998901112233', 'togri-parol');
    with_config(['login_ip_limit' => 10], static function (): void {
        for ($i = 0; $i < 10; $i++) {
            throws(static fn () => Auth::attempt('+998901112233', 'xato', '10.3.3.3'), $i < 8 ? 'bad_credentials' : 'throttled');
        }
        fill_ip_failures('10.3.3.3', 2); // jami 10 — chegarada
        fill_ip_failures('10.4.4.4', 3);
        Auth::rateLimit('register', '10.3.3.3', 100, 900);

        $status = Auth::throttleStatus('10.3.3.3');
        eq(10, $status['ip_failures']);
        eq(10, $status['ip_limit']);
        eq(8, $status['login_limit']);
        eq(15, $status['window_minutes']);
        eq(1, $status['blocked_logins']);
        eq(['ip' => '10.3.3.3', 'failures' => 10, 'blocked' => true], $status['top_ips'][0]);
        eq(['ip' => '10.4.4.4', 'failures' => 3, 'blocked' => false], $status['top_ips'][1]);

        eq(19, Auth::clearThrottle('10.3.3.3'), 'ip (10) + login (8) + rateLimit (1)');
        eq(0, (int) Db::val("SELECT COUNT(*) FROM login_throttle WHERE throttle_key LIKE '%10.3.3.3%'"));
        eq('+998901112233', Auth::attempt('+998901112233', 'togri-parol', '10.3.3.3')['login']);
        eq(3, Auth::throttleStatus('10.4.4.4')['ip_failures'], 'boshqa IP saqlanadi');
        eq(3, Auth::clearThrottle());
        eq(0, (int) Db::val('SELECT COUNT(*) FROM login_throttle'));
    });
});

test('kirish bloklari: IP bo\'yicha tozalash o\'xshash manzillarga tegmaydi', static function (): void {
    fill_ip_failures('1.2.3.4', 2);
    fill_ip_failures('11.2.3.4', 2);
    fill_ip_failures('1.2.3.40', 2);
    Auth::rateLimit('register', '11.2.3.4', 100, 900);
    eq(2, Auth::clearThrottle('1.2.3.4'));
    eq(5, (int) Db::val('SELECT COUNT(*) FROM login_throttle'));
});

test('kirish bloklari: administrator API — ruxsat, tekshiruv va jurnal', static function (): void {
    $admin = make_user('admin', 'admin');
    $student = make_user('student', '+998901112244');
    fill_ip_failures('10.5.5.5', 4);
    fill_ip_failures('10.6.6.6', 2);
    $req = static fn (array $body, string $ip = '10.6.6.6') => new Request('POST', '/admin/security/throttle/clear', [], $body, [], [], $ip);

    Auth::actAs($student);
    throws(static fn () => AdminSecurityController::throttle(new Request('GET', '/admin/security/throttle', [], [], [], [], '10.6.6.6')), 'forbidden');
    throws(static fn () => AdminSecurityController::clear($req(['scope' => 'all'])), 'forbidden');

    Auth::actAs($admin);
    $view = AdminSecurityController::throttle(new Request('GET', '/admin/security/throttle', [], [], [], [], '10.6.6.6'));
    eq('10.6.6.6', $view['ip']['detected']);
    eq(2, $view['throttle']['ip_failures']);
    ok(array_key_exists('header_used', $view['ip']));

    throws(static fn () => AdminSecurityController::clear($req(['scope' => 'ip', 'ip' => 'abc'])), 'validation');
    throws(static fn () => AdminSecurityController::clear($req(['scope' => 'boshqa'])), 'validation');

    $res = AdminSecurityController::clear($req(['scope' => 'mine']));
    eq(2, $res['removed']);
    eq(0, $res['throttle']['ip_failures']);
    $res = AdminSecurityController::clear($req(['scope' => 'ip', 'ip' => ' 10.5.5.5 ']));
    eq(4, $res['removed']);
    eq([], $res['throttle']['top_ips']);

    $log = Db::all("SELECT target FROM audit_log WHERE action = 'throttle_clear' ORDER BY id");
    eq(['10.6.6.6', '10.5.5.5'], array_column($log, 'target'));
});

test('IP: sarlavha faqat ishonchli proksidan kelganda o\'qiladi', static function (): void {
    $h = 'HTTP_X_FORWARDED_FOR';
    // Sarlavha sozlanmagan — har doim REMOTE_ADDR.
    eq('203.0.113.7', ClientIp::resolve(['REMOTE_ADDR' => '203.0.113.7', $h => '1.1.1.1'], '', ['private']));
    // To'g'ridan-to'g'ri kelgan so'rov: mijoz yozgan sarlavha e'tiborsiz.
    eq('203.0.113.7', ClientIp::resolve(['REMOTE_ADDR' => '203.0.113.7', $h => '1.1.1.1'], $h, ['private']));
    // Lokal proksi: o'ngdan birinchi ishonchsiz manzil; chapdagi soxta qiymat e'tiborsiz.
    eq('198.51.100.20', ClientIp::resolve(['REMOTE_ADDR' => '127.0.0.1', $h => '1.1.1.1, 198.51.100.20'], $h, ['private']));
    eq('198.51.100.20', ClientIp::resolve(['REMOTE_ADDR' => '127.0.0.1', $h => '198.51.100.20, 10.0.0.2'], $h, ['private']));
    // Sarlavha yo'q yoki bo'sh.
    eq('127.0.0.1', ClientIp::resolve(['REMOTE_ADDR' => '127.0.0.1'], $h, ['private']));
    eq('127.0.0.1', ClientIp::resolve(['REMOTE_ADDR' => '127.0.0.1', $h => '  '], $h, ['private']));
    // Buzilgan qiymat: undan chapdagilarga ishonilmaydi.
    eq('203.0.113.9', ClientIp::resolve(['REMOTE_ADDR' => '127.0.0.1', $h => 'garbage, 203.0.113.9'], $h, ['private']));
    eq('127.0.0.1', ClientIp::resolve(['REMOTE_ADDR' => '127.0.0.1', $h => '203.0.113.9, unknown'], $h, ['private']));
    // Zanjirdagi hammasi ichki tarmoqda — markazdagi kompyuter.
    eq('192.168.1.20', ClientIp::resolve(['REMOTE_ADDR' => '127.0.0.1', $h => '192.168.1.20'], $h, ['private']));
    // Cloudflare: faqat 'cloudflare' kalit so'zi bilan ishoniladi.
    $cf = ['REMOTE_ADDR' => '173.245.48.5', $h => '198.51.100.7'];
    eq('198.51.100.7', ClientIp::resolve($cf, $h, ['private', 'cloudflare']));
    eq('173.245.48.5', ClientIp::resolve($cf, $h, ['private']));
    eq('198.51.100.8', ClientIp::resolve(['REMOTE_ADDR' => '2606:4700:10::6816:1', 'HTTP_CF_CONNECTING_IP' => '198.51.100.8'], 'HTTP_CF_CONNECTING_IP', 'cloudflare'));
    // Aniq IP va CIDR ro'yxati.
    eq('198.51.100.9', ClientIp::resolve(['REMOTE_ADDR' => '203.0.113.50', $h => '198.51.100.9'], $h, ['203.0.113.50']));
    eq('198.51.100.9', ClientIp::resolve(['REMOTE_ADDR' => '203.0.113.50', $h => '198.51.100.9'], $h, ['203.0.113.0/24']));
    // IPv6, port va IPv4-in-IPv6.
    eq('2001:db8::5', ClientIp::resolve(['REMOTE_ADDR' => '::1', $h => '2001:DB8::5'], $h, ['private']));
    eq('2001:db8::5', ClientIp::resolve(['REMOTE_ADDR' => '::1', $h => '"[2001:db8::5]:4711"'], $h, ['private']));
    eq('203.0.113.5', ClientIp::resolve(['REMOTE_ADDR' => '::ffff:127.0.0.1', $h => '203.0.113.5:8080'], $h, ['private']));
    eq('0.0.0.0', ClientIp::resolve([], $h, ['private']));
});

test('IP: CIDR tekshiruvi va normalize', static function (): void {
    ok(ClientIp::inRange('10.1.2.3', '10.0.0.0/8'));
    ok(!ClientIp::inRange('11.0.0.1', '10.0.0.0/8'));
    ok(ClientIp::inRange('172.31.255.255', '172.16.0.0/12'));
    ok(!ClientIp::inRange('172.32.0.0', '172.16.0.0/12'));
    ok(ClientIp::inRange('2606:4700::1', '2606:4700::/32'));
    ok(!ClientIp::inRange('2606:4701::1', '2606:4700::/32'));
    ok(ClientIp::inRange('fd12:3456::1', 'fc00::/7'));
    ok(!ClientIp::inRange('10.0.0.1', '::/0'), 'IPv4 IPv6 oralig\'iga tushmaydi');
    ok(ClientIp::inRange('8.8.8.8', '0.0.0.0/0'));
    ok(ClientIp::inRange('8.8.8.8', '8.8.8.8'));
    ok(!ClientIp::inRange('8.8.8.8', '8.8.8.0/33'));
    ok(!ClientIp::inRange('8.8.8.8', '8.8.8.0/abc'));
    ok(!ClientIp::inRange('nonsense', '0.0.0.0/0'));
    eq(null, ClientIp::normalize('unknown'));
    eq(null, ClientIp::normalize('1.2.3.4.5'));
    eq('1.2.3.4', ClientIp::normalize(" '1.2.3.4' "));
    eq('::1', ClientIp::normalize('[::1]'));
    eq(['127.0.0.0/8', '10.0.0.0/8'], array_slice(ClientIp::expand('private'), 0, 2));
    eq(['198.51.100.1'], ClientIp::expand([' 198.51.100.1 ', '']));
});

test('IP: Request::clientIp config\'dagi sozlamani ishlatadi', static function (): void {
    $saved = $_SERVER;
    try {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.30';
        with_config(['client_ip_header' => ''], static fn () => eq('127.0.0.1', Request::clientIp()));
        with_config(['client_ip_header' => 'HTTP_X_FORWARDED_FOR'], static fn () => eq('198.51.100.30', Request::clientIp()));
        with_config(['client_ip_header' => 'HTTP_X_FORWARDED_FOR', 'trusted_proxies' => []], static fn () => eq('127.0.0.1', Request::clientIp()));
    } finally {
        $_SERVER = $saved;
    }
});

test('CSP: .htaccess dagi matn Security::CSP bilan bir xil', static function (): void {
    $htaccess = (string) file_get_contents(APP_ROOT . '/public/.htaccess');
    ok(str_contains($htaccess, 'Header always set Content-Security-Policy "' . Security::CSP . '"'), '.htaccess dagi CSP src/Http/Security.php bilan mos emas');
    ok(!preg_match("/script-src[^;]*unsafe/", Security::CSP), "script-src da 'unsafe-*' bo'lmasligi kerak");
    ok(str_contains($htaccess, 'X-Frame-Options'), 'X-Frame-Options yo\'q');
});

test('Permissions-Policy: .htaccess va README dagi matn Security::PERMISSIONS bilan bir xil, kamera va ekran ochiq', static function (): void {
    $htaccess = (string) file_get_contents(APP_ROOT . '/public/.htaccess');
    $readme = (string) file_get_contents(APP_ROOT . '/README.md');
    ok(str_contains($htaccess, 'Header always set Permissions-Policy "' . Security::PERMISSIONS . '"'), '.htaccess dagi Permissions-Policy mos emas');
    ok(str_contains($readme, 'add_header Permissions-Policy "' . Security::PERMISSIONS . '"'), 'README (nginx) dagi Permissions-Policy mos emas');
    foreach (['camera=(self)', 'display-capture=(self)', 'microphone=(self)'] as $needed) {
        ok(str_contains(Security::PERMISSIONS, $needed), "{$needed} yo'q — video nazorat ishlamaydi");
    }
});
