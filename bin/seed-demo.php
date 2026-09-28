<?php

// Namunaviy ma'lumotlar: ikki mock (to'liq va qisqa), ekspert va o'quvchi hisoblari.
//   php bin/seed-demo.php
// Audio va rasmlar vaqtinchalik (signal ovozlari va oddiy rasmlar) — haqiqiy imtihon uchun almashtiring.

declare(strict_types=1);

if (!defined('APP_ROOT')) {
    require __DIR__ . '/../src/bootstrap.php';
}

use App\Config;
use App\Db;
use App\Installer;
use App\Services\MockService;
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

function demo_asset(int $mockId, string $kind, string $bytes, string $ext, string $mime, ?float $duration, string $name): int
{
    $file = sprintf('m%d_%s.%s', $mockId, bin2hex(random_bytes(10)), $ext);
    $dir = Config::storagePath($kind === 'audio' ? 'uploads/audio' : 'uploads/images');
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents($dir . '/' . $file, $bytes);
    return Db::insert('assets', [
        'mock_id' => $mockId,
        'kind' => $kind,
        'file' => $file,
        'original_name' => $name,
        'mime' => $mime,
        'size' => strlen($bytes),
        'duration' => $duration,
        'created_at' => time(),
    ]);
}

/** Manbadagi "@audio:x" va "@image:x" belgilarini haqiqiy fayl identifikatorlariga almashtirish. */
function demo_replace(mixed $node, array $map, array $durations): mixed
{
    if (is_array($node)) {
        if (isset($node['asset']) && is_string($node['asset']) && isset($map[$node['asset']])) {
            $node['duration'] = $durations[$node['asset']] ?? ($node['duration'] ?? 0);
            $node['asset'] = $map[$node['asset']];
        }
        foreach ($node as $k => $v) {
            $node[$k] = demo_replace($v, $map, $durations);
        }
        return $node;
    }
    if (is_string($node) && isset($map[$node])) {
        return $map[$node];
    }
    return $node;
}

function demo_mock(string $file): int
{
    $def = require $file;
    $existing = Db::one('SELECT id FROM mocks WHERE title = ?', [$def['title']]);
    if ($existing) {
        echo "Mock allaqachon bor: {$def['title']} (#{$existing['id']})\n";
        return (int) $existing['id'];
    }
    $settings = MockService::mergeSettings($def['settings'] ?? []);
    $compiled = MockService::compile(MockService::emptySource());
    $id = Db::insert('mocks', [
        'title' => $def['title'],
        'description' => $def['description'],
        'status' => 'draft',
        'max_attempts' => 2,
        'source_json' => Util::json(MockService::emptySource()),
        'content_json' => Util::json($compiled['content']),
        'key_json' => Util::json($compiled['key']),
        'settings_json' => Util::json($settings),
        'stats_json' => '{}',
        'created_at' => time(),
        'updated_at' => time(),
    ]);

    $map = [];
    $durations = [];
    foreach ($def['assets']['audio'] ?? [] as $key => $seconds) {
        $placeholder = '@audio:' . $key;
        $map[$placeholder] = demo_asset($id, 'audio', demo_wav((float) $seconds), 'wav', 'audio/wav', (float) $seconds, "namuna-{$key}.wav");
        $durations[$placeholder] = (float) $seconds;
    }
    $seed = 1;
    foreach ($def['assets']['image'] ?? [] as $key => $caption) {
        $png = demo_png($caption, $seed++);
        if ($png !== null) {
            $map['@image:' . $key] = demo_asset($id, 'image', $png, 'png', 'image/png', null, "namuna-{$key}.png");
        }
    }

    $source = demo_replace($def['source'], $map, $durations);
    // GD bo'lmasa, rasm belgilari qolib ketmasin.
    foreach ($source['speaking']['parts'] ?? [] as $i => $part) {
        $source['speaking']['parts'][$i]['images'] = array_values(array_filter($part['images'] ?? [], 'is_int'));
    }
    $compiled = MockService::compile($source);
    Db::update('mocks', [
        'source_json' => Util::json($source),
        'content_json' => Util::json($compiled['content']),
        'key_json' => Util::json($compiled['key']),
    ], 'id = ?', [$id]);

    $validation = MockService::validate($source, $settings, MockService::assetIds($id));
    if ($validation['errors'] === []) {
        Db::update('mocks', ['status' => 'active'], 'id = ?', [$id]);
        echo "Mock yaratildi va faollashtirildi: {$def['title']} (#{$id})\n";
    } else {
        echo "Mock yaratildi, lekin xatolar bor (qoralama): {$def['title']} (#{$id})\n";
        foreach ($validation['errors'] as $e) {
            echo "  - {$e['where']}: {$e['message']}\n";
        }
    }
    foreach ($validation['warnings'] as $w) {
        echo "  ! {$w['where']}: {$w['message']}\n";
    }
    return $id;
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
