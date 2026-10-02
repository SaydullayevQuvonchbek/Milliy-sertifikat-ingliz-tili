<?php

// Namunaviy ma'lumotlar: ikki mock (to'liq va qisqa), ekspert va o'quvchi hisoblari.
//   php bin/seed-demo.php
// Audio va rasmlar vaqtinchalik (signal ovozlari va oddiy rasmlar) — haqiqiy imtihon uchun almashtiring.

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

use App\Db;
use App\Installer;
use App\Seeder;
use App\Util;

/** Oddiy WAV: past ovozli qisqa signallar (audio o'ynayotganini eshitish uchun). */
function demo_wav(float $seconds): string
{
    $rate = 8000;
    $samples = (int) round($seconds * $rate);
    $data = '';
    for ($i = 0; $i < $samples; $i++) {
        $t = $i / $rate;
        $phase = fmod($t, 2.0);
        $value = 128;
        if ($phase < 0.12 || ($t > $seconds - 0.6 && fmod($t, 0.25) < 0.1)) {
            $value = (int) (128 + 40 * sin(2 * M_PI * 660 * $t));
        }
        $data .= chr(max(0, min(255, $value)));
    }
    return 'RIFF' . pack('V', 36 + strlen($data)) . 'WAVE'
        . 'fmt ' . pack('VvvVVvv', 16, 1, 1, $rate, $rate, 1, 8)
        . 'data' . pack('V', strlen($data)) . $data;
}

/** Oddiy PNG rasm (GD bo'lmasa — null). */
function demo_png(string $caption, int $seed): ?string
{
    if (!function_exists('imagecreatetruecolor')) {
        return null;
    }
    $w = 800;
    $h = 500;
    $img = imagecreatetruecolor($w, $h);
    $palettes = [[219, 234, 254], [220, 252, 231], [254, 243, 199], [243, 232, 255]];
    [$r, $g, $b] = $palettes[$seed % count($palettes)];
    imagefill($img, 0, 0, imagecolorallocate($img, $r, $g, $b));
    mt_srand($seed * 97);
    for ($i = 0; $i < 14; $i++) {
        $color = imagecolorallocatealpha($img, mt_rand(60, 200), mt_rand(60, 200), mt_rand(60, 200), 70);
        imagefilledellipse($img, mt_rand(0, $w), mt_rand(0, $h), mt_rand(60, 220), mt_rand(60, 220), $color);
    }
    $dark = imagecolorallocate($img, 15, 27, 51);
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefilledrectangle($img, 0, $h - 70, $w, $h, $dark);
    imagestring($img, 5, 20, $h - 45, $caption, $white);
    imagestring($img, 3, 20, 20, 'Sample image - replace with a real photo', $dark);
    ob_start();
    imagepng($img);
    imagedestroy($img);
    return (string) ob_get_clean();
}

function demo_mock(string $file): int
{
    $def = require $file;
    $files = ['audio' => [], 'image' => []];
    foreach ($def['assets']['audio'] ?? [] as $key => $seconds) {
        $files['audio'][$key] = ['bytes' => demo_wav((float) $seconds), 'ext' => 'wav', 'mime' => 'audio/wav', 'duration' => (float) $seconds, 'name' => "namuna-{$key}.wav"];
    }
    $seed = 1;
    foreach ($def['assets']['image'] ?? [] as $key => $caption) {
        $png = demo_png($caption, $seed++);
        if ($png !== null) {
            $files['image'][$key] = ['bytes' => $png, 'ext' => 'png', 'mime' => 'image/png', 'name' => "namuna-{$key}.png"];
        }
    }

    $result = Seeder::importMock($def, $files);
    if (!$result['created']) {
        echo "Mock allaqachon bor: {$def['title']} (#{$result['id']})\n";
        return $result['id'];
    }
    if ($result['active']) {
        echo "Mock yaratildi va faollashtirildi: {$def['title']} (#{$result['id']})\n";
    } else {
        echo "Mock yaratildi, lekin xatolar bor (qoralama): {$def['title']} (#{$result['id']})\n";
        foreach ($result['errors'] as $e) {
            echo "  - {$e['where']}: {$e['message']}\n";
        }
    }
    foreach ($result['warnings'] as $w) {
        echo "  ! {$w['where']}: {$w['message']}\n";
    }
    return $result['id'];
}

function demo_user(string $role, string $name, string $login, string $password): void
{
    if (Db::one('SELECT id FROM users WHERE login = ?', [Util::normalizeLogin($login)])) {
        return;
    }
    Installer::createUser($role, $name, $login, $password, str_starts_with($login, '+') ? $login : null);
    echo "Hisob: {$role} — login: {$login}, parol: {$password}\n";
}

Installer::install();
demo_mock(__DIR__ . '/../demo/mock-quick.php');
demo_mock(__DIR__ . '/../demo/mock-full.php');
demo_user('expert', 'Ekspert Namuna', 'expert', 'expert123');
demo_user('student', "O'quvchi Namuna", '+998900000001', 'student123');
