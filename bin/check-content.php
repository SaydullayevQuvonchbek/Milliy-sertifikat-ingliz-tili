<?php

// Kontentni vaqtinchalik bazada tekshirish (asosiy bazaga tegmaydi).
//
//   php bin/check-content.php <slug> [<slug> ...]      (slug bo'lmasa — barcha mocklar)
//
// Chiqish kodi: 0 — xato ham, ogohlantirish ham yo'q; 1 — xato bor; 2 — faqat ogohlantirishlar (imlo/format).

declare(strict_types=1);

$root = dirname(__DIR__);
$dir = sys_get_temp_dir() . '/mlmock-check-' . bin2hex(random_bytes(4));
mkdir($dir, 0775, true);
$config = $dir . '/config.php';
file_put_contents($config, "<?php return [\n  'db' => ['driver' => 'sqlite', 'sqlite_path' => " . var_export($dir . '/db.sqlite', true) . "],\n"
    . "  'storage_path' => " . var_export($dir, true) . ",\n  'secure_cookies' => false,\n  'debug' => true,\n];\n");

$slugs = array_slice($argv, 1);
$args = ['--report'];
if ($slugs !== []) {
    $args[] = '--only=' . implode(',', $slugs);
}
$cmd = 'MOCK_CONFIG=' . escapeshellarg($config) . ' MOCK_TESTING=1 php ' . escapeshellarg($root . '/bin/seed-content.php') . ' ' . implode(' ', array_map('escapeshellarg', $args));
passthru($cmd, $code);

// Vaqtinchalik papkani tozalash.
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($it as $f) {
    $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
}
rmdir($dir);
exit($code);
