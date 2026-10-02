<?php

// Tayyor kontentli mocklarni (content/mocks/<slug>/) bazaga joylash.
//
//   php bin/seed-content.php [--only=slug1,slug2] [--no-activate] [--report]
//
// Har bir mock: mock.php (savollar), audio/*.mp3 + audio/manifest.json (tools/synth.py yaratadi),
// images/*.png (tools/render_images.py yaratadi). Mock nomi bazada bor bo'lsa, o'tkazib yuboriladi.
// --report: batafsil hisobot beradi; xato bo'lsa chiqish kodi 1.

declare(strict_types=1);

// Faqat buyruq qatoridan (yoki cron'dan): veb-so'rov orqali ochilsa (masalan, .htaccess ishlamay qolganda) hech narsa
// qilmaydi. Ayrim hostinglarda cron php-cgi bilan ishlaydi — u REQUEST_METHOD siz keladi, shuning uchun to'xtatilmaydi.
if (PHP_SAPI !== 'cli' && isset($_SERVER['REQUEST_METHOD'])) {
    http_response_code(404);
    exit;
}

if (!defined('APP_ROOT')) {
    require __DIR__ . '/../src/bootstrap.php';
}

use App\Installer;
use App\Seeder;

require_once APP_ROOT . '/content/lib.php';

$options = getopt('', ['only::', 'no-activate', 'report']);
$only = isset($options['only']) ? array_filter(explode(',', (string) $options['only'])) : [];
$report = isset($options['report']);

Installer::install();

$dirs = glob(APP_ROOT . '/content/mocks/*/mock.php') ?: [];
sort($dirs);
$exit = 0;
$found = 0;

foreach ($dirs as $file) {
    $dir = dirname($file);
    $slug = basename($dir);
    if ($only !== [] && !in_array($slug, $only, true)) {
        continue;
    }
    $found++;
    $def = require $file;
    [$files, $problems] = Seeder::contentFiles($dir, $def);

    if ($problems !== []) {
        echo "✗ {$slug}: fayllar to'liq emas\n";
        foreach ($problems as $p) {
            echo "    - {$p}\n";
        }
        $exit = 1;
        continue;
    }

    $result = Seeder::importMock($def, $files, !isset($options['no-activate']));
    if (!$result['created']) {
        echo "= {$slug}: allaqachon bor (#{$result['id']}) — {$def['title']}\n";
        continue;
    }
    $state = $result['active'] ? 'faollashtirildi' : 'qoralama';
    echo ($result['errors'] === [] ? '✓' : '✗') . " {$slug}: #{$result['id']} {$state} — {$def['title']}\n";
    if ($report || $result['errors'] !== []) {
        $s = $result['summary'];
        if (isset($s['L'])) {
            printf("    Listening: %d qism, %d savol, %.1f daqiqa\n", $s['L']['parts'], $s['L']['questions'], ($s['L']['duration_sec'] ?? 0) / 60);
        }
        if (isset($s['R'])) {
            printf("    Reading:   %d qism, %d savol\n", $s['R']['parts'], $s['R']['questions']);
        }
        if (isset($s['W'])) {
            printf("    Writing:   %d topshiriq\n", $s['W']['tasks']);
        }
        if (isset($s['S'])) {
            printf("    Speaking:  %d savol\n", $s['S']['questions']);
        }
    }
    foreach ($result['errors'] as $e) {
        echo "    XATO: {$e['where']}: {$e['message']}\n";
        $exit = 1;
    }
    foreach ($result['warnings'] as $w) {
        echo "    ogohlantirish: {$w['where']}: {$w['message']}\n";
        if ($report) {
            // Ogohlantirishlar (imlo, format) ham tuzatilishi kerak: tayyor kontentda ular bo'lmasligi lozim.
            $exit = max($exit, 2);
        }
    }
}

if ($found === 0) {
    echo "content/mocks/ ichida mock topilmadi.\n";
    $exit = 1;
}
if (PHP_SAPI === 'cli' && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'seed-content.php') {
    exit($exit);
}
