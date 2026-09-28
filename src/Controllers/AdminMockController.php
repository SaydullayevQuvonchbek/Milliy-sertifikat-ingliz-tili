<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Audit;
use App\Auth;
use App\Db;
use App\Http\FileResponse;
use App\Http\HttpError;
use App\Http\Request;
use App\Http\TextResponse;
use App\Services\GradingService;
use App\Services\MockService;
use App\Services\ScoreService;
use App\Services\Uploads;
use App\Util;

final class AdminMockController
{
    public static function list(Request $r): array
    {
        Auth::require('admin');
        $rows = Db::all('SELECT id, title, description, status, max_attempts, settings_json, content_json, available_from, available_to, results_published_at, created_at, updated_at FROM mocks ORDER BY id DESC');
        $counts = [];
        foreach (Db::all("SELECT mock_id, status, COUNT(*) AS c FROM attempts GROUP BY mock_id, status") as $row) {
            $counts[(int) $row['mock_id']][$row['status']] = (int) $row['c'];
        }
        $out = [];
        foreach ($rows as $m) {
            $c = $counts[(int) $m['id']] ?? [];
            $out[] = [
                'id' => (int) $m['id'],
                'title' => $m['title'],
                'description' => $m['description'],
                'status' => $m['status'],
                'max_attempts' => MockService::maxAttempts($m),
                'sections' => MockService::sections($m),
                'available_from' => $m['available_from'],
                'available_to' => $m['available_to'],
                'results_published' => $m['results_published_at'] !== null,
                'attempts' => [
                    'in_progress' => $c['in_progress'] ?? 0,
                    'completed' => $c['completed'] ?? 0,
                    'terminated' => $c['terminated'] ?? 0,
                ],
                'updated_at' => (int) $m['updated_at'],
            ];
        }
        return ['mocks' => $out];
    }

    public static function get(Request $r): array
    {
        Auth::require('admin');
        $mock = MockService::find((int) $r->param('id'));
        return ['mock' => self::full($mock)];
    }

    private static function full(array $mock): array
    {
        $assets = Db::all('SELECT id, kind, original_name, mime, size, duration, created_at FROM assets WHERE mock_id = ? ORDER BY id', [$mock['id']]);
        return [
            'id' => (int) $mock['id'],
            'title' => $mock['title'],
            'description' => $mock['description'],
            'status' => $mock['status'],
            'max_attempts' => (int) $mock['max_attempts'],
            'available_from' => $mock['available_from'] !== null ? (int) $mock['available_from'] : null,
            'available_to' => $mock['available_to'] !== null ? (int) $mock['available_to'] : null,
            'source' => Util::decode($mock['source_json'], MockService::emptySource()),
            'settings' => MockService::settings($mock),
            'stats' => Util::decode($mock['stats_json']),
            'results_published_at' => $mock['results_published_at'],
            'assets' => $assets,
            'in_progress' => (int) Db::val("SELECT COUNT(*) FROM attempts WHERE mock_id = ? AND status = 'in_progress'", [$mock['id']]),
            'attempts_total' => (int) Db::val('SELECT COUNT(*) FROM attempts WHERE mock_id = ?', [$mock['id']]),
        ];
    }

    public static function create(Request $r): array
    {
        $user = Auth::require('admin');
        $result = MockService::save(null, $r->body, (int) $user['id']);
        Audit::log((int) $user['id'], 'mock_create', 'mock:' . $result['id']);
        return ['mock' => self::full(MockService::find($result['id']))];
    }

    public static function update(Request $r): array
    {
        $user = Auth::require('admin');
        $id = (int) $r->param('id');
        $result = MockService::save($id, $r->body, (int) $user['id']);
        $rescored = 0;
        if ($result['key_changed']) {
            $rescored = ScoreService::recomputeMock($id);
            Audit::log((int) $user['id'], 'mock_key_changed', 'mock:' . $id, ['rescored' => $rescored]);
        }
        $mock = MockService::find($id);
        return [
            'mock' => self::full($mock),
            'rescored' => $rescored,
            'validation' => MockService::validate(Util::decode($mock['source_json']), MockService::settings($mock), MockService::assetIds($id)),
        ];
    }

    public static function validate(Request $r): array
    {
        Auth::require('admin');
        $id = (int) $r->param('id');
        $source = is_array($r->input('source')) ? $r->input('source') : Util::decode(MockService::find($id)['source_json']);
        $settings = MockService::mergeSettings(is_array($r->input('settings')) ? $r->input('settings') : []);
        return ['validation' => MockService::validate($source, $settings, MockService::assetIds($id))];
    }

    public static function status(Request $r): array
    {
        $user = Auth::require('admin');
        $mock = MockService::setStatus((int) $r->param('id'), $r->str('status'), (int) $user['id']);
        return ['mock' => self::full($mock)];
    }

    public static function duplicate(Request $r): array
    {
        $user = Auth::require('admin');
        $mock = MockService::find((int) $r->param('id'));
        $newId = Db::tx(static function () use ($mock, $user): int {
            $id = Db::insert('mocks', [
                'title' => mb_substr($mock['title'] . ' (nusxa)', 0, 190),
                'description' => $mock['description'],
                'status' => 'draft',
                'max_attempts' => $mock['max_attempts'],
                'source_json' => $mock['source_json'],
                'content_json' => $mock['content_json'],
                'key_json' => $mock['key_json'],
                'settings_json' => $mock['settings_json'],
                'stats_json' => '{}',
                'created_by' => $user['id'],
                'created_at' => time(),
                'updated_at' => time(),
            ]);
            // Fayllarni nusxalash va manbadagi identifikatorlarni yangilash.
            $map = [];
            foreach (Db::all('SELECT * FROM assets WHERE mock_id = ?', [$mock['id']]) as $asset) {
                $src = Uploads::assetPath($asset['kind'], $asset['file']);
                $ext = pathinfo($asset['file'], PATHINFO_EXTENSION);
                $name = sprintf('m%d_%s.%s', $id, bin2hex(random_bytes(10)), $ext);
                if (is_file($src)) {
                    copy($src, Uploads::assetPath($asset['kind'], $name));
                }
                $map[(int) $asset['id']] = Db::insert('assets', [
                    'mock_id' => $id,
                    'kind' => $asset['kind'],
                    'file' => $name,
                    'original_name' => $asset['original_name'],
                    'mime' => $asset['mime'],
                    'size' => $asset['size'],
                    'duration' => $asset['duration'],
                    'created_at' => time(),
                ]);
            }
            $source = self::remapAssets(Util::decode($mock['source_json']), $map);
            $compiled = MockService::compile($source);
            Db::update('mocks', [
                'source_json' => Util::json($source),
                'content_json' => Util::json($compiled['content']),
                'key_json' => Util::json($compiled['key']),
            ], 'id = ?', [$id]);
            return $id;
        });
        Audit::log((int) $user['id'], 'mock_duplicate', 'mock:' . $mock['id'], ['new' => $newId]);
        return ['mock' => self::full(MockService::find($newId))];
    }

    private static function remapAssets(array $source, array $map): array
    {
        foreach ($source['listening']['parts'] ?? [] as $pi => $part) {
            foreach ($part['tracks'] ?? [] as $ti => $track) {
                if (isset($track['asset'], $map[(int) $track['asset']])) {
                    $source['listening']['parts'][$pi]['tracks'][$ti]['asset'] = $map[(int) $track['asset']];
                }
            }
        }
        foreach ($source['speaking']['parts'] ?? [] as $pi => $part) {
            foreach ($part['images'] ?? [] as $ii => $img) {
                if (isset($map[(int) $img])) {
                    $source['speaking']['parts'][$pi]['images'][$ii] = $map[(int) $img];
                }
            }
            foreach ($part['questions'] ?? [] as $qi => $q) {
                if (!empty($q['audio']) && isset($map[(int) $q['audio']])) {
                    $source['speaking']['parts'][$pi]['questions'][$qi]['audio'] = $map[(int) $q['audio']];
                }
            }
        }
        return $source;
    }

    public static function delete(Request $r): array
    {
        $user = Auth::require('admin');
        $id = (int) $r->param('id');
        $mock = MockService::find($id);
        $attempts = (int) Db::val('SELECT COUNT(*) FROM attempts WHERE mock_id = ?', [$id]);
        if ($attempts > 0) {
            throw new HttpError(409, 'has_attempts', "Bu mockda {$attempts} ta urinish bor. O'chirish o'rniga arxivlang.");
        }
        foreach (Db::all('SELECT kind, file FROM assets WHERE mock_id = ?', [$id]) as $asset) {
            @unlink(Uploads::assetPath($asset['kind'], $asset['file']));
        }
        Db::exec('DELETE FROM assets WHERE mock_id = ?', [$id]);
        Db::exec('DELETE FROM mocks WHERE id = ?', [$id]);
        Audit::log((int) $user['id'], 'mock_delete', 'mock:' . $id, ['title' => $mock['title']]);
        return ['ok' => true];
    }

    public static function uploadAsset(Request $r): array
    {
        $user = Auth::require('admin');
        $mock = MockService::find((int) $r->param('id'));
        $kind = $r->str('kind') === 'image' ? 'image' : 'audio';
        $file = $r->files['file'] ?? null;
        if (!is_array($file)) {
            throw new HttpError(422, 'no_file', 'Fayl tanlanmagan.');
        }
        $stored = Uploads::storeAsset($file, (int) $mock['id'], $kind);
        $duration = $r->input('duration');
        $id = Db::insert('assets', [
            'mock_id' => $mock['id'],
            'kind' => $kind,
            'file' => $stored['file'],
            'original_name' => $stored['original_name'],
            'mime' => $stored['mime'],
            'size' => $stored['size'],
            'duration' => is_numeric($duration) ? round((float) $duration, 3) : null,
            'created_at' => time(),
        ]);
        Audit::log((int) $user['id'], 'asset_upload', 'mock:' . $mock['id'], ['asset' => $id, 'kind' => $kind]);
        return ['asset' => Db::one('SELECT id, kind, original_name, mime, size, duration, created_at FROM assets WHERE id = ?', [$id])];
    }

    public static function asset(Request $r): FileResponse
    {
        Auth::require('admin', 'expert');
        $asset = Db::one('SELECT * FROM assets WHERE id = ?', [(int) $r->param('asset')]);
        if ($asset === null) {
            throw new HttpError(404, 'not_found', 'Fayl topilmadi.');
        }
        return new FileResponse(Uploads::assetPath($asset['kind'], $asset['file']), $asset['mime'], 600);
    }

    public static function deleteAsset(Request $r): array
    {
        $user = Auth::require('admin');
        $asset = Db::one('SELECT * FROM assets WHERE id = ?', [(int) $r->param('asset')]);
        if ($asset === null) {
            throw new HttpError(404, 'not_found', 'Fayl topilmadi.');
        }
        $mock = MockService::find((int) $asset['mock_id']);
        if (in_array((int) $asset['id'], self::usedAssetIds(Util::decode($mock['source_json'])), true)) {
            throw new HttpError(409, 'in_use', "Bu fayl mockda ishlatilmoqda. Avval uni savollardan olib tashlang va saqlang.");
        }
        @unlink(Uploads::assetPath($asset['kind'], $asset['file']));
        Db::exec('DELETE FROM assets WHERE id = ?', [$asset['id']]);
        Audit::log((int) $user['id'], 'asset_delete', 'mock:' . $mock['id'], ['asset' => (int) $asset['id']]);
        return ['ok' => true];
    }

    /** @return int[] */
    private static function usedAssetIds(array $source): array
    {
        $ids = [];
        foreach ($source['listening']['parts'] ?? [] as $part) {
            foreach ($part['tracks'] ?? [] as $track) {
                if (!empty($track['asset'])) {
                    $ids[] = (int) $track['asset'];
                }
            }
        }
        foreach ($source['speaking']['parts'] ?? [] as $part) {
            foreach ($part['images'] ?? [] as $img) {
                $ids[] = (int) $img;
            }
            foreach ($part['questions'] ?? [] as $q) {
                if (!empty($q['audio'])) {
                    $ids[] = (int) $q['audio'];
                }
            }
        }
        return $ids;
    }

    public static function setAssetDuration(Request $r): array
    {
        Auth::require('admin');
        $duration = $r->input('duration');
        if (!is_numeric($duration) || (float) $duration <= 0) {
            throw new HttpError(422, 'validation', "Davomiylik noto'g'ri.");
        }
        Db::exec('UPDATE assets SET duration = ? WHERE id = ?', [round((float) $duration, 3), (int) $r->param('asset')]);
        return ['ok' => true];
    }

    // ------------------------------------------------------------------
    // Natijalar
    // ------------------------------------------------------------------

    public static function attempts(Request $r): array
    {
        Auth::require('admin');
        $mock = MockService::find((int) $r->param('id'));
        $rows = Db::all(
            'SELECT a.id, a.attempt_no, a.status, a.stage, a.stage_state, a.violations, a.started_at, a.finished_at,
                    a.l_raw, a.r_raw, a.l_score, a.r_score, a.w_raw, a.w_score, a.s_raw, a.s_score, a.overall, a.level,
                    a.score_method, a.grade_w, a.grade_s, a.anon_code, a.last_seen_ms, a.section_deadline_ms,
                    u.id AS user_id, u.full_name, u.login
             FROM attempts a JOIN users u ON u.id = a.user_id WHERE a.mock_id = ? ORDER BY a.overall IS NULL, a.overall DESC, a.id',
            [$mock['id']]
        );
        foreach ($rows as &$row) {
            foreach (['id', 'attempt_no', 'violations', 'started_at', 'finished_at', 'user_id', 'last_seen_ms', 'section_deadline_ms'] as $k) {
                $row[$k] = $row[$k] !== null ? (int) $row[$k] : null;
            }
        }
        unset($row);
        return [
            'mock' => ['id' => (int) $mock['id'], 'title' => $mock['title'], 'sections' => MockService::sections($mock), 'results_published_at' => $mock['results_published_at']],
            'attempts' => $rows,
            'stats' => Util::decode($mock['stats_json']),
        ];
    }

    public static function exportCsv(Request $r): TextResponse
    {
        Auth::require('admin');
        $mock = MockService::find((int) $r->param('id'));
        $rows = Db::all(
            "SELECT a.*, u.full_name, u.login FROM attempts a JOIN users u ON u.id = a.user_id
             WHERE a.mock_id = ? ORDER BY a.overall IS NULL, a.overall DESC, a.id",
            [$mock['id']]
        );
        $levels = ['C1' => 'C1', 'B2' => 'B2', 'B1' => 'B1', 'below' => 'B1 dan quyi'];
        $statuses = ['in_progress' => 'Jarayonda', 'completed' => 'Yakunlangan', 'terminated' => 'Chetlatilgan'];
        $fh = fopen('php://temp', 'w+');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, ["O'rin", 'F.I.Sh.', 'Login', 'Urinish', 'Holat', 'Listening (to\'g\'ri)', 'Listening', 'Reading (to\'g\'ri)', 'Reading', 'Writing (xom)', 'Writing', 'Speaking (xom)', 'Speaking', 'Umumiy', 'Daraja', 'Qoidabuzarlik'], ';', '"', '');
        $place = 0;
        foreach ($rows as $a) {
            $place++;
            fputcsv($fh, [
                $a['overall'] !== null ? $place : '',
                $a['full_name'],
                $a['login'],
                $a['attempt_no'],
                $statuses[$a['status']] ?? $a['status'],
                $a['l_raw'], $a['l_score'], $a['r_raw'], $a['r_score'],
                $a['w_raw'], $a['w_score'], $a['s_raw'], $a['s_score'],
                $a['overall'], $levels[$a['level']] ?? '', $a['violations'],
            ], ';', '"', '');
        }
        rewind($fh);
        $csv = (string) stream_get_contents($fh);
        fclose($fh);
        $name = 'natijalar_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $mock['title']) . '.csv';
        return new TextResponse($csv, 'text/csv; charset=utf-8', $name);
    }

    public static function rescore(Request $r): array
    {
        $user = Auth::require('admin');
        $id = (int) $r->param('id');
        $count = ScoreService::recomputeMock($id);
        Audit::log((int) $user['id'], 'mock_rescore', 'mock:' . $id, ['count' => $count]);
        return ['rescored' => $count];
    }

    public static function rasch(Request $r): array
    {
        $user = Auth::require('admin');
        $id = (int) $r->param('id');
        $fixed = is_array($r->input('fixed')) ? $r->input('fixed') : [];
        $report = ScoreService::computeRasch($id, $fixed);
        Audit::log((int) $user['id'], 'mock_rasch', 'mock:' . $id, $report);
        return ['report' => $report];
    }

    public static function raschClear(Request $r): array
    {
        $user = Auth::require('admin');
        ScoreService::clearRasch((int) $r->param('id'));
        Audit::log((int) $user['id'], 'mock_rasch_clear', 'mock:' . (int) $r->param('id'));
        return ['ok' => true];
    }

    public static function items(Request $r): array
    {
        Auth::require('admin');
        return ['items' => ScoreService::itemAnalysis((int) $r->param('id'))];
    }

    public static function acceptAlternative(Request $r): array
    {
        $user = Auth::require('admin');
        ScoreService::acceptAlternative((int) $r->param('id'), $r->str('section'), $r->int('n'), $r->str('value'), (int) $user['id']);
        return ['items' => ScoreService::itemAnalysis((int) $r->param('id'))];
    }

    public static function publish(Request $r): array
    {
        $user = Auth::require('admin');
        $id = (int) $r->param('id');
        MockService::find($id);
        $publish = (bool) $r->input('publish', true);
        if ($publish) {
            $pending = (int) Db::val("SELECT COUNT(*) FROM attempts WHERE mock_id = ? AND (grade_w = 'queue' OR grade_s = 'queue')", [$id]);
            if ($pending > 0 && !$r->input('force')) {
                throw new HttpError(409, 'grading_pending', "{$pending} ta ish hali ekspertlar tomonidan baholanmagan. Baribir e'lon qilasizmi?", ['pending' => $pending]);
            }
        }
        Db::update('mocks', ['results_published_at' => $publish ? time() : null], 'id = ?', [$id]);
        Audit::log((int) $user['id'], $publish ? 'results_publish' : 'results_unpublish', 'mock:' . $id);
        return ['ok' => true, 'published' => $publish];
    }

    public static function gradingOverview(Request $r): array
    {
        Auth::require('admin');
        $rows = Db::all(
            "SELECT r.skill, r.expert_id, u.full_name, COUNT(*) AS c, AVG(r.raw_total) AS avg_raw
             FROM ratings r JOIN users u ON u.id = r.expert_id GROUP BY r.skill, r.expert_id, u.full_name ORDER BY u.full_name"
        );
        return [
            'queue' => [
                'W' => (int) Db::val("SELECT COUNT(*) FROM attempts WHERE grade_w = 'queue'"),
                'S' => (int) Db::val("SELECT COUNT(*) FROM attempts WHERE grade_s = 'queue'"),
            ],
            'experts' => array_map(static fn ($row) => [
                'expert_id' => (int) $row['expert_id'],
                'name' => $row['full_name'],
                'skill' => $row['skill'],
                'count' => (int) $row['c'],
                'avg_raw' => round((float) $row['avg_raw'], 2),
            ], $rows),
            'rubric' => GradingService::RUBRIC,
        ];
    }
}
