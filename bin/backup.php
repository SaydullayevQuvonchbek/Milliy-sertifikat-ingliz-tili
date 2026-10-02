<?php

// Zaxira nusxa: baza + yuklangan fayllar (audio, rasmlar, Speaking yozuvlari) bitta ZIP faylga.
//
//   php bin/backup.php [--out=/yo'l/papka] [--keep=7] [--no-uploads] [--mysqldump=/yo'l/mysqldump] [--php-dump]
//
// Standart papka: storage/backups (storage/.htaccess orqali veb'dan yopiq).
//   to'liq nusxa:               backup-<sana>.zip     (baza + uploads/)
//   faqat baza (--no-uploads):  backup-db-<sana>.zip
// Har tur o'zicha saqlanadi: eng yangi --keep tasi qoladi, eskilari o'chiriladi (bir tur ikkinchisini o'chirmaydi).
//
// SQLite: VACUUM INTO (imtihon davomida ham xavfsiz, izchil nusxa).
// MySQL/MariaDB: mysqldump (yoki mariadb-dump) bo'lsa — u bilan; topilmasa yoki ishlamasa, PHP'ning o'zi bitta
// izchil tranzaksiyada SQL dump yozadi (hostingda exec() o'chiq bo'lsa ham ishlaydi). --php-dump — har doim PHP.
//
// Cron misollari: har kecha faqat baza, har yakshanba to'liq nusxa:
//   30 3 * * *  php /yo'l/bin/backup.php --no-uploads --keep=14
//   45 3 * * 0  php /yo'l/bin/backup.php --keep=4
//
// Tiklash: ZIP ni oching; database.sqlite ni config'dagi sqlite_path ga (yoki dump.sql ni phpMyAdmin / `mysql` orqali
// bazaga) va uploads/ ni storage_path ichiga qo'ying.

declare(strict_types=1);

// Faqat buyruq qatoridan (yoki cron'dan): veb-so'rov orqali ochilsa (masalan, .htaccess ishlamay qolganda) hech narsa
// qilmaydi. Ayrim hostinglarda cron php-cgi bilan ishlaydi — u REQUEST_METHOD siz keladi, shuning uchun to'xtatilmaydi.
if (PHP_SAPI !== 'cli' && isset($_SERVER['REQUEST_METHOD'])) {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../src/bootstrap.php';

use App\Config;
use App\Db;
use App\Services\Backup;

/** Xabarlar: CLI'da STDOUT/STDERR, php-cgi cron'da oddiy chiqish. */
function backup_say(string $text, bool $error = false): void
{
    if ($error && defined('STDERR')) {
        fwrite(STDERR, $text . "\n");
    } else {
        echo $text . "\n";
    }
}

if (!class_exists(ZipArchive::class)) {
    backup_say("PHP'ning zip kengaytmasi yo'q — hosting panelidagi PHP kengaytmalari ro'yxatida \"zip\" ni yoqing.", true);
    exit(1);
}

$options = getopt('', ['out::', 'keep::', 'no-uploads', 'mysqldump::', 'php-dump']);
$outDir = rtrim((string) ($options['out'] ?? Config::storagePath('backups')), '/\\');
$keep = max(1, (int) ($options['keep'] ?? 7));
$dbOnly = isset($options['no-uploads']);
if (!is_dir($outDir) && !@mkdir($outDir, 0770, true) && !is_dir($outDir)) {
    backup_say("Papka yaratilmadi: {$outDir}", true);
    exit(1);
}

$tmp = sys_get_temp_dir() . '/mlmock-backup-' . bin2hex(random_bytes(4));
mkdir($tmp, 0770, true);
$exit = 0;

try {
    if (Db::driver() === 'sqlite') {
        $dbFile = $tmp . '/database.sqlite';
        Db::pdo()->exec('VACUUM INTO ' . Db::pdo()->quote($dbFile));
        $dbName = 'database.sqlite';
        $dbNote = 'SQLite';
    } else {
        $dbFile = $tmp . '/dump.sql';
        $dbName = 'dump.sql';
        $reason = null;
        if (!isset($options['php-dump'])) {
            $binary = isset($options['mysqldump']) && $options['mysqldump'] !== false
                ? (string) $options['mysqldump']
                : Backup::findBinary(['mysqldump', 'mariadb-dump']);
            if ($binary === null) {
                $reason = 'mysqldump topilmadi';
            } elseif (!@is_executable($binary)) {
                $reason = "mysqldump ishga tushmaydi: {$binary}";
            } else {
                $reason = Backup::mysqldump($binary, (array) Config::get('db.mysql', []), $dbFile);
                $dbNote = 'mysqldump';
            }
        }
        if (isset($options['php-dump']) || $reason !== null) {
            if ($reason !== null) {
                backup_say("Eslatma: {$reason} — baza PHP orqali eksport qilinadi.");
            }
            $rows = Backup::phpDump(Db::pdo(), $dbFile);
            $dbNote = "PHP dump, {$rows} ta qator";
        }
    }

    $prefix = $dbOnly ? Backup::PREFIX_DB : Backup::PREFIX_FULL;
    $zipPath = Backup::nextPath($outDir, $prefix, date('Ymd-His'));
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
        throw new RuntimeException("ZIP yaratilmadi: {$zipPath}");
    }
    $zip->addFile($dbFile, $dbName);

    $files = 0;
    if (!$dbOnly) {
        $base = rtrim(Config::storagePath('uploads'), '/\\');
        if (is_dir($base)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if ($file->isFile()) {
                    // ZIP ichida doim "/" (Windows'da olingan nusxa ham Linux serverda to'g'ri ochilsin).
                    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
                    $zip->addFile($file->getPathname(), 'uploads/' . $relative);
                    $files++;
                }
            }
        }
    }
    if (!$zip->close()) {
        throw new RuntimeException("ZIP yozilmadi: {$zipPath}");
    }
    backup_say(sprintf(
        'Zaxira tayyor: %s (%.1f MB; baza: %s%s)',
        $zipPath,
        filesize($zipPath) / 1048576,
        $dbNote,
        $dbOnly ? '' : ", {$files} ta fayl"
    ));
    foreach (Backup::prune($outDir, $prefix, $keep) as $old) {
        backup_say("Eski nusxa o'chirildi: {$old}");
    }
} catch (Throwable $e) {
    backup_say('Xatolik: ' . $e->getMessage(), true);
    $exit = 1;
} finally {
    foreach (glob($tmp . '/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($tmp);
}
exit($exit);
