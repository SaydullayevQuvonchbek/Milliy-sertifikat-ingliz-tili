<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Audit;
use App\Auth;
use App\Db;
use App\Http\FileResponse;
use App\Http\HttpError;
use App\Http\Request;
use App\Services\AttemptService;
use App\Services\GradingService;
use App\Services\MockService;
use App\Services\Scoring;
use App\Services\Uploads;
use App\Util;

final class AdminAttemptController
{
    public static function detail(Request $r): array
    {
        Auth::require('admin');
        $id = (int) $r->param('id');
        $a = Db::tx(static fn () => AttemptService::refresh(AttemptService::lock($id)));
        $mock = MockService::find((int) $a['mock_id']);
        $user = Db::one('SELECT id, full_name, login FROM users WHERE id = ?', [$a['user_id']]);
        $key = Util::decode($mock['key_json']);
        $answers = Util::decode($a['answers_json']);

        $review = [];
        foreach (['L', 'R'] as $code) {
            foreach ($key[$code] ?? [] as $n => $item) {
                $given = (string) ($answers[$code][(string) $n] ?? '');
                $review[$code][] = [
                    'n' => (int) $n,
                    'type' => $item['type'],
                    'key' => $item['answer'],
                    'given' => $given,
                    'correct' => $given !== '' && Scoring::isCorrect($item, $given),
                ];
            }
        }

        $writing = [];
        foreach (Util::decode($a['writing_json']) as $task => $text) {
            $writing[] = ['task' => (string) $task, 'text' => (string) $text, 'words' => Util::wordCount((string) $text)];
        }

        $ratings = Db::all(
            'SELECT r.*, u.full_name AS expert FROM ratings r JOIN users u ON u.id = r.expert_id WHERE r.attempt_id = ? ORDER BY r.skill, r.round',
            [$id]
        );
        foreach ($ratings as &$rating) {
            $rating['scores'] = Util::decode($rating['scores_json']);
            $rating['flags'] = Util::decode($rating['flags_json']);
            unset($rating['scores_json'], $rating['flags_json']);
        }
        unset($rating);

        return [
            'attempt' => array_diff_key($a, array_flip(['answers_json', 'writing_json', 'meta_json'])),
            'meta' => Util::decode($a['meta_json']),
            'mock' => ['id' => (int) $mock['id'], 'title' => $mock['title']],
            'user' => $user,
            'review' => $review,
            'writing' => $writing,
            'speaking' => Db::all('SELECT id, q_no, mime, size, duration, created_at FROM speaking_answers WHERE attempt_id = ? ORDER BY q_no', [$id]),
            'ratings' => $ratings,
            'events' => Db::all('SELECT type, section, detail, is_violation, created_ms FROM attempt_events WHERE attempt_id = ? ORDER BY id DESC LIMIT 500', [$id]),
        ];
    }

    /**
     * Urinishni bekor qilish: o'quvchiga shu urinish qaytariladi (masalan, texnik nosozlik bo'lsa).
     * Sababi jurnalga yoziladi.
     */
    public static function reset(Request $r): array
    {
        $admin = Auth::require('admin');
        $id = (int) $r->param('id');
        $reason = trim(Util::cleanText($r->str('reason'), 300));
        if (mb_strlen($reason) < 5) {
            throw new HttpError(422, 'validation', 'Bekor qilish sababini yozing.');
        }
        $a = AttemptService::lock($id);
        foreach (Db::all('SELECT file FROM speaking_answers WHERE attempt_id = ?', [$id]) as $row) {
            @unlink(Uploads::speakingPath($row['file']));
        }
        Db::tx(static function () use ($a, $id): void {
            Db::exec('DELETE FROM attempts WHERE id = ?', [$id]);
            // Keyingi urinishlarning tartib raqamini siljitamiz, toki chegara to'g'ri ishlasin.
            Db::exec(
                'UPDATE attempts SET attempt_no = attempt_no - 1 WHERE mock_id = ? AND user_id = ? AND attempt_no > ?',
                [$a['mock_id'], $a['user_id'], $a['attempt_no']]
            );
        });
        Audit::log((int) $admin['id'], 'attempt_reset', 'attempt:' . $id, [
            'user_id' => (int) $a['user_id'],
            'mock_id' => (int) $a['mock_id'],
            'attempt_no' => (int) $a['attempt_no'],
            'status' => $a['status'],
            'reason' => $reason,
        ]);
        return ['ok' => true];
    }

    public static function terminate(Request $r): array
    {
        $admin = Auth::require('admin');
        $id = (int) $r->param('id');
        $reason = trim(Util::cleanText($r->str('reason'), 300));
        if (mb_strlen($reason) < 5) {
            throw new HttpError(422, 'validation', 'Sababini yozing.');
        }
        $a = Db::tx(static function () use ($id, $reason): array {
            $a = AttemptService::lock($id);
            return AttemptService::terminate($a, MockService::find((int) $a['mock_id']), 'Administrator: ' . $reason);
        });
        Audit::log((int) $admin['id'], 'attempt_terminate', 'attempt:' . $id, ['reason' => $reason]);
        return ['status' => $a['status']];
    }

    public static function speakingAudio(Request $r): FileResponse
    {
        $user = Auth::require('admin', 'expert');
        $row = Db::one('SELECT * FROM speaking_answers WHERE id = ?', [(int) $r->param('id')]);
        if ($row === null) {
            throw new HttpError(404, 'not_found', 'Yozuv topilmadi.');
        }
        if (!GradingService::canAccess($user, (int) $row['attempt_id'], 'S')) {
            throw new HttpError(403, 'forbidden', "Bu yozuvni tinglash uchun ruxsat yo'q.");
        }
        return new FileResponse(Uploads::speakingPath($row['file']), $row['mime'], 600);
    }

    /** Jonli nazorat: hozir imtihon ishlayotgan o'quvchilar. */
    public static function live(Request $r): array
    {
        Auth::require('admin');
        $rows = Db::all(
            "SELECT a.id, a.mock_id, m.title, a.attempt_no, a.stage, a.stage_state, a.section_deadline_ms, a.last_seen_ms,
                    a.violations, a.save_seq, u.full_name, u.login
             FROM attempts a JOIN users u ON u.id = a.user_id JOIN mocks m ON m.id = a.mock_id
             WHERE a.status = 'in_progress' ORDER BY a.last_seen_ms DESC"
        );
        $now = Util::nowMs();
        $out = [];
        foreach ($rows as $row) {
            $last = $row['last_seen_ms'] !== null ? (int) $row['last_seen_ms'] : null;
            $row['online'] = $last !== null && $now - $last < AttemptService::STALE_CLIENT_MS;
            foreach (['id', 'mock_id', 'attempt_no', 'section_deadline_ms', 'last_seen_ms', 'violations', 'save_seq'] as $k) {
                $row[$k] = $row[$k] !== null ? (int) $row[$k] : null;
            }
            $row['last_event'] = Db::one(
                'SELECT type, detail, is_violation, created_ms FROM attempt_events WHERE attempt_id = ? ORDER BY id DESC LIMIT 1',
                [$row['id']]
            );
            $out[] = $row;
        }
        return ['now' => $now, 'attempts' => $out];
    }

    public static function dashboard(Request $r): array
    {
        Auth::require('admin');
        $since = time() - 7 * 86400;
        return [
            'students' => (int) Db::val("SELECT COUNT(*) FROM users WHERE role = 'student'"),
            'experts' => (int) Db::val("SELECT COUNT(*) FROM users WHERE role = 'expert'"),
            'mocks' => [
                'active' => (int) Db::val("SELECT COUNT(*) FROM mocks WHERE status = 'active'"),
                'frozen' => (int) Db::val("SELECT COUNT(*) FROM mocks WHERE status = 'frozen'"),
                'draft' => (int) Db::val("SELECT COUNT(*) FROM mocks WHERE status = 'draft'"),
            ],
            'in_progress' => (int) Db::val("SELECT COUNT(*) FROM attempts WHERE status = 'in_progress'"),
            'week' => [
                'attempts' => (int) Db::val('SELECT COUNT(*) FROM attempts WHERE started_at >= ?', [$since]),
                'terminated' => (int) Db::val("SELECT COUNT(*) FROM attempts WHERE status = 'terminated' AND started_at >= ?", [$since]),
            ],
            'queue' => [
                'W' => (int) Db::val("SELECT COUNT(*) FROM attempts WHERE grade_w = 'queue'"),
                'S' => (int) Db::val("SELECT COUNT(*) FROM attempts WHERE grade_s = 'queue'"),
            ],
            'audit' => Db::all(
                'SELECT l.action, l.target, l.detail, l.created_at, u.full_name FROM audit_log l
                 LEFT JOIN users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT 15'
            ),
        ];
    }
}
