<?php

// O'rnatish: jadvallarni yaratish va administrator qo'shish.
//
//   php bin/install.php --admin-login=admin --admin-password=KuchliParol123 [--admin-name="Ism Familiya"] [--content] [--demo]
//
// --content — content/mocks/ dagi tayyor mocklarni (audio va rasmlari bilan) joylaydi va faollashtiradi.
// --demo    — namunaviy mock va sinov hisoblarini (ekspert, o'quvchi) qo'shadi (faqat sinov uchun).

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Db;
use App\Installer;

$options = getopt('', ['admin-login:', 'admin-password:', 'admin-name:', 'content', 'demo']);

Installer::install();
echo "Jadvallar tayyor (" . Db::driver() . ").\n";

$login = $options['admin-login'] ?? null;
$password = $options['admin-password'] ?? null;
if ($login !== null) {
    if ($password === null || strlen((string) $password) < 8) {
        fwrite(STDERR, "Administrator paroli kamida 8 ta belgidan iborat bo'lishi kerak.\n");
        exit(1);
    }
    $exists = Db::one('SELECT id FROM users WHERE login = ?', [strtolower((string) $login)]);
    if ($exists) {
        Db::exec('UPDATE users SET password_hash = ?, role = ?, status = ? WHERE id = ?', [
            password_hash((string) $password, PASSWORD_DEFAULT), 'admin', 'active', $exists['id'],
        ]);
        echo "Administrator paroli yangilandi: {$login}\n";
    } else {
        Installer::createUser('admin', (string) ($options['admin-name'] ?? 'Administrator'), (string) $login, (string) $password);
        echo "Administrator yaratildi: {$login}\n";
    }
}

if (isset($options['content'])) {
    require __DIR__ . '/seed-content.php';
}

if (isset($options['demo'])) {
    require __DIR__ . '/seed-demo.php';
}
