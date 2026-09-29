<?php

declare(strict_types=1);

namespace App;

use App\Services\MockService;

/** Namunaviy va tayyor kontentli mocklarni bazaga joylash (bin/seed-demo.php va bin/seed-content.php uchun). */
final class Seeder
{
    /**
     * Mockni yaratish: fayllarni saqlaydi, "@audio:x" / "@image:x" belgilarini haqiqiy identifikatorlarga almashtiradi,
     * tekshiradi va xatosiz bo'lsa faollashtiradi.
     *
     * @param array $def   demo/mock-*.php yoki content/mocks/<slug>/mock.php qaytaradigan tuzilma
     * @param array{audio?: array<string,array>, image?: array<string,array>} $files
     *        har biri: ['bytes' => string, 'ext' => string, 'mime' => string, 'duration' => ?float, 'name' => string]
     * @return array{id:int, created:bool, active:bool, errors:array, warnings:array, summary:array}
     */
    public static function importMock(array $def, array $files, bool $activate = true): array
    {
        $existing = Db::one('SELECT id, status FROM mocks WHERE title = ?', [$def['title']]);
        if ($existing) {
            return ['id' => (int) $existing['id'], 'created' => false, 'active' => $existing['status'] === 'active', 'errors' => [], 'warnings' => [], 'summary' => []];
        }

        $settings = MockService::mergeSettings($def['settings'] ?? []);
        $empty = MockService::compile(MockService::emptySource());
        $id = Db::insert('mocks', [
            'title' => $def['title'],
            'description' => $def['description'] ?? '',
            'status' => 'draft',
            'max_attempts' => 2,
            'source_json' => Util::json(MockService::emptySource()),
            'content_json' => Util::json($empty['content']),
            'key_json' => Util::json($empty['key']),
            'settings_json' => Util::json($settings),
            'stats_json' => '{}',
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        $map = [];
        $durations = [];
        foreach (['audio', 'image'] as $kind) {
            foreach ($files[$kind] ?? [] as $key => $file) {
                $placeholder = '@' . $kind . ':' . $key;
                $map[$placeholder] = self::storeAsset($id, $kind, $file);
                if (isset($file['duration'])) {
                    $durations[$placeholder] = (float) $file['duration'];
                }
            }
        }

        $source = self::replace($def['source'], $map, $durations);
        // Rasm fayli yo'q bo'lsa (masalan GD yo'q), qolib ketgan belgilar olib tashlanadi.
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
        $active = false;
        if ($activate && $validation['errors'] === []) {
            Db::update('mocks', ['status' => 'active'], 'id = ?', [$id]);
            $active = true;
        }
        return [
            'id' => $id,
            'created' => true,
            'active' => $active,
            'errors' => $validation['errors'],
            'warnings' => $validation['warnings'],
            'summary' => $validation['summary'],
        ];
    }

    /**
     * content/mocks/<slug>/ papkasidan fayllarni o'qish (audio/*.mp3 + manifest.json, images/*.png).
     *
     * @return array{0: array{audio: array<string,array>, image: array<string,array>}, 1: string[]} [fayllar, muammolar]
     */
    public static function contentFiles(string $dir, array $def): array
    {
        $slug = basename($dir);
        $files = ['audio' => [], 'image' => []];
        $problems = [];
        $manifestFile = $dir . '/audio/manifest.json';
        $manifest = is_file($manifestFile) ? (json_decode((string) file_get_contents($manifestFile), true) ?: []) : [];

        foreach ($def['assets']['audio'] ?? [] as $key) {
            $path = "{$dir}/audio/{$key}.mp3";
            if (!is_file($path) || !isset($manifest[$key]['duration'])) {
                $problems[] = "audio/{$key}.mp3 yoki manifestdagi davomiylik yo'q (python3 content/tools/synth.py content/mocks/{$slug})";
                continue;
            }
            $files['audio'][$key] = [
                'bytes' => (string) file_get_contents($path), 'ext' => 'mp3', 'mime' => 'audio/mpeg',
                'duration' => (float) $manifest[$key]['duration'], 'name' => "{$slug}-{$key}.mp3",
            ];
        }
        foreach ($def['assets']['image'] ?? [] as $k => $v) {
            $key = is_int($k) ? (string) $v : $k;
            $path = "{$dir}/images/{$key}.png";
            if (!is_file($path)) {
                $problems[] = "images/{$key}.png yo'q (python3 content/tools/render_images.py content/mocks/{$slug})";
                continue;
            }
            $files['image'][$key] = ['bytes' => (string) file_get_contents($path), 'ext' => 'png', 'mime' => 'image/png', 'name' => "{$slug}-{$key}.png"];
        }
        // audio.json da bor, lekin mock.php da ishlatilmagan treklar — ehtimol unutilgan.
        if (is_file($dir . '/audio.json')) {
            $declared = array_keys((json_decode((string) file_get_contents($dir . '/audio.json'), true)['tracks'] ?? []));
            foreach (array_diff($declared, $def['assets']['audio'] ?? []) as $unused) {
                $problems[] = "audio.json dagi '{$unused}' treki mock.php ning assets.audio ro'yxatida yo'q";
            }
        }
        return [$files, $problems];
    }

    /** @param array{bytes:string, ext:string, mime:string, duration?:?float, name:string} $file */
    public static function storeAsset(int $mockId, string $kind, array $file): int
    {
        $name = sprintf('m%d_%s.%s', $mockId, bin2hex(random_bytes(10)), $file['ext']);
        $dir = Config::storagePath($kind === 'audio' ? 'uploads/audio' : 'uploads/images');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($dir . '/' . $name, $file['bytes']);
        return Db::insert('assets', [
            'mock_id' => $mockId,
            'kind' => $kind,
            'file' => $name,
            'original_name' => $file['name'],
            'mime' => $file['mime'],
            'size' => strlen($file['bytes']),
            'duration' => $file['duration'] ?? null,
            'created_at' => time(),
        ]);
    }

    /** Manbadagi "@audio:x" va "@image:x" belgilarini fayl identifikatorlariga almashtirish (davomiylik bilan). */
    public static function replace(mixed $node, array $map, array $durations): mixed
    {
        if (is_array($node)) {
            // Listening treki: 'asset' + 'duration'. Speaking savol ovozi: 'audio' + 'audio_duration'.
            if (isset($node['asset']) && is_string($node['asset']) && isset($map[$node['asset']])) {
                $node['duration'] = $durations[$node['asset']] ?? ($node['duration'] ?? 0);
                $node['asset'] = $map[$node['asset']];
            }
            if (isset($node['audio']) && is_string($node['audio']) && isset($map[$node['audio']])) {
                $node['audio_duration'] = $durations[$node['audio']] ?? ($node['audio_duration'] ?? 0);
                $node['audio'] = $map[$node['audio']];
            }
            foreach ($node as $k => $v) {
                $node[$k] = self::replace($v, $map, $durations);
            }
            return $node;
        }
        if (is_string($node) && isset($map[$node])) {
            return $map[$node];
        }
        return $node;
    }
}
