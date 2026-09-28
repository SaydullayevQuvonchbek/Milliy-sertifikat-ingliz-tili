<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Db;
use App\Http\HttpError;
use App\Http\Request;
use App\Services\AttemptService;
use App\Services\MockService;
use App\Services\Scoring;
use App\Util;

final class StudentController
{
    public static function mocks(Request $r): array
    {
        $user = Auth::require('student');
        $rows = Db::all(
            "SELECT * FROM mocks WHERE status = 'active'
             OR id IN (SELECT mock_id FROM attempts WHERE user_id = ? AND status = 'in_progress')
             ORDER BY id DESC",
            [$user['id']]
        );
        $out = [];
        foreach ($rows as $mock) {
            $attempts = Db::all('SELECT * FROM attempts WHERE mock_id = ? AND user_id = ? ORDER BY attempt_no', [$mock['id'], $user['id']]);
            $active = null;
            foreach ($attempts as $i => $a) {
                if ($a['status'] === 'in_progress') {
                    $a = Db::tx(static fn () => AttemptService::refresh(AttemptService::lock((int) $a['id']), $mock));
                    $attempts[$i] = $a;
                    if ($a['status'] === 'in_progress') {
                        $active = $a;
                    }
                }
            }
            $max = MockService::maxAttempts($mock);
            $used = count($attempts);
            $reason = null;
            if ($active === null) {
                try {
                    AttemptService::assertStartable($mock);
                    if ($used >= $max) {
                        $reason = "Urinishlar tugagan ({$used}/{$max}).";
                    }
                } catch (HttpError $e) {
                    $reason = $e->getMessage();
                }
            }
            $out[] = [
                'id' => (int) $mock['id'],
                'title' => $mock['title'],
                'description' => $mock['description'],
                'status' => $mock['status'],
                'sections' => MockService::sections($mock),
                'max_attempts' => $max,
                'used_attempts' => $used,
                'can_start' => $active === null && $reason === null,
                'reason' => $reason,
                'available_to' => $mock['available_to'] !== null ? (int) $mock['available_to'] : null,
                'active_attempt' => $active ? [
                    'id' => (int) $active['id'],
                    'stage' => $active['stage'],
                    'stage_state' => $active['stage_state'],
                ] : null,
            ];
        }
        return ['mocks' => $out];
    }

    public static function start(Request $r): array
    {
        $user = Auth::require('student');
        $mock = MockService::find((int) $r->param('id'));
        self::assertDevice($r, $mock);
        $attempt = AttemptService::startOrResume($user, (int) $mock['id'], $r->ip, $r->userAgent());
        return ['attempt_id' => (int) $attempt['id']];
    }

    /** Tasodifiy mock: avval hali ishlanmaganlari, keyin bir marta ishlanganlari. */
    public static function random(Request $r): array
    {
        $user = Auth::require('student');
        $candidates = [];
        foreach (Db::all("SELECT * FROM mocks WHERE status = 'active'") as $mock) {
            try {
                AttemptService::assertStartable($mock);
            } catch (HttpError) {
                continue;
            }
            if (MockService::sections($mock) === []) {
                continue;
            }
            $used = (int) Db::val('SELECT COUNT(*) FROM attempts WHERE mock_id = ? AND user_id = ?', [$mock['id'], $user['id']]);
            if ($used < MockService::maxAttempts($mock)) {
                $candidates[$used][] = $mock;
            }
        }
        if ($candidates === []) {
            throw new HttpError(404, 'no_mock', "Siz uchun yangi mock qolmadi. Barcha mocklarni ruxsat etilgan marta ishlagansiz.");
        }
        ksort($candidates);
        $pool = reset($candidates);
        $mock = $pool[random_int(0, count($pool) - 1)];
        self::assertDevice($r, $mock);
        $attempt = AttemptService::startOrResume($user, (int) $mock['id'], $r->ip, $r->userAgent());
        return ['attempt_id' => (int) $attempt['id'], 'mock_id' => (int) $mock['id']];
    }

    public static function assertDevice(Request $r, array $mock): void
    {
        $lockdown = MockService::settings($mock)['lockdown'];
        $ua = $r->userAgent();
        if ($lockdown['require_seb'] && !str_contains($ua, 'SEB')) {
            throw new HttpError(403, 'seb_required', 'Bu mock faqat Safe Exam Browser dasturi orqali ishlanadi.');
        }
        if (!$lockdown['allow_mobile'] && preg_match('/Android|iPhone|iPod|Mobile|Opera Mini|IEMobile/i', $ua)) {
            throw new HttpError(403, 'mobile_blocked', 'Bu mockni telefonda ishlab bo\'lmaydi. Kompyuter yoki noutbukdan kiring.');
        }
    }

    public static function attempts(Request $r): array
    {
        $user = Auth::require('student');
        $rows = Db::all(
            'SELECT a.*, m.title, m.results_published_at, m.settings_json FROM attempts a
             JOIN mocks m ON m.id = a.mock_id WHERE a.user_id = ? ORDER BY a.id DESC',
            [$user['id']]
        );
        $out = [];
        foreach ($rows as $a) {
            $visible = self::resultsVisible($a);
            $out[] = [
                'id' => (int) $a['id'],
                'mock_id' => (int) $a['mock_id'],
                'title' => $a['title'],
                'attempt_no' => (int) $a['attempt_no'],
                'status' => $a['status'],
                'stage' => $a['stage'],
                'started_at' => (int) $a['started_at'],
                'finished_at' => $a['finished_at'] !== null ? (int) $a['finished_at'] : null,
                'results_visible' => $visible,
                'overall' => $visible ? $a['overall'] : null,
                'level' => $visible ? $a['level'] : null,
            ];
        }
        return ['attempts' => $out];
    }

    private static function resultsVisible(array $row): bool
    {
        if ($row['status'] === 'in_progress') {
            return false;
        }
        $settings = MockService::mergeSettings(Util::decode($row['settings_json'] ?? '{}'));
        return $settings['results'] === 'instant' || $row['results_published_at'] !== null;
    }

    public static function result(Request $r): array
    {
        $user = Auth::require('student');
        $a = Db::one(
            'SELECT a.*, m.title, m.results_published_at, m.settings_json, m.content_json, m.key_json
             FROM attempts a JOIN mocks m ON m.id = a.mock_id WHERE a.id = ? AND a.user_id = ?',
            [(int) $r->param('id'), $user['id']]
        );
        if ($a === null) {
            throw new HttpError(404, 'not_found', 'Natija topilmadi.');
        }
        if (!self::resultsVisible($a)) {
            return ['visible' => false, 'title' => $a['title'], 'status' => $a['status']];
        }

        // Qismlar bo'yicha to'g'ri javoblar soni (kalitning o'zi ko'rsatilmaydi — keyingi urinish uchun sir qoladi).
        $content = Util::decode($a['content_json']);
        $key = Util::decode($a['key_json']);
        $answers = Util::decode($a['answers_json']);
        $parts = [];
        foreach (['L' => 'listening', 'R' => 'reading'] as $code => $name) {
            foreach ($content[$name]['parts'] ?? [] as $i => $part) {
                $numbers = [];
                foreach ($part['blocks'] as $block) {
                    $numbers = array_merge($numbers, MockService::blockNumbers($block));
                }
                $correct = 0;
                foreach ($numbers as $n) {
                    if (isset($key[$code][(string) $n]) && Scoring::isCorrect($key[$code][(string) $n], $answers[$code][(string) $n] ?? '')) {
                        $correct++;
                    }
                }
                $parts[$code][] = ['title' => $part['title'] !== '' ? $part['title'] : 'Part ' . ($i + 1), 'correct' => $correct, 'total' => count($numbers)];
            }
        }

        $comments = [];
        foreach (Db::all('SELECT skill, comment FROM ratings WHERE attempt_id = ? AND comment <> ? ORDER BY id', [$a['id'], '']) as $row) {
            $comments[$row['skill']][] = $row['comment'];
        }

        return [
            'visible' => true,
            'title' => $a['title'],
            'status' => $a['status'],
            'attempt_no' => (int) $a['attempt_no'],
            'sections' => AttemptService::sequence($a),
            'scores' => [
                'L' => ['raw' => $a['l_raw'], 'score' => $a['l_score']],
                'R' => ['raw' => $a['r_raw'], 'score' => $a['r_score']],
                'W' => ['raw' => $a['w_raw'], 'score' => $a['w_score']],
                'S' => ['raw' => $a['s_raw'], 'score' => $a['s_score']],
            ],
            'overall' => $a['overall'],
            'level' => $a['level'],
            'method' => $a['score_method'],
            'parts' => $parts,
            'comments' => $comments,
        ];
    }
}
