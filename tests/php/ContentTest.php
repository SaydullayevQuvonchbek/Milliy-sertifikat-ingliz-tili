<?php

declare(strict_types=1);

use App\Config;
use App\Seeder;

/** content/mocks/* — tayyor mocklar: fayllar to'liq, rasmiy format, xatosiz va ogohlantirishsiz. */
test('kontent: tayyor mocklar to\'liq va rasmiy formatga mos', static function (): void {
    $files = glob(APP_ROOT . '/content/mocks/*/mock.php') ?: [];
    ok($files !== [], 'content/mocks ichida mock yo\'q');
    foreach ($files as $file) {
        $dir = dirname($file);
        $slug = basename($dir);
        $def = require $file;

        [$assets, $problems] = Seeder::contentFiles($dir, $def);
        eq([], $problems, "{$slug}: fayllar");

        $result = Seeder::importMock($def, $assets);
        eq([], $result['errors'], "{$slug}: xatolar");
        eq([], $result['warnings'], "{$slug}: ogohlantirishlar");
        ok($result['active'], "{$slug}: faollashmagan");
        eq(['parts' => 6, 'questions' => 35], array_intersect_key($result['summary']['L'], ['parts' => 1, 'questions' => 1]), "{$slug}: Listening");
        eq(['parts' => 5, 'questions' => 35], $result['summary']['R'], "{$slug}: Reading");
        eq(3, $result['summary']['W']['tasks'], "{$slug}: Writing");
        eq(8, $result['summary']['S']['questions'], "{$slug}: Speaking");

        // Listening real uzunlikda bo'lishi kerak (rasmiy imtihon ~ 35–50 daqiqa, har audio 2 marta).
        $minutes = $result['summary']['L']['duration_sec'] / 60;
        ok($minutes >= 30 && $minutes <= 60, sprintf('%s: Listening %.1f daqiqa (30–60 oralig\'ida bo\'lishi kerak)', $slug, $minutes));

        // Har trekning matni (transkript) bor va audio.json bilan bir xil manbadan olingan.
        foreach ($def['source']['listening']['parts'] as $part) {
            foreach ($part['tracks'] as $track) {
                ok(trim((string) $track['transcript']) !== '', "{$slug}: transkript bo'sh");
            }
        }
    }
    // Vaqtinchalik fayllarni tozalash (audio hajmi katta).
    $uploads = Config::storagePath('uploads');
    if (is_dir($uploads)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploads, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
    }
});
