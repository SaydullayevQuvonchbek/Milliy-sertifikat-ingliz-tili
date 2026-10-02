<?php

declare(strict_types=1);

use App\Db;
use App\Installer;
use App\Services\Backup;

/** Vaqtinchalik papka (test oxirida o'chiriladi). */
function deploy_tmpdir(): string
{
    $dir = sys_get_temp_dir() . '/mlmock-deploytest-' . bin2hex(random_bytes(4));
    mkdir($dir, 0775, true);
    return $dir;
}

function deploy_rmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($dir);
}

/** PHP'ni alohida jarayonda ishga tushirish: [chiqish kodi, stdout, stderr]. */
function deploy_run(array $args, array $env): array
{
    $cmd = array_merge([PHP_BINARY], $args);
    $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, APP_ROOT, $env + ['PATH' => (string) getenv('PATH')]);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($proc), (string) $out, (string) $err];
}

test('.htaccess: yashirin fayllar (.user.ini) yopiq, storage har ikki Apache versiyasida yopiq', static function (): void {
    $public = (string) file_get_contents(APP_ROOT . '/public/.htaccess');
    $deny = strpos($public, 'RewriteRule (^|/)\.(?!well-known/) - [F,L]');
    $api = strpos($public, 'RewriteRule ^api/');
    ok($deny !== false, "public/.htaccess da yashirin fayllar qoidasi yo'q");
    ok($api !== false && $deny < $api, 'yashirin fayllar qoidasi API qoidasidan oldin turishi kerak');
    // Apache .htaccess ichida yo'l papkaga nisbatan, boshidagi "/" siz keladi.
    $rule = '~(^|/)\.(?!well-known/)~';
    ok(preg_match($rule, '.user.ini') === 1 && preg_match($rule, 'assets/.env') === 1);
    ok(preg_match($rule, 'assets/js/app.js') === 0 && preg_match($rule, 'api/auth/me') === 0);
    ok(preg_match($rule, '.well-known/acme-challenge/x') === 0, '.well-known ochiq qolishi kerak');

    $storage = (string) file_get_contents(APP_ROOT . '/storage/.htaccess');
    ok(str_contains($storage, '<IfModule mod_authz_core.c>') && str_contains($storage, 'Require all denied'));
    ok(str_contains($storage, '<IfModule !mod_authz_core.c>') && str_contains($storage, 'Deny from all'));
});

test('bin/: skriptlar veb orqali ishlamaydi (himoya require dan oldin)', static function (): void {
    foreach (glob(APP_ROOT . '/bin/*.php') ?: [] as $file) {
        $code = (string) file_get_contents($file);
        $name = basename($file);
        $guard = $name === 'dev-router.php'
            ? strpos($code, "if (PHP_SAPI !== 'cli-server')")
            : strpos($code, "if (PHP_SAPI !== 'cli' && isset(\$_SERVER['REQUEST_METHOD']))");
        ok($guard !== false, "{$name}: himoya yo'q");
        $require = preg_match('/^\s*(?:require|require_once|include|include_once)\b/m', $code, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] : null;
        ok($require === null || $guard < $require, "{$name}: himoya require dan keyin turibdi");
    }
});

test('bootstrap: config.php bo\'lmasa CLI ogohlantiradi, veb-so\'rov 500 bilan to\'xtaydi', static function (): void {
    $script = 'require "src/bootstrap.php"; echo "OK:" . App\Config::get("db.driver");';
    [$code, $out, $err] = deploy_run(['-r', $script], ['MOCK_CONFIG' => '/yoq/config.php']);
    eq(0, $code);
    eq('OK:sqlite', $out);
    ok(str_contains($err, 'config/config.php topilmadi'), 'CLI ogohlantirishi yo\'q');
    // Testlar (MOCK_TESTING) jim ishlaydi.
    [, $out, $err] = deploy_run(['-r', $script], ['MOCK_CONFIG' => '/yoq/config.php', 'MOCK_TESTING' => '1']);
    eq('OK:sqlite', $out);
    eq('', $err);
    $source = (string) file_get_contents(APP_ROOT . '/src/bootstrap.php');
    ok(str_contains($source, "'code' => 'config_missing'"), 'veb uchun aniq xato yo\'q');
});

test('api.php: Apache bilmaydigan holat kodlari (419, 425) aniq holat qatori bilan yuboriladi', static function (): void {
    // Apache 2.4 jadvalidagi 4xx/5xx kodlar: mod_php ularni o'zgartirmaydi. Qolganlarini u "500" deb yuboradi.
    $known = array_merge(range(400, 417), [421, 422, 423, 424, 426, 428, 429, 431, 451], range(500, 508), [510, 511]);
    $api = (string) file_get_contents(APP_ROOT . '/public/api.php');
    $used = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_ROOT . '/src', FilesystemIterator::SKIP_DOTS));
    foreach ($files as $f) {
        if (str_ends_with($f->getFilename(), '.php')) {
            preg_match_all('/HttpError\((\d{3})/', (string) file_get_contents($f->getPathname()), $m);
            array_push($used, ...array_map('intval', $m[1]));
        }
    }
    ok(in_array(419, $used, true) && in_array(425, $used, true), 'kodlar topilmadi');
    foreach (array_unique($used) as $code) {
        if (!in_array($code, $known, true)) {
            ok(preg_match('/' . $code . " => '[A-Za-z ]+'/", $api) === 1, "{$code}: api.php send_status() da holat qatori yo'q");
        }
    }
});

test('zaxira: nomlar, bir soniyadagi nusxalar va turlar bo\'yicha tozalash', static function (): void {
    $dir = deploy_tmpdir();
    try {
        $stamp = '20261002-033000';
        $made = [];
        for ($i = 0; $i < 3; $i++) {
            $path = Backup::nextPath($dir, Backup::PREFIX_DB, $stamp);
            touch($path);
            $made[] = basename($path);
        }
        eq(['backup-db-20261002-033000.zip', 'backup-db-20261002-033000-2.zip', 'backup-db-20261002-033000-3.zip'], $made);
        touch($dir . '/backup-20261001-033000.zip');
        touch($dir . '/backup-20261002-033000.zip');
        touch($dir . '/backup-db-qolda.zip');
        touch($dir . '/backup-db-20261001-235959.zip');

        eq(['backup-db-20261002-033000.zip', 'backup-db-20261001-235959.zip'], Backup::prune($dir, Backup::PREFIX_DB, 2));
        // O'chirilgan raqam qayta olinmaydi: keyingi nusxa eng yangisi bo'lib qoladi.
        eq('backup-db-20261002-033000-4.zip', basename(Backup::nextPath($dir, Backup::PREFIX_DB, $stamp)));
        // To'liq nusxalar alohida: faqat baza nusxalari ularni o'chirmaydi.
        eq([], Backup::prune($dir, Backup::PREFIX_FULL, 2));
        eq(['backup-20261001-033000.zip'], Backup::prune($dir, Backup::PREFIX_FULL, 1));
        $left = array_map('basename', glob($dir . '/*.zip') ?: []);
        sort($left);
        eq(['backup-20261002-033000.zip', 'backup-db-20261002-033000-2.zip', 'backup-db-20261002-033000-3.zip', 'backup-db-qolda.zip'], $left);
    } finally {
        deploy_rmdir($dir);
    }
});

test('zaxira: bin/backup.php (SQLite) — to\'liq va faqat baza nusxasi', static function (): void {
    if (!class_exists(ZipArchive::class)) {
        return; // zip kengaytmasi yo'q muhit
    }
    $dir = deploy_tmpdir();
    try {
        $config = $dir . '/config.php';
        file_put_contents($config, "<?php return ['db' => ['driver' => 'sqlite', 'sqlite_path' => " . var_export($dir . '/db.sqlite', true)
            . "], 'storage_path' => " . var_export($dir, true) . "];\n");
        mkdir($dir . '/uploads/audio', 0775, true);
        file_put_contents($dir . '/uploads/audio/sinov.mp3', 'ID3');
        $env = ['MOCK_CONFIG' => $config, 'MOCK_TESTING' => '1'];
        [$code, $out] = deploy_run(['bin/install.php'], $env);
        eq(0, $code, 'install');
        [$code, $out, $err] = deploy_run(['bin/backup.php', '--keep=1'], $env);
        eq(0, $code, "backup: {$err}");
        ok(str_contains($out, 'baza: SQLite, 1 ta fayl'), $out);
        [$code] = deploy_run(['bin/backup.php', '--no-uploads', '--keep=1'], $env);
        eq(0, $code);
        [$code] = deploy_run(['bin/backup.php', '--no-uploads', '--keep=1'], $env);
        eq(0, $code);
        $full = glob($dir . '/backups/backup-[0-9]*.zip') ?: [];
        $db = glob($dir . '/backups/backup-db-*.zip') ?: [];
        eq(1, count($full), "to'liq nusxa faqat baza nusxalari tufayli o'chmasligi kerak");
        eq(1, count($db));
        $zip = new ZipArchive();
        ok($zip->open($full[0]) === true);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();
        sort($names);
        eq(['database.sqlite', 'uploads/audio/sinov.mp3'], $names);
        $zip->open($full[0]);
        file_put_contents($dir . '/check.sqlite', $zip->getFromName('database.sqlite'));
        $zip->close();
        $copy = new PDO('sqlite:' . $dir . '/check.sqlite');
        ok((int) $copy->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'users'")->fetchColumn() === 1, 'nusxada jadvallar yo\'q');
    } finally {
        deploy_rmdir($dir);
    }
});

test('zaxira: PHP dump (MySQL) qayta yuklanganda ma\'lumot aynan tiklanadi', static function (): void {
    if (Db::driver() !== 'mysql') {
        return; // faqat MOCK_TEST_DB=mysql bilan
    }
    $admin = make_user('admin', 'admin');
    $mock = make_mock();
    Db::insert('settings', ['name' => 'sinov', 'value' => "qo'shtirnoq \\ \"q\" ;\nyangi qator; 😀 Ўзбек"]);
    foreach ([123.45678901234567, 0.1 + 0.2, 1e-9, null] as $i => $duration) {
        Db::insert('assets', ['mock_id' => $mock['id'], 'kind' => 'audio', 'file' => "a{$i}.mp3", 'mime' => 'audio/mpeg', 'duration' => $duration, 'created_at' => time()]);
    }
    // Parametr sifatida yuborilgan kasr 14 xonaga yaxlitlanadi — aniq qiymatni SQL matni bilan yozamiz.
    Db::pdo()->exec("UPDATE assets SET duration = 0.30000000000000004 WHERE file = 'a1.mp3'");
    Db::exec('UPDATE users SET last_login_at = NULL WHERE id = ?', [$admin['id']]);
    $snapshot = static function (): array {
        $out = [];
        foreach (Db::pdo()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $out[$table] = Db::all("SELECT * FROM `{$table}` ORDER BY 1");
        }
        return $out;
    };
    $before = $snapshot();
    $dir = deploy_tmpdir();
    try {
        $rows = Backup::phpDump(Db::pdo(), $dir . '/dump.sql');
        ok($rows > 0);
        $sql = (string) file_get_contents($dir . '/dump.sql');
        Db::pdo()->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (array_keys($before) as $table) {
            Db::pdo()->exec("DROP TABLE `{$table}`");
        }
        foreach (explode(";\n", $sql) as $statement) {
            $statement = trim(preg_replace('/^--.*$/m', '', $statement) ?? '');
            if ($statement !== '') {
                Db::pdo()->exec($statement);
            }
        }
        Db::pdo()->exec('SET FOREIGN_KEY_CHECKS = 1');
        eq($before, $snapshot());
        eq(0.1 + 0.2, Db::val("SELECT duration FROM assets WHERE file = 'a1.mp3'"), 'kasr son aniqligi');
    } finally {
        deploy_rmdir($dir);
    }
});

test('o\'rnatish: sxema ikki marta ishga tushirilsa ham buzilmaydi', static function (): void {
    Installer::install();
    Installer::install();
    ok((int) Db::val('SELECT COUNT(*) FROM users') === 0);
});
