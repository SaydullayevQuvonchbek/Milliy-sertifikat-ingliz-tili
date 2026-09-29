<?php

// Zaxira nusxa: baza + yuklangan fayllar (audio, rasmlar, Speaking yozuvlari) bitta ZIP faylga.
//
//   php bin/backup.php [--out=/yo'l/papka] [--keep=7] [--no-uploads]
//
// Standart papka: storage/backups (storage/.htaccess orqali veb'dan yopiq). Eski nusxalar --keep gacha saqlanadi.
// SQLite: VACUUM INTO (imtihon davomida ham xavfsiz, izchil nusxa). MySQL: mysqldump kerak.
// Cron misoli (har kecha 03:30):  30 3 * * *  php /yo'l/bin/backup.php --keep=14
//
// Tiklash: ZIP ni oching; database.sqlite ni config'dagi sqlite_path ga (yoki dump.sql ni `mysql` ga) va uploads/ ni
// storage_path ichiga qo'ying.

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Config;
use App\Db;

$options = getopt('', ['out::', 'keep::', 'no-uploads']);
$outDir = (string) ($options['out'] ?? Config::storagePath('backups'));
$keep = max(1, (int) ($options['keep'] ?? 7));
if (!is_dir($outDir) && !mkdir($outDir, 0770, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Papka yaratilmadi: {$outDir}\n");
    exit(1);
}

$stamp = date('Ymd-His');
$tmp = sys_get_temp_dir() . '/mlmock-backup-' . bin2hex(random_bytes(4));
mkdir($tmp, 0770, true);
$dbFile = null;

try {
    if (Db::driver() === 'sqlite') {
        $dbFile = $tmp . '/database.sqlite';
        Db::pdo()->exec('VACUUM INTO ' . Db::pdo()->quote($dbFile));
        $dbName = 'database.sqlite';
    } else {
        $m = (array) Config::get('db.mysql', []);
        $dbFile = $tmp . '/dump.sql';
        $cmd = sprintf(
            'mysqldump --single-transaction --default-character-set=utf8mb4 -h %s -P %d -u %s %s > %s',
            escapeshellarg((string) ($m['host'] ?? 'localhost')),
            (int) ($m['port'] ?? 3306),
            escapeshellarg((string) ($m['username'] ?? '')),
            escapeshellarg((string) ($m['database'] ?? '')),
            escapeshellarg($dbFile)
        );
        putenv('MYSQL_PWD=' . (string) ($m['password'] ?? ''));
        exec($cmd, $unused, $code);
        putenv('MYSQL_PWD');
        if ($code !== 0 || !is_file($dbFile) || filesize($dbFile) === 0) {
            throw new RuntimeException("mysqldump ishlamadi (o'rnatilganini tekshiring). Bazani hosting panelidan eksport qiling.");
        }
        $dbName = 'dump.sql';
    }

    $zipPath = rtrim($outDir, '/') . "/backup-{$stamp}.zip";
    for ($n = 2; is_file($zipPath); $n++) {
        $zipPath = rtrim($outDir, '/') . "/backup-{$stamp}-{$n}.zip";
    }
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
        throw new RuntimeException("ZIP yaratilmadi: {$zipPath}");
    }
    $zip->addFile($dbFile, $dbName);

    $files = 0;
    if (!isset($options['no-uploads'])) {
        $base = Config::storagePath('uploads');
        if (is_dir($base)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if ($file->isFile()) {
                    $zip->addFile($file->getPathname(), 'uploads/' . substr($file->getPathname(), strlen($base) + 1));
                    $files++;
                }
            }
        }
    }
    $zip->close();
    printf("Zaxira tayyor: %s (%.1f MB, baza + %d ta fayl)\n", $zipPath, filesize($zipPath) / 1048576, $files);

    // Eskilarini o'chirish.
    $all = glob(rtrim($outDir, '/') . '/backup-*.zip') ?: [];
    rsort($all);
    foreach (array_slice($all, $keep) as $old) {
        unlink($old);
        echo "Eski nusxa o'chirildi: " . basename($old) . "\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Xatolik: ' . $e->getMessage() . "\n");
    $exit = 1;
} finally {
    foreach (glob($tmp . '/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($tmp);
}
exit($exit ?? 0);
