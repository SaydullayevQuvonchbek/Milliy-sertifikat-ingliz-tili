<?php

declare(strict_types=1);

namespace App\Services;

use App\Db;
use App\Http\HttpError;
use App\Util;
use PDOException;

/**
 * Writing va Speaking ekspert tekshiruvi.
 * Har ish anonim kod bilan navbatga tushadi; sozlamaga ko'ra 1 yoki 2 ekspert baholaydi,
 * ikki ekspert bahosi farqi chegaradan oshsa — 3-ekspert.
 */
final class GradingService
{
    public const CLAIM_MS = 30 * 60_000;

    /** Har topshiriq / qism uchun maksimal band. */
    public const RUBRIC = [
        'W' => ['1.1' => 5, '1.2' => 5, '2' => 6],
        'S' => ['1.1' => 5, '1.2' => 5, '2' => 5, '3' => 6],
    ];

    /** Maxsus holatlar: mavzuga mos emas, yodlangan, ko'chirilgan, asosan ona tilida. */
    public const FLAGS = ['off_topic', 'memorized', 'copied', 'mother_tongue'];

    /**
     * Mockdagi mavjud topshiriq/qismlar bo'yicha mezon. Rasmiy formatda — to'liq RUBRIC;
     * qisqa (sinov) mockda faqat bor qismlar baholanadi.
     * @return array<string,int>
     */
    public static function rubric(array $mock, string $skill): array
    {
        $content = Util::decode($mock['content_json']);
        $ids = [];
        if ($skill === 'W') {
            foreach ($content['writing']['parts'] ?? [] as $part) {
                foreach ($part['tasks'] ?? [] as $task) {
                    $ids[] = (string) $task['id'];
                }
            }
        } else {
            foreach ($content['speaking']['parts'] ?? [] as $part) {
                $ids[] = (string) $part['id'];
            }
        }
        $rubric = array_filter(self::RUBRIC[$skill], static fn ($key) => in_array((string) $key, $ids, true), ARRAY_FILTER_USE_KEY);
        return $rubric !== [] ? $rubric : self::RUBRIC[$skill];
    }

    /** Rasmiy maksimum (Writing 16, Speaking 21). */
    public static function officialMax(string $skill): int
    {
        return (int) array_sum(self::RUBRIC[$skill]);
    }

    public static function ratings(int $attemptId, string $skill): array
    {
        return Db::all('SELECT * FROM ratings WHERE attempt_id = ? AND skill = ? ORDER BY round, id', [$attemptId, $skill]);
    }

    public static function needed(array $ratings, array $settings, string $skill): int
    {
        if ((int) $settings['grading']['raters'] === 1) {
            return 1;
        }
        if (count($ratings) >= 2) {
            $threshold = $skill === 'W' ? $settings['grading']['diff_w'] : $settings['grading']['diff_s'];
            if (abs((float) $ratings[0]['raw_total'] - (float) $ratings[1]['raw_total']) > $threshold) {
                return 3;
            }
        }
        return 2;
    }

    public static function finalRaw(int $attemptId, string $skill, array $settings): ?float
    {
        $ratings = self::ratings($attemptId, $skill);
        $needed = self::needed($ratings, $settings, $skill);
        if (count($ratings) < $needed) {
            return null;
        }
        $r1 = (float) $ratings[0]['raw_total'];
        if ($needed === 1) {
            return $r1;
        }
        $r2 = (float) $ratings[1]['raw_total'];
        if ($needed === 2) {
            return Scoring::roundHalf(($r1 + $r2) / 2);
        }
        // 3-ekspert: uning bahosi va unga yaqinroq bo'lgan birinchi ikki bahodan birining o'rtachasi.
        $r3 = (float) $ratings[2]['raw_total'];
        $closest = abs($r3 - $r1) <= abs($r3 - $r2) ? $r1 : $r2;
        return Scoring::roundHalf(($r3 + $closest) / 2);
    }

    private static function column(string $skill): string
    {
        return $skill === 'W' ? 'grade_w' : 'grade_s';
    }

    public static function queueCounts(int $expertId): array
    {
        $out = [];
        foreach (['W', 'S'] as $skill) {
            $col = self::column($skill);
            $out[$skill] = [
                'waiting' => (int) Db::val("SELECT COUNT(*) FROM attempts WHERE {$col} = 'queue'"),
                'mine' => (int) Db::val('SELECT COUNT(*) FROM ratings WHERE expert_id = ? AND skill = ?', [$expertId, $skill]),
            ];
        }
        return $out;
    }

    /** Ekspertga keyingi ishni berish (yoki u allaqachon olgan ishni qaytarish). */
    public static function next(array $expert, string $skill): ?array
    {
        if (!isset(self::RUBRIC[$skill])) {
            throw new HttpError(422, 'validation', "Ko'nikma noto'g'ri.");
        }
        $expertId = (int) $expert['id'];
        $col = self::column($skill);

        return Db::tx(static function () use ($expertId, $skill, $col): ?array {
            $now = Util::nowMs();
            Db::exec('DELETE FROM grading_claims WHERE expires_ms < ?', [$now]);

            $mine = Db::one(
                "SELECT c.attempt_id FROM grading_claims c JOIN attempts a ON a.id = c.attempt_id
                 WHERE c.expert_id = ? AND c.skill = ? AND a.{$col} = 'queue' ORDER BY c.expires_ms LIMIT 1",
                [$expertId, $skill]
            );
            if ($mine !== null) {
                return self::work((int) $mine['attempt_id'], $skill);
            }

            $candidates = Db::all(
                "SELECT a.id, a.mock_id FROM attempts a
                 WHERE a.{$col} = 'queue'
                   AND NOT EXISTS (SELECT 1 FROM ratings r WHERE r.attempt_id = a.id AND r.skill = ? AND r.expert_id = ?)
                 ORDER BY a.finished_at, a.id LIMIT 300",
                [$skill, $expertId]
            );
            $settingsCache = [];
            foreach ($candidates as $c) {
                $mockId = (int) $c['mock_id'];
                $settingsCache[$mockId] ??= MockService::settings(MockService::find($mockId));
                $ratings = self::ratings((int) $c['id'], $skill);
                $needed = self::needed($ratings, $settingsCache[$mockId], $skill);
                $claims = (int) Db::val('SELECT COUNT(*) FROM grading_claims WHERE attempt_id = ? AND skill = ?', [$c['id'], $skill]);
                if (count($ratings) + $claims >= $needed) {
                    continue;
                }
                try {
                    Db::insert('grading_claims', [
                        'attempt_id' => $c['id'],
                        'skill' => $skill,
                        'expert_id' => $expertId,
                        'expires_ms' => $now + self::CLAIM_MS,
                    ]);
                } catch (PDOException $e) {
                    if (!Db::isUniqueViolation($e)) {
                        throw $e;
                    }
                }
                return self::work((int) $c['id'], $skill);
            }
            return null;
        });
    }

    /** Ekspertga ko'rsatiladigan anonim ish. */
    public static function work(int $attemptId, string $skill): array
    {
        $a = AttemptService::lock($attemptId);
        $mock = MockService::find((int) $a['mock_id']);
        $content = Util::decode($mock['content_json']);
        $claim = Db::one('SELECT expires_ms FROM grading_claims WHERE attempt_id = ? AND skill = ? ORDER BY expires_ms DESC LIMIT 1', [$attemptId, $skill]);
        $out = [
            'attempt_id' => $attemptId,
            'code' => $a['anon_code'],
            'skill' => $skill,
            // Ro'yxat ko'rinishida: JSON obyektida "2" kaliti "1.1" dan oldin kelib qolmasligi uchun.
            'rubric' => array_map(
                static fn ($part, $max) => ['part' => (string) $part, 'max' => $max],
                array_keys(self::rubric($mock, $skill)),
                array_values(self::rubric($mock, $skill))
            ),
            'flags' => self::FLAGS,
            'claim_expires_ms' => $claim['expires_ms'] ?? null,
        ];
        if ($skill === 'W') {
            $writing = Util::decode($a['writing_json']);
            $tasks = [];
            foreach ($content['writing']['parts'] ?? [] as $part) {
                foreach ($part['tasks'] as $task) {
                    $text = (string) ($writing[$task['id']] ?? '');
                    $tasks[] = [
                        'id' => $task['id'],
                        'title' => $task['title'],
                        'context' => $part['context'],
                        'prompt' => $task['prompt'],
                        'min_words' => $task['min_words'],
                        'max_words' => $task['max_words'],
                        'text' => $text,
                        'words' => Util::wordCount($text),
                    ];
                }
            }
            $out['tasks'] = $tasks;
        } else {
            $answers = [];
            foreach (Db::all('SELECT id, q_no, duration, mime FROM speaking_answers WHERE attempt_id = ?', [$attemptId]) as $row) {
                $answers[(int) $row['q_no']] = $row;
            }
            $questions = [];
            foreach (AttemptService::speakingQuestions($mock) as $q) {
                $answer = $answers[(int) $q['no']] ?? null;
                $questions[] = [
                    'no' => (int) $q['no'],
                    'part_id' => $q['part_id'],
                    'text' => $q['text'],
                    'topic' => $q['topic'],
                    'for' => $q['for'],
                    'against' => $q['against'],
                    'images' => $q['images'],
                    'answer_sec' => (int) $q['answer_sec'],
                    'answer_id' => $answer ? (int) $answer['id'] : null,
                    'duration' => $answer ? (float) $answer['duration'] : null,
                ];
            }
            $out['questions'] = $questions;
        }
        return $out;
    }

    public static function canAccess(array $user, int $attemptId, string $skill): bool
    {
        if ($user['role'] === 'admin') {
            return true;
        }
        $claimed = Db::val(
            'SELECT COUNT(*) FROM grading_claims WHERE attempt_id = ? AND skill = ? AND expert_id = ? AND expires_ms >= ?',
            [$attemptId, $skill, $user['id'], Util::nowMs()]
        );
        return (bool) $claimed;
    }

    public static function rate(array $expert, int $attemptId, string $skill, array $scores, array $flags, string $comment): array
    {
        if (!isset(self::RUBRIC[$skill])) {
            throw new HttpError(422, 'validation', "Ko'nikma noto'g'ri.");
        }
        return Db::tx(static function () use ($expert, $attemptId, $skill, $scores, $flags, $comment): array {
            // Qulflash tartibi next() bilan bir xil: avval da'vo (claim), keyin urinish — MySQL'da deadlock bo'lmasin.
            Db::one(
                'SELECT attempt_id FROM grading_claims WHERE attempt_id = ? AND skill = ? AND expert_id = ?' . Db::forUpdate(),
                [$attemptId, $skill, $expert['id']]
            );
            $a = AttemptService::lock($attemptId);
            $col = self::column($skill);
            if ($a[$col] !== 'queue') {
                throw new HttpError(409, 'not_in_queue', 'Bu ish allaqachon baholangan yoki navbatda emas.');
            }
            if (!self::canAccess($expert, $attemptId, $skill)) {
                throw new HttpError(403, 'no_claim', "Bu ishni baholash muddati tugagan. Navbatdan qayta oling.");
            }
            if (Db::val('SELECT COUNT(*) FROM ratings WHERE attempt_id = ? AND skill = ? AND expert_id = ?', [$attemptId, $skill, $expert['id']])) {
                throw new HttpError(409, 'already_rated', 'Siz bu ishni allaqachon baholagansiz.');
            }

            $mock = MockService::find((int) $a['mock_id']);
            $writing = $skill === 'W' ? Util::decode($a['writing_json']) : [];
            $cleanScores = [];
            $cleanFlags = [];
            foreach (self::rubric($mock, $skill) as $part => $max) {
                $part = (string) $part;
                $value = $scores[$part] ?? null;
                if (!is_numeric($value) || (int) $value != $value || (int) $value < 0 || (int) $value > $max) {
                    throw new HttpError(422, 'validation', "{$part} uchun ball 0 dan {$max} gacha butun son bo'lishi kerak.");
                }
                $value = (int) $value;
                $flag = (string) ($flags[$part] ?? '');
                if ($flag !== '') {
                    if (!in_array($flag, self::FLAGS, true)) {
                        throw new HttpError(422, 'validation', "Belgi noto'g'ri.");
                    }
                    $cleanFlags[$part] = $flag;
                    if ($skill === 'W') {
                        // Rasmiy qoida: 1.1 da 1 ball (bo'sh bo'lsa 0), 1.2 va 2 da 0.
                        $empty = Util::wordCount((string) ($writing[$part] ?? '')) === 0;
                        $value = ($part === '1.1' && !$empty) ? 1 : 0;
                    }
                }
                $cleanScores[$part] = $value;
            }

            $round = count(self::ratings($attemptId, $skill)) + 1;
            Db::insert('ratings', [
                'attempt_id' => $attemptId,
                'skill' => $skill,
                'expert_id' => $expert['id'],
                'round' => $round,
                'scores_json' => Util::json($cleanScores),
                'flags_json' => Util::json((object) $cleanFlags),
                'raw_total' => array_sum($cleanScores),
                'comment' => mb_substr(trim(Util::cleanText($comment, 2000)), 0, 2000),
                'created_at' => time(),
            ]);
            Db::exec('DELETE FROM grading_claims WHERE attempt_id = ? AND skill = ? AND expert_id = ?', [$attemptId, $skill, $expert['id']]);

            if (self::finalRaw($attemptId, $skill, MockService::settings($mock)) !== null) {
                Db::update('attempts', [$col => 'done'], 'id = ?', [$attemptId]);
                $a[$col] = 'done';
            }
            ScoreService::recompute($a, $mock);
            return ['round' => $round, 'done' => $a[$col] === 'done', 'scores' => $cleanScores];
        });
    }

    public static function release(array $expert, int $attemptId, string $skill): void
    {
        Db::exec('DELETE FROM grading_claims WHERE attempt_id = ? AND skill = ? AND expert_id = ?', [$attemptId, $skill, $expert['id']]);
    }
}
