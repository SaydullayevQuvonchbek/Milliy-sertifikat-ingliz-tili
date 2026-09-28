<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Db;
use App\Http\FileResponse;
use App\Http\HttpError;
use App\Http\Request;
use App\Services\GradingService;
use App\Services\MockService;
use App\Services\Uploads;
use App\Util;

final class ExpertController
{
    public static function queue(Request $r): array
    {
        $user = Auth::require('admin', 'expert');
        return ['queue' => GradingService::queueCounts((int) $user['id'])];
    }

    public static function next(Request $r): array
    {
        $user = Auth::require('admin', 'expert');
        $work = GradingService::next($user, $r->str('skill'));
        return ['work' => $work];
    }

    public static function rate(Request $r): array
    {
        $user = Auth::require('admin', 'expert');
        $scores = is_array($r->input('scores')) ? $r->input('scores') : [];
        $flags = is_array($r->input('flags')) ? $r->input('flags') : [];
        return GradingService::rate($user, $r->int('attempt_id'), $r->str('skill'), $scores, $flags, $r->str('comment'));
    }

    public static function release(Request $r): array
    {
        $user = Auth::require('admin', 'expert');
        GradingService::release($user, $r->int('attempt_id'), $r->str('skill'));
        return ['ok' => true];
    }

    /** Speaking savolidagi rasm — faqat shu ishni olgan ekspertga. */
    public static function image(Request $r): FileResponse
    {
        $user = Auth::require('admin', 'expert');
        $attemptId = (int) $r->param('attempt');
        if (!GradingService::canAccess($user, $attemptId, 'S')) {
            throw new HttpError(403, 'forbidden', "Ruxsat yo'q.");
        }
        $a = Db::one('SELECT mock_id FROM attempts WHERE id = ?', [$attemptId]);
        $asset = Db::one("SELECT * FROM assets WHERE id = ? AND mock_id = ? AND kind = 'image'", [(int) $r->param('asset'), $a['mock_id'] ?? 0]);
        if ($asset === null) {
            throw new HttpError(404, 'not_found', 'Rasm topilmadi.');
        }
        return new FileResponse(Uploads::assetPath('image', $asset['file']), $asset['mime'], 600);
    }

    public static function history(Request $r): array
    {
        $user = Auth::require('admin', 'expert');
        $rows = Db::all(
            'SELECT r.id, r.attempt_id, r.skill, r.round, r.raw_total, r.scores_json, r.created_at, a.anon_code, m.title
             FROM ratings r JOIN attempts a ON a.id = r.attempt_id JOIN mocks m ON m.id = a.mock_id
             WHERE r.expert_id = ? ORDER BY r.id DESC LIMIT 100',
            [$user['id']]
        );
        foreach ($rows as &$row) {
            $row['scores'] = Util::decode($row['scores_json']);
            unset($row['scores_json']);
        }
        unset($row);
        return ['ratings' => $rows];
    }
}
