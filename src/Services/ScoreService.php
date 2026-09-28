<?php

declare(strict_types=1);

namespace App\Services;

use App\Db;
use App\Http\HttpError;
use App\Util;

/** Urinish ballarini hisoblash va mock bo'yicha Rasch tahlili. */
final class ScoreService
{
    public const MIN_RASCH_PERSONS = 10;

    public static function recompute(array $a, ?array $mock = null): array
    {
        $mock ??= MockService::find((int) $a['mock_id']);
        $settings = MockService::settings($mock);
        $key = Util::decode($mock['key_json']);
        $stats = Util::decode($mock['stats_json']);
        $answers = Util::decode($a['answers_json']);
        $meta = Util::decode($a['meta_json']);
        $sequence = AttemptService::sequence($a);

        $changes = [];
        $scores = [];
        $method = 'provisional';

        foreach (['L' => 'l', 'R' => 'r'] as $code => $prefix) {
            $changes[$prefix . '_raw'] = null;
            $changes[$prefix . '_theta'] = null;
            $changes[$prefix . '_score'] = null;
            if (!in_array($code, $sequence, true)) {
                continue;
            }
            if (!isset($meta['sections'][$code]['finished_ms'])) {
                $scores[] = null;
                continue;
            }
            $result = Scoring::scoreSection($key[$code] ?? [], $answers[$code] ?? []);
            $changes[$prefix . '_raw'] = $result['raw'];
            $rasch = $stats['rasch'][$code] ?? null;
            if (is_array($rasch) && (float) ($rasch['sigma'] ?? 0) > 0 && !empty($rasch['items'])) {
                $theta = Rasch::personAbility($result['items'], $rasch['items']);
                $changes[$prefix . '_theta'] = $theta;
                $changes[$prefix . '_score'] = Scoring::raschTo75($theta, (float) $rasch['mu'], (float) $rasch['sigma']);
                $method = 'rasch';
            } else {
                $changes[$prefix . '_score'] = Scoring::provisional75($result['raw'], max(1, $result['total']));
            }
            $scores[] = $changes[$prefix . '_score'];
        }

        foreach (['W' => 'w', 'S' => 's'] as $code => $prefix) {
            $changes[$prefix . '_raw'] = null;
            $changes[$prefix . '_score'] = null;
            if (!in_array($code, $sequence, true)) {
                continue;
            }
            $finished = $code === 'W'
                ? isset($meta['sections']['W']['finished_ms'])
                : isset($meta['speaking']['finished_ms']);
            $raw = null;
            if (!empty($meta[$prefix . '_auto_zero'])) {
                $raw = 0.0;
            } elseif ($finished) {
                $raw = GradingService::finalRaw((int) $a['id'], $code, $settings);
            }
            if ($raw !== null) {
                $changes[$prefix . '_raw'] = $raw;
                // Qisqa mockda (qismlar kam) xom ball rasmiy maksimumga mutanosib o'tkaziladi.
                $max = array_sum(GradingService::rubric($mock, $code));
                $official = GradingService::officialMax($code);
                $scaled = $max > 0 && $max !== $official ? $raw * $official / $max : $raw;
                $changes[$prefix . '_score'] = $code === 'W' ? Scoring::writing75($scaled) : Scoring::speaking75($scaled);
            }
            $scores[] = $changes[$prefix . '_score'];
        }

        $overall = $a['status'] === 'terminated' ? null : Scoring::overall($scores);
        $changes['overall'] = $overall;
        $changes['level'] = Scoring::level($overall);
        $changes['score_method'] = $method;

        Db::update('attempts', $changes, 'id = ?', [$a['id']]);
        return array_merge($a, $changes);
    }

    public static function recomputeMock(int $mockId): int
    {
        $mock = MockService::find($mockId);
        $count = 0;
        foreach (Db::all('SELECT * FROM attempts WHERE mock_id = ?', [$mockId]) as $attempt) {
            self::recompute($attempt, $mock);
            $count++;
        }
        return $count;
    }

    /**
     * Mock bo'yicha Rasch tahlili (Listening va Reading alohida).
     * μ va σ qatnashchilardan olinadi yoki admin tomonidan qotiriladi (kichik MOC uchun).
     */
    public static function computeRasch(int $mockId, array $fixed = []): array
    {
        $mock = MockService::find($mockId);
        $key = Util::decode($mock['key_json']);
        $stats = Util::decode($mock['stats_json']);
        $report = [];

        foreach (['L', 'R'] as $code) {
            $items = array_map('strval', array_keys($key[$code] ?? []));
            if ($items === []) {
                continue;
            }
            $attempts = [];
            foreach (Db::all('SELECT * FROM attempts WHERE mock_id = ?', [$mockId]) as $a) {
                $meta = Util::decode($a['meta_json']);
                if (isset($meta['sections'][$code]['finished_ms']) && $a['status'] !== 'terminated') {
                    $attempts[] = $a;
                }
            }
            $n = count($attempts);
            if ($n < self::MIN_RASCH_PERSONS) {
                $report[$code] = ['ok' => false, 'n' => $n, 'message' => "Kamida " . self::MIN_RASCH_PERSONS . " ta qatnashchi kerak (hozir {$n} ta)."];
                continue;
            }
            $matrix = [];
            foreach ($attempts as $a) {
                $answers = Util::decode($a['answers_json'])[$code] ?? [];
                $row = [];
                foreach ($items as $i => $q) {
                    $row[$i] = Scoring::isCorrect($key[$code][$q], $answers[$q] ?? '') ? 1 : 0;
                }
                $matrix[] = $row;
            }
            $est = Rasch::estimate($matrix);
            [$mu, $sigma] = Rasch::meanSd($est['theta']);
            $isFixed = false;
            if (isset($fixed[$code]['mu'], $fixed[$code]['sigma']) && is_numeric($fixed[$code]['mu']) && (float) $fixed[$code]['sigma'] > 0) {
                $mu = (float) $fixed[$code]['mu'];
                $sigma = (float) $fixed[$code]['sigma'];
                $isFixed = true;
            }
            if ($sigma <= 0) {
                $report[$code] = ['ok' => false, 'n' => $n, 'message' => "Natijalar bir xil — Rasch hisoblab bo'lmaydi."];
                continue;
            }
            $difficulty = [];
            foreach ($items as $i => $q) {
                $difficulty[$q] = $est['difficulty'][$i];
            }
            $stats['rasch'][$code] = [
                'items' => $difficulty,
                'mu' => $mu,
                'sigma' => $sigma,
                'fixed' => $isFixed,
                'n' => $n,
                'iterations' => $est['iterations'],
                'computed_at' => time(),
            ];
            $report[$code] = ['ok' => true, 'n' => $n, 'mu' => $mu, 'sigma' => $sigma, 'fixed' => $isFixed];
        }

        if ($report === []) {
            throw new HttpError(422, 'no_items', "Listening yoki Reading bo'limlari yo'q.");
        }
        Db::update('mocks', ['stats_json' => Util::json($stats)], 'id = ?', [$mockId]);
        self::recomputeMock($mockId);
        return $report;
    }

    public static function clearRasch(int $mockId): void
    {
        $mock = MockService::find($mockId);
        $stats = Util::decode($mock['stats_json']);
        unset($stats['rasch']);
        Db::update('mocks', ['stats_json' => Util::json($stats)], 'id = ?', [$mockId]);
        self::recomputeMock($mockId);
    }

    /**
     * Savollar tahlili: har savol bo'yicha to'g'ri javoblar ulushi va eng ko'p tanlangan noto'g'ri javoblar.
     * Ochiq (gap-fill) javoblar guruhlab ko'rsatiladi — admin ularni kalitga qo'shishi mumkin.
     */
    public static function itemAnalysis(int $mockId): array
    {
        $mock = MockService::find($mockId);
        $key = Util::decode($mock['key_json']);
        $stats = Util::decode($mock['stats_json']);
        $attempts = Db::all("SELECT answers_json, meta_json, status FROM attempts WHERE mock_id = ?", [$mockId]);
        $out = [];
        foreach (['L', 'R'] as $code) {
            $rows = [];
            foreach ($key[$code] ?? [] as $q => $item) {
                $rows[(string) $q] = [
                    'n' => (int) $q,
                    'type' => $item['type'],
                    'answer' => $item['answer'],
                    'total' => 0,
                    'correct' => 0,
                    'blank' => 0,
                    'wrong' => [],
                    'difficulty' => $stats['rasch'][$code]['items'][(string) $q] ?? null,
                ];
            }
            foreach ($attempts as $a) {
                $meta = Util::decode($a['meta_json']);
                if (!isset($meta['sections'][$code]['finished_ms'])) {
                    continue;
                }
                $answers = Util::decode($a['answers_json'])[$code] ?? [];
                foreach ($rows as $q => &$row) {
                    $row['total']++;
                    $given = (string) ($answers[$q] ?? '');
                    if ($given === '') {
                        $row['blank']++;
                    } elseif (Scoring::isCorrect($key[$code][$q], $given)) {
                        $row['correct']++;
                    } else {
                        $label = $row['type'] === 'gap' ? Scoring::normalize($given) : strtoupper($given);
                        $row['wrong'][$label] = ($row['wrong'][$label] ?? 0) + 1;
                    }
                }
                unset($row);
            }
            foreach ($rows as &$row) {
                arsort($row['wrong']);
                $row['wrong'] = array_slice($row['wrong'], 0, 12, true);
                $row['p'] = $row['total'] > 0 ? round($row['correct'] / $row['total'], 3) : null;
            }
            unset($row);
            $out[$code] = array_values($rows);
        }
        return $out;
    }

    /** Gap-fill savol kalitiga yangi muqobil javob qo'shish (masalan, admin imloni qabul qildi). */
    public static function acceptAlternative(int $mockId, string $code, int $n, string $alternative, int $userId): void
    {
        $alternative = trim(Util::cleanText($alternative, 200));
        if ($alternative === '' || str_contains($alternative, '|') || !in_array($code, ['L', 'R'], true)) {
            throw new HttpError(422, 'validation', "Javob noto'g'ri.");
        }
        Db::tx(static function () use ($mockId, $code, $n, $alternative, $userId): void {
            $mock = MockService::find($mockId);
            $source = Util::decode($mock['source_json']);
            $section = $code === 'L' ? 'listening' : 'reading';
            $found = false;
            foreach ($source[$section]['parts'] ?? [] as $pi => $part) {
                foreach ($part['blocks'] ?? [] as $bi => $block) {
                    if (($block['type'] ?? '') !== 'gap_text' || !in_array($n, MockService::gapNumbers((string) ($block['text'] ?? '')), true)) {
                        continue;
                    }
                    $current = (string) ($block['answers'][(string) $n] ?? '');
                    $alternatives = array_filter(array_map('trim', explode('|', $current)), 'strlen');
                    foreach ($alternatives as $existing) {
                        if (Scoring::normalize($existing) === Scoring::normalize($alternative)) {
                            return;
                        }
                    }
                    $alternatives[] = $alternative;
                    $source[$section]['parts'][$pi]['blocks'][$bi]['answers'][(string) $n] = implode('|', $alternatives);
                    $found = true;
                }
            }
            if (!$found) {
                throw new HttpError(404, 'not_found', "Bu raqamli bo'sh joy topilmadi.");
            }
            $compiled = MockService::compile($source);
            Db::update('mocks', [
                'source_json' => Util::json($source),
                'content_json' => Util::json($compiled['content']),
                'key_json' => Util::json($compiled['key']),
                'updated_at' => time(),
            ], 'id = ?', [$mockId]);
            \App\Audit::log($userId, 'key_alternative', 'mock:' . $mockId, ['section' => $code, 'n' => $n, 'value' => $alternative]);
        });
        self::recomputeMock($mockId);
    }
}
