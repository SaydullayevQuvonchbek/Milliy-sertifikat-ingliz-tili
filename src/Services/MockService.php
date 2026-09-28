<?php

declare(strict_types=1);

namespace App\Services;

use App\Db;
use App\Http\HttpError;
use App\Settings;
use App\Util;

/**
 * Mock kontenti bilan ishlash.
 *
 * Admin "manba" (source) JSON'ni tahrirlaydi — unda to'g'ri javoblar ham bor.
 * Saqlashda undan ikki qism yasaladi:
 *   content — o'quvchiga yuboriladigan qism (javoblarsiz),
 *   key     — faqat serverda qoladigan kalit.
 */
final class MockService
{
    public const SECTION_NAMES = ['L' => 'Listening', 'R' => 'Reading', 'W' => 'Writing', 'S' => 'Speaking'];
    public const STATUSES = ['draft', 'active', 'frozen', 'archived'];

    public static function defaultSettings(): array
    {
        return [
            'sections' => ['L', 'R', 'W', 'S'],
            'times' => ['reading' => 3600, 'writing' => 3600],
            'break_sec' => 60,
            'lockdown' => [
                'fullscreen' => true,
                'max_violations' => 3,
                'action' => 'terminate',
                'allow_mobile' => false,
                'require_seb' => false,
            ],
            'grading' => ['raters' => 2, 'diff_w' => 3, 'diff_s' => 3],
            'speaking' => ['mode' => 'separate', 'from' => null, 'to' => null],
            'results' => 'publish',
        ];
    }

    public static function emptySource(): array
    {
        return [
            'listening' => ['review_sec' => 120, 'parts' => []],
            'reading' => ['parts' => []],
            'writing' => ['parts' => []],
            'speaking' => ['parts' => []],
        ];
    }

    public static function find(int $id): array
    {
        $mock = Db::one('SELECT * FROM mocks WHERE id = ?', [$id]);
        if ($mock === null) {
            throw new HttpError(404, 'not_found', 'Mock topilmadi.');
        }
        return $mock;
    }

    public static function settings(array $mock): array
    {
        return self::mergeSettings(Util::decode($mock['settings_json'] ?? '{}'));
    }

    public static function mergeSettings(array $input): array
    {
        $defaults = self::defaultSettings();
        $s = array_replace_recursive($defaults, $input);

        $sections = array_values(array_intersect(['L', 'R', 'W', 'S'], (array) ($input['sections'] ?? $defaults['sections'])));
        $s['sections'] = $sections;
        $s['times']['reading'] = self::clampInt($s['times']['reading'] ?? 3600, 60, 4 * 3600);
        $s['times']['writing'] = self::clampInt($s['times']['writing'] ?? 3600, 60, 4 * 3600);
        $s['break_sec'] = self::clampInt($s['break_sec'] ?? 60, 10, 900);
        $s['lockdown']['fullscreen'] = (bool) $s['lockdown']['fullscreen'];
        $s['lockdown']['max_violations'] = self::clampInt($s['lockdown']['max_violations'], 0, 50);
        $s['lockdown']['action'] = in_array($s['lockdown']['action'], ['terminate', 'log'], true) ? $s['lockdown']['action'] : 'terminate';
        $s['lockdown']['allow_mobile'] = (bool) $s['lockdown']['allow_mobile'];
        $s['lockdown']['require_seb'] = (bool) $s['lockdown']['require_seb'];
        $s['grading']['raters'] = in_array((int) $s['grading']['raters'], [1, 2], true) ? (int) $s['grading']['raters'] : 2;
        $s['grading']['diff_w'] = self::clampInt($s['grading']['diff_w'], 1, 16);
        $s['grading']['diff_s'] = self::clampInt($s['grading']['diff_s'], 1, 21);
        $s['speaking']['mode'] = in_array($s['speaking']['mode'], ['separate', 'same_session'], true) ? $s['speaking']['mode'] : 'separate';
        $s['speaking']['from'] = is_numeric($s['speaking']['from'] ?? null) ? (int) $s['speaking']['from'] : null;
        $s['speaking']['to'] = is_numeric($s['speaking']['to'] ?? null) ? (int) $s['speaking']['to'] : null;
        $s['results'] = in_array($s['results'], ['publish', 'instant'], true) ? $s['results'] : 'publish';
        return $s;
    }

    private static function clampInt(mixed $value, int $min, int $max): int
    {
        return max($min, min($max, (int) $value));
    }

    public static function maxAttempts(array $mock): int
    {
        return max(1, min(Settings::attemptsCap(), (int) $mock['max_attempts']));
    }

    /** O'quvchi uchun bo'limlar ketma-ketligi: sozlamada yoqilgan va kontenti bor bo'limlar. */
    public static function sections(array $mock): array
    {
        $content = Util::decode($mock['content_json']);
        $out = [];
        foreach (self::settings($mock)['sections'] as $code) {
            if (self::hasSection($content, $code)) {
                $out[] = $code;
            }
        }
        return $out;
    }

    public static function hasSection(array $content, string $code): bool
    {
        return match ($code) {
            'L' => !empty($content['listening']['parts']),
            'R' => !empty($content['reading']['parts']),
            'W' => !empty($content['writing']['parts']),
            'S' => !empty($content['speaking']['parts']),
            default => false,
        };
    }

    // ---------------------------------------------------------------------
    // Manba → content + key
    // ---------------------------------------------------------------------

    /** @return array{content: array, key: array} */
    public static function compile(array $source): array
    {
        $content = self::emptySource();
        $key = ['L' => [], 'R' => []];

        // Listening
        $listening = (array) ($source['listening'] ?? []);
        $content['listening']['review_sec'] = self::clampInt($listening['review_sec'] ?? 120, 0, 900);
        foreach ((array) ($listening['parts'] ?? []) as $part) {
            $part = (array) $part;
            $tracks = [];
            foreach ((array) ($part['tracks'] ?? []) as $track) {
                $track = (array) $track;
                $tracks[] = [
                    'asset' => isset($track['asset']) ? (int) $track['asset'] : null,
                    'duration' => round(max(0.0, (float) ($track['duration'] ?? 0)), 3),
                    'label' => self::str($track['label'] ?? '', 120),
                ];
            }
            $content['listening']['parts'][] = [
                'title' => self::str($part['title'] ?? '', 120),
                'instructions' => self::str($part['instructions'] ?? '', 2000),
                'preview_sec' => self::clampInt($part['preview_sec'] ?? 20, 0, 300),
                'gap_sec' => self::clampInt($part['gap_sec'] ?? 5, 0, 120),
                'plays' => self::clampInt($part['plays'] ?? 2, 1, 3),
                'tracks' => $tracks,
                'blocks' => self::compileBlocks((array) ($part['blocks'] ?? []), $key['L']),
            ];
        }

        // Reading
        foreach ((array) ($source['reading']['parts'] ?? []) as $part) {
            $part = (array) $part;
            $passage = null;
            if (!empty($part['passage']) && (trim((string) ($part['passage']['text'] ?? '')) !== '')) {
                $passage = [
                    'title' => self::str($part['passage']['title'] ?? '', 200),
                    'text' => self::str($part['passage']['text'] ?? '', 20000),
                ];
            }
            $content['reading']['parts'][] = [
                'title' => self::str($part['title'] ?? '', 120),
                'instructions' => self::str($part['instructions'] ?? '', 2000),
                'passage' => $passage,
                'blocks' => self::compileBlocks((array) ($part['blocks'] ?? []), $key['R']),
            ];
        }

        // Writing
        foreach ((array) ($source['writing']['parts'] ?? []) as $part) {
            $part = (array) $part;
            $tasks = [];
            foreach ((array) ($part['tasks'] ?? []) as $task) {
                $task = (array) $task;
                $tasks[] = [
                    'id' => self::str($task['id'] ?? '', 10),
                    'title' => self::str($task['title'] ?? '', 120),
                    'prompt' => self::str($task['prompt'] ?? '', 5000),
                    'min_words' => self::clampInt($task['min_words'] ?? 0, 0, 1000),
                    'max_words' => self::clampInt($task['max_words'] ?? 0, 0, 2000),
                ];
            }
            $content['writing']['parts'][] = [
                'title' => self::str($part['title'] ?? '', 120),
                'instructions' => self::str($part['instructions'] ?? '', 2000),
                'context' => self::str($part['context'] ?? '', 6000),
                'tasks' => $tasks,
            ];
        }

        // Speaking
        foreach ((array) ($source['speaking']['parts'] ?? []) as $part) {
            $part = (array) $part;
            $questions = [];
            foreach ((array) ($part['questions'] ?? []) as $q) {
                $q = (array) $q;
                $questions[] = [
                    'no' => self::clampInt($q['no'] ?? 0, 0, 50),
                    'text' => self::str($q['text'] ?? '', 2000),
                    'prep_sec' => self::clampInt($q['prep_sec'] ?? 0, 0, 300),
                    'answer_sec' => self::clampInt($q['answer_sec'] ?? 30, 5, 600),
                    'audio' => isset($q['audio']) && $q['audio'] !== null && $q['audio'] !== '' ? (int) $q['audio'] : null,
                    'audio_duration' => round(max(0.0, (float) ($q['audio_duration'] ?? 0)), 3),
                ];
            }
            $content['speaking']['parts'][] = [
                'id' => self::str($part['id'] ?? '', 10),
                'title' => self::str($part['title'] ?? '', 120),
                'instructions' => self::str($part['instructions'] ?? '', 2000),
                'images' => array_values(array_map('intval', array_filter((array) ($part['images'] ?? []), 'is_numeric'))),
                'topic' => self::str($part['topic'] ?? '', 1000),
                'for' => array_values(array_filter(array_map(static fn ($v) => self::str($v, 300), (array) ($part['for'] ?? [])), 'strlen')),
                'against' => array_values(array_filter(array_map(static fn ($v) => self::str($v, 300), (array) ($part['against'] ?? [])), 'strlen')),
                'questions' => $questions,
            ];
        }

        ksort($key['L'], SORT_NUMERIC);
        ksort($key['R'], SORT_NUMERIC);
        return ['content' => $content, 'key' => $key];
    }

    private static function compileBlocks(array $blocks, array &$key): array
    {
        $out = [];
        foreach ($blocks as $block) {
            $block = (array) $block;
            $type = (string) ($block['type'] ?? '');
            switch ($type) {
                case 'text':
                    $out[] = ['type' => 'text', 'text' => self::str($block['text'] ?? '', 10000)];
                    break;

                case 'mcq':
                    $n = (int) ($block['n'] ?? 0);
                    $options = array_values(array_map(static fn ($o) => self::str($o, 1000), (array) ($block['options'] ?? [])));
                    $out[] = ['type' => 'mcq', 'n' => $n, 'prompt' => self::str($block['prompt'] ?? '', 3000), 'options' => $options];
                    $key[(string) $n] = ['type' => 'mcq', 'answer' => strtoupper(self::str($block['answer'] ?? '', 4))];
                    break;

                case 'tfng':
                    $n = (int) ($block['n'] ?? 0);
                    $variant = ($block['variant'] ?? 'tfng') === 'yng' ? 'yng' : 'tfng';
                    $out[] = ['type' => 'tfng', 'n' => $n, 'prompt' => self::str($block['prompt'] ?? '', 3000), 'variant' => $variant];
                    $key[(string) $n] = ['type' => 'tfng', 'answer' => strtoupper(self::str($block['answer'] ?? '', 16))];
                    break;

                case 'match':
                    $options = [];
                    foreach ((array) ($block['options'] ?? []) as $i => $opt) {
                        $opt = is_array($opt) ? $opt : ['text' => $opt];
                        $options[] = [
                            'key' => strtoupper(self::str($opt['key'] ?? chr(65 + (int) $i), 4)),
                            'text' => self::str($opt['text'] ?? '', 1000),
                        ];
                    }
                    $items = [];
                    foreach ((array) ($block['items'] ?? []) as $item) {
                        $item = (array) $item;
                        $n = (int) ($item['n'] ?? 0);
                        $items[] = ['n' => $n, 'prompt' => self::str($item['prompt'] ?? '', 3000)];
                        $key[(string) $n] = ['type' => 'match', 'answer' => strtoupper(self::str($item['answer'] ?? '', 4))];
                    }
                    $out[] = [
                        'type' => 'match',
                        'title' => self::str($block['title'] ?? '', 500),
                        'options' => $options,
                        'items' => $items,
                    ];
                    break;

                case 'gap_text':
                    $text = self::str($block['text'] ?? '', 10000);
                    $maxWords = self::clampInt($block['max_words'] ?? 1, 1, 5);
                    $answers = (array) ($block['answers'] ?? []);
                    foreach (self::gapNumbers($text) as $n) {
                        $key[(string) $n] = [
                            'type' => 'gap',
                            'answer' => self::str($answers[(string) $n] ?? '', 500),
                            'max_words' => $maxWords,
                        ];
                    }
                    $out[] = [
                        'type' => 'gap_text',
                        'title' => self::str($block['title'] ?? '', 500),
                        'text' => $text,
                        'max_words' => $maxWords,
                    ];
                    break;
            }
        }
        return $out;
    }

    /** @return int[] Matndagi [[n]] belgilarining raqamlari. */
    public static function gapNumbers(string $text): array
    {
        preg_match_all('/\[\[\s*(\d{1,3})\s*\]\]/', $text, $m);
        return array_map('intval', $m[1]);
    }

    private static function str(mixed $value, int $max): string
    {
        return trim(Util::cleanText($value, $max));
    }

    // ---------------------------------------------------------------------
    // Listening vaqt jadvali (frontend'dagi timeline.js bilan bir xil qoida)
    // ---------------------------------------------------------------------

    /** Listening bo'limining umumiy davomiyligi, millisoniyada. */
    public static function listeningDurationMs(array $listening): int
    {
        $parts = (array) ($listening['parts'] ?? []);
        $total = 0;
        $count = count($parts);
        foreach (array_values($parts) as $index => $part) {
            $total += (int) ($part['preview_sec'] ?? 0) * 1000;
            $plays = max(1, (int) ($part['plays'] ?? 1));
            $gap = (int) ($part['gap_sec'] ?? 0) * 1000;
            $segments = 0;
            foreach ((array) ($part['tracks'] ?? []) as $track) {
                $duration = (int) round(((float) ($track['duration'] ?? 0)) * 1000);
                for ($p = 0; $p < $plays; $p++) {
                    $total += $duration;
                    $segments++;
                }
            }
            if ($segments > 1) {
                $total += ($segments - 1) * $gap;
            }
            if ($index < $count - 1) {
                $total += $gap;
            }
        }
        $total += (int) ($listening['review_sec'] ?? 0) * 1000;
        return $total;
    }

    // ---------------------------------------------------------------------
    // Tekshirish: xatolar (faollashtirishni to'sadi) va ogohlantirishlar
    // ---------------------------------------------------------------------

    /** @return array{errors: array<int,array>, warnings: array<int,array>, summary: array} */
    public static function validate(array $source, array $settings, array $assetIds = []): array
    {
        $errors = [];
        $warnings = [];
        $compiled = self::compile($source);
        $content = $compiled['content'];
        $key = $compiled['key'];
        $sections = $settings['sections'] ?? ['L', 'R', 'W', 'S'];
        $summary = [];

        $add = static function (array &$list, string $where, string $message): void {
            $list[] = ['where' => $where, 'message' => $message];
        };

        if ($sections === []) {
            $add($errors, 'Sozlamalar', "Kamida bitta bo'lim tanlanishi kerak.");
        }

        foreach (['L' => 'listening', 'R' => 'reading'] as $code => $name) {
            if (!in_array($code, $sections, true)) {
                continue;
            }
            $label = self::SECTION_NAMES[$code];
            $parts = $content[$name]['parts'];
            if ($parts === []) {
                $add($errors, $label, "Bo'limda qism yo'q.");
                continue;
            }
            $numbers = [];
            foreach ($parts as $pi => $part) {
                $where = $label . ', ' . ($part['title'] !== '' ? $part['title'] : ($pi + 1) . '-qism');
                if ($code === 'L') {
                    if ($part['tracks'] === []) {
                        $add($errors, $where, 'Audio fayl biriktirilmagan.');
                    }
                    foreach ($part['tracks'] as $ti => $track) {
                        if (empty($track['asset'])) {
                            $add($errors, $where, ($ti + 1) . '-audio: fayl tanlanmagan.');
                        } elseif ($assetIds !== [] && !in_array($track['asset'], $assetIds, true)) {
                            $add($errors, $where, ($ti + 1) . '-audio: fayl topilmadi (o\'chirilgan bo\'lishi mumkin).');
                        }
                        if ($track['duration'] <= 0) {
                            $add($errors, $where, ($ti + 1) . '-audio: davomiylik aniqlanmagan.');
                        }
                    }
                }
                if ($code === 'R' && $part['passage'] === null && !self::hasBlockType($part['blocks'], 'gap_text')) {
                    $add($warnings, $where, "Matn (passage) kiritilmagan.");
                }
                if ($part['blocks'] === []) {
                    $add($errors, $where, "Savollar yo'q.");
                }
                foreach ($part['blocks'] as $block) {
                    foreach (self::blockNumbers($block) as $n) {
                        $numbers[] = $n;
                    }
                    self::validateBlock($block, $where, $errors, $warnings, $add);
                }
            }

            $counts = array_count_values($numbers);
            foreach ($counts as $n => $c) {
                if ($c > 1) {
                    $add($errors, $label, "{$n}-savol raqami {$c} marta ishlatilgan.");
                }
            }
            $unique = array_keys($counts);
            sort($unique);
            $expected = $unique === [] ? [] : range(1, max($unique));
            $missing = array_diff($expected, $unique);
            if ($missing !== []) {
                $add($errors, $label, 'Savol raqamlari uzluksiz emas, tushib qolgan: ' . implode(', ', $missing) . '.');
            }
            foreach ($key[$code] as $n => $item) {
                if (trim((string) $item['answer']) === '') {
                    $add($errors, $label, "{$n}-savolga to'g'ri javob kiritilmagan.");
                }
            }
            $total = count($unique);
            $summary[$code] = ['parts' => count($parts), 'questions' => $total];
            if ($total !== 35) {
                $add($warnings, $label, "Rasmiy formatda 35 ta savol bor, bu yerda {$total} ta.");
            }
            $officialParts = $code === 'L' ? 6 : 5;
            if (count($parts) !== $officialParts) {
                $add($warnings, $label, "Rasmiy formatda {$officialParts} ta qism bor, bu yerda " . count($parts) . ' ta.');
            }
            if ($code === 'L') {
                $summary['L']['duration_sec'] = (int) round(self::listeningDurationMs($content['listening']) / 1000);
            }
        }

        if (in_array('W', $sections, true)) {
            $tasks = [];
            foreach ($content['writing']['parts'] as $part) {
                foreach ($part['tasks'] as $task) {
                    $tasks[] = $task;
                    $where = 'Writing, ' . ($task['id'] !== '' ? $task['id'] . '-topshiriq' : 'topshiriq');
                    if ($task['id'] === '') {
                        $add($errors, $where, 'Topshiriq raqami (1.1, 1.2 yoki 2) kiritilmagan.');
                    }
                    if ($task['prompt'] === '') {
                        $add($errors, $where, 'Topshiriq matni kiritilmagan.');
                    }
                }
            }
            $ids = array_column($tasks, 'id');
            if (count($ids) !== count(array_unique($ids))) {
                $add($errors, 'Writing', 'Topshiriq raqamlari takrorlangan.');
            }
            if ($tasks === []) {
                $add($errors, 'Writing', "Topshiriqlar yo'q.");
            } elseif ($ids !== ['1.1', '1.2', '2']) {
                $add($warnings, 'Writing', 'Rasmiy formatda 3 ta topshiriq bor: 1.1, 1.2 va 2.');
            }
            $summary['W'] = ['tasks' => count($tasks)];
        }

        if (in_array('S', $sections, true)) {
            $questions = [];
            foreach ($content['speaking']['parts'] as $part) {
                foreach ($part['questions'] as $q) {
                    $questions[] = $q;
                    if ($q['text'] === '') {
                        $add($errors, 'Speaking, ' . $q['no'] . '-savol', 'Savol matni kiritilmagan.');
                    }
                }
                if (in_array($part['id'], ['1.2', '2'], true) && $part['images'] === []) {
                    $add($warnings, 'Speaking, ' . $part['id'], 'Rasmiy formatda bu qismda rasm bor.');
                }
                if ($part['id'] === '3' && ($part['for'] === [] || $part['against'] === [])) {
                    $add($warnings, 'Speaking, 3', "FOR va AGAINST ro'yxatlari to'ldirilmagan.");
                }
            }
            if ($questions === []) {
                $add($errors, 'Speaking', "Savollar yo'q.");
            } else {
                $nos = array_column($questions, 'no');
                if ($nos !== range(1, count($nos))) {
                    $add($errors, 'Speaking', 'Savollar 1 dan boshlab ketma-ket raqamlanishi kerak.');
                }
                if (count($questions) !== 8) {
                    $add($warnings, 'Speaking', 'Rasmiy formatda 8 ta savol bor, bu yerda ' . count($questions) . ' ta.');
                }
            }
            $summary['S'] = ['questions' => count($questions)];
        }

        foreach (self::lint($content) as $issue) {
            $warnings[] = $issue;
        }

        return ['errors' => $errors, 'warnings' => $warnings, 'summary' => $summary];
    }

    private static function hasBlockType(array $blocks, string $type): bool
    {
        foreach ($blocks as $block) {
            if (($block['type'] ?? '') === $type) {
                return true;
            }
        }
        return false;
    }

    /** @return int[] */
    public static function blockNumbers(array $block): array
    {
        return match ($block['type'] ?? '') {
            'mcq', 'tfng' => [(int) $block['n']],
            'match' => array_map(static fn ($i) => (int) $i['n'], $block['items']),
            'gap_text' => self::gapNumbers((string) $block['text']),
            default => [],
        };
    }

    private static function validateBlock(array $block, string $where, array &$errors, array &$warnings, callable $add): void
    {
        switch ($block['type']) {
            case 'mcq':
                $w = $where . ', ' . $block['n'] . '-savol';
                if ($block['n'] <= 0) {
                    $add($errors, $where, 'Test savoliga raqam berilmagan.');
                }
                if (count($block['options']) < 2) {
                    $add($errors, $w, "Kamida 2 ta variant bo'lishi kerak.");
                }
                foreach ($block['options'] as $i => $option) {
                    if ($option === '') {
                        $add($errors, $w, chr(65 + $i) . ' varianti bo\'sh.');
                    }
                }
                break;
            case 'tfng':
                if ($block['n'] <= 0) {
                    $add($errors, $where, 'True/False/Not Given savoliga raqam berilmagan.');
                }
                if ($block['prompt'] === '') {
                    $add($errors, $where . ', ' . $block['n'] . '-savol', "Savol matni bo'sh.");
                }
                break;
            case 'match':
                if (count($block['options']) < 2) {
                    $add($errors, $where, "Moslashtirishda kamida 2 ta variant bo'lishi kerak.");
                }
                $keys = array_column($block['options'], 'key');
                if (count($keys) !== count(array_unique($keys))) {
                    $add($errors, $where, 'Moslashtirish variantlarining harflari takrorlangan.');
                }
                if ($block['items'] === []) {
                    $add($errors, $where, "Moslashtirishda savollar yo'q.");
                }
                break;
            case 'gap_text':
                if (self::gapNumbers($block['text']) === []) {
                    $add($errors, $where, "Bo'sh joy to'ldirish matnida [[raqam]] belgisi yo'q.");
                }
                break;
        }
    }

    // ---------------------------------------------------------------------
    // Imlo va yozuv xatolarini aniqlash (lint)
    // ---------------------------------------------------------------------

    /** @return array<int, array{where:string,message:string}> */
    public static function lint(array $content): array
    {
        $issues = [];
        foreach (self::texts($content) as [$where, $text]) {
            foreach (self::lintText($text) as $message) {
                $issues[] = ['where' => $where, 'message' => $message];
            }
        }
        return $issues;
    }

    /** @return string[] */
    public static function lintText(string $text): array
    {
        $found = [];
        if ($text === '') {
            return $found;
        }
        $plain = preg_replace('/\[\[\s*\d+\s*\]\]/', '___', $text) ?? $text;

        if (preg_match_all('/\b[\p{L}]*(?:\p{Latin}\p{Cyrillic}|\p{Cyrillic}\p{Latin})[\p{L}]*\b/u', $plain, $m)) {
            $found[] = "Lotin va kirill harflari aralash so'z: " . self::quoteList($m[0]) . '.';
        }
        if (preg_match_all('/\b(\p{L}+)\s+\1\b/iu', $plain, $m)) {
            $words = array_filter($m[1], static fn ($w) => !in_array(mb_strtolower($w), ['had', 'that'], true));
            if ($words !== []) {
                $found[] = "Takrorlangan so'z: " . self::quoteList(array_map(static fn ($w) => $w . ' ' . $w, $words)) . '.';
            }
        }
        if (preg_match('/[^\S\n]{2,}/u', $plain)) {
            $found[] = "Ketma-ket ikki yoki undan ortiq bo'sh joy bor.";
        }
        if (preg_match_all('/\p{L}+[^\S\n]+[,.;:!?](?=\s|$)/u', $plain, $m)) {
            $found[] = "Tinish belgisidan oldin bo'sh joy bor: " . self::quoteList($m[0]) . '.';
        }
        if (preg_match('/[,;](?=\p{L})/u', $plain)) {
            $found[] = "Vergul yoki nuqtali verguldan keyin bo'sh joy yo'q.";
        }
        if (preg_match('/(?<!\.)\.\.(?!\.)|,,|\?\?|!!/u', $plain)) {
            $found[] = 'Tinish belgisi takrorlangan (.., ,, ?? yoki !!).';
        }
        if (preg_match('/[.!?]\s+\p{Ll}/u', $plain) && !preg_match('/\b(?:e\.g|i\.e|etc|a\.m|p\.m|approx|No)\.\s+\p{Ll}/u', $plain)) {
            $found[] = 'Gap kichik harf bilan boshlangan (nuqtadan keyin).';
        }
        if (substr_count($plain, '(') !== substr_count($plain, ')')) {
            $found[] = 'Qavslar soni mos emas.';
        }
        if (substr_count($plain, '"') % 2 !== 0) {
            $found[] = "Qo'shtirnoqlar soni juft emas.";
        }
        if (preg_match('/\b(?:TODO|lorem ipsum|xxx)\b/iu', $plain)) {
            $found[] = "Vaqtinchalik matn qolib ketgan (TODO, lorem ipsum yoki xxx).";
        }
        if (preg_match("/\t/", $plain)) {
            $found[] = 'Matnda tabulyatsiya belgisi bor.';
        }
        return $found;
    }

    private static function quoteList(array $items): string
    {
        $items = array_values(array_unique($items));
        $shown = array_slice($items, 0, 4);
        $out = implode(', ', array_map(static fn ($s) => '"' . trim($s) . '"', $shown));
        return count($items) > 4 ? $out . ' va boshqalar' : $out;
    }

    /** @return array<int, array{0:string,1:string}> */
    private static function texts(array $content): array
    {
        $out = [];
        foreach (['listening' => 'Listening', 'reading' => 'Reading'] as $name => $label) {
            foreach ($content[$name]['parts'] as $pi => $part) {
                $where = $label . ', ' . ($part['title'] !== '' ? $part['title'] : ($pi + 1) . '-qism');
                $out[] = [$where . ' (ko\'rsatma)', $part['instructions']];
                if (!empty($part['passage'])) {
                    $out[] = [$where . ' (matn sarlavhasi)', $part['passage']['title']];
                    $out[] = [$where . ' (matn)', $part['passage']['text']];
                }
                foreach ($part['blocks'] as $block) {
                    switch ($block['type']) {
                        case 'text':
                            $out[] = [$where, $block['text']];
                            break;
                        case 'mcq':
                            $out[] = [$where . ', ' . $block['n'] . '-savol', $block['prompt']];
                            foreach ($block['options'] as $i => $opt) {
                                $out[] = [$where . ', ' . $block['n'] . '-savol, ' . chr(65 + $i), $opt];
                            }
                            break;
                        case 'tfng':
                            $out[] = [$where . ', ' . $block['n'] . '-savol', $block['prompt']];
                            break;
                        case 'match':
                            $out[] = [$where . ' (moslashtirish sarlavhasi)', $block['title']];
                            foreach ($block['options'] as $opt) {
                                $out[] = [$where . ', variant ' . $opt['key'], $opt['text']];
                            }
                            foreach ($block['items'] as $item) {
                                $out[] = [$where . ', ' . $item['n'] . '-savol', $item['prompt']];
                            }
                            break;
                        case 'gap_text':
                            $out[] = [$where . ' (bo\'sh joyli matn sarlavhasi)', $block['title']];
                            $out[] = [$where . ' (bo\'sh joyli matn)', $block['text']];
                            break;
                    }
                }
            }
        }
        foreach ($content['writing']['parts'] as $part) {
            $out[] = ['Writing, ' . $part['title'] . ' (kirish matni)', $part['context']];
            foreach ($part['tasks'] as $task) {
                $out[] = ['Writing, ' . $task['id'] . '-topshiriq', $task['prompt']];
            }
        }
        foreach ($content['speaking']['parts'] as $part) {
            $out[] = ['Speaking, ' . $part['id'] . ' (mavzu)', $part['topic']];
            foreach ($part['questions'] as $q) {
                $out[] = ['Speaking, ' . $q['no'] . '-savol', $q['text']];
            }
            foreach (array_merge($part['for'], $part['against']) as $line) {
                $out[] = ['Speaking, ' . $part['id'] . " (ro'yxat)", $line];
            }
        }
        return array_values(array_filter($out, static fn ($pair) => $pair[1] !== ''));
    }

    // ---------------------------------------------------------------------
    // Saqlash
    // ---------------------------------------------------------------------

    public static function save(?int $id, array $data, int $userId): array
    {
        $title = trim(Util::cleanText($data['title'] ?? '', 190));
        if ($title === '') {
            throw new HttpError(422, 'validation', 'Mock nomini kiriting.');
        }
        $source = is_array($data['source'] ?? null) ? $data['source'] : self::emptySource();
        $settings = self::mergeSettings(is_array($data['settings'] ?? null) ? $data['settings'] : []);
        $compiled = self::compile($source);
        $maxAttempts = max(1, min(Settings::attemptsCap(), (int) ($data['max_attempts'] ?? 2)));
        $availableFrom = is_numeric($data['available_from'] ?? null) ? (int) $data['available_from'] : null;
        $availableTo = is_numeric($data['available_to'] ?? null) ? (int) $data['available_to'] : null;
        if ($availableFrom !== null && $availableTo !== null && $availableTo <= $availableFrom) {
            throw new HttpError(422, 'validation', "Tugash vaqti boshlanish vaqtidan keyin bo'lishi kerak.");
        }

        $row = [
            'title' => $title,
            'description' => trim(Util::cleanText($data['description'] ?? '', 2000)),
            'max_attempts' => $maxAttempts,
            'source_json' => Util::json($source),
            'content_json' => Util::json($compiled['content']),
            'key_json' => Util::json($compiled['key']),
            'settings_json' => Util::json($settings),
            'available_from' => $availableFrom,
            'available_to' => $availableTo,
            'updated_at' => time(),
        ];

        return Db::tx(static function () use ($id, $row, $userId): array {
            if ($id === null) {
                $row += ['status' => 'draft', 'stats_json' => '{}', 'created_by' => $userId, 'created_at' => time()];
                $newId = Db::insert('mocks', $row);
                return ['id' => $newId, 'key_changed' => false];
            }
            $mock = self::find($id);
            if ($mock['status'] === 'active') {
                $validation = self::validate(Util::decode($row['source_json']), Util::decode($row['settings_json']), self::assetIds($id));
                if ($validation['errors'] !== []) {
                    throw new HttpError(422, 'validation', "Faol mockda xatolik qoldirib bo'lmaydi. Avval xatolarni tuzating yoki mockni muzlating.", ['validation' => $validation]);
                }
            }
            $keyChanged = $mock['key_json'] !== $row['key_json'];
            Db::update('mocks', $row, 'id = ?', [$id]);
            return ['id' => $id, 'key_changed' => $keyChanged];
        });
    }

    /** @return int[] */
    public static function assetIds(int $mockId): array
    {
        return array_map('intval', array_column(Db::all('SELECT id FROM assets WHERE mock_id = ?', [$mockId]), 'id'));
    }

    public static function setStatus(int $id, string $status, int $userId): array
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new HttpError(422, 'validation', "Noma'lum holat.");
        }
        $mock = self::find($id);
        if ($status === 'active') {
            $validation = self::validate(Util::decode($mock['source_json']), self::settings($mock), self::assetIds($id));
            if ($validation['errors'] !== []) {
                throw new HttpError(422, 'validation', 'Mockni faollashtirishdan oldin xatolarni tuzating.', ['validation' => $validation]);
            }
        }
        Db::update('mocks', ['status' => $status, 'updated_at' => time()], 'id = ?', [$id]);
        \App\Audit::log($userId, 'mock_status', 'mock:' . $id, ['from' => $mock['status'], 'to' => $status]);
        return self::find($id);
    }
}
