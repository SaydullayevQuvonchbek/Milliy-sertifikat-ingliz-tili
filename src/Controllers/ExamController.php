<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Db;
use App\Http\FileResponse;
use App\Http\HttpError;
use App\Http\Request;
use App\Services\AttemptService;
use App\Services\MockService;
use App\Services\Uploads;
use App\Util;

/** O'quvchining imtihon jarayoni (bitta urinish doirasida). */
final class ExamController
{
    /** @param callable(array,array,array):array $fn */
    private static function withAttempt(Request $r, callable $fn, bool $requireClient = true): array
    {
        $user = Auth::require('student');
        $id = (int) $r->param('id');
        return Db::tx(static function () use ($r, $user, $id, $fn, $requireClient): array {
            $a = AttemptService::ownedBy($user, $id);
            $mock = MockService::find((int) $a['mock_id']);
            if ($requireClient) {
                AttemptService::assertClient($a, $r->str('client_id'));
            }
            return $fn($a, $mock, $user);
        });
    }

    private static function summary(array $a, array $mock): array
    {
        return [
            'now' => Util::nowMs(),
            'status' => $a['status'],
            'stage' => $a['stage'],
            'stage_state' => $a['stage_state'],
            'deadline_ms' => $a['section_deadline_ms'] !== null ? (int) $a['section_deadline_ms'] : null,
            'violations' => (int) $a['violations'],
            'save_seq' => (int) $a['save_seq'],
        ];
    }

    public static function claim(Request $r): array
    {
        return self::withAttempt($r, static function (array $a, array $mock, array $user) use ($r): array {
            $a = AttemptService::refresh($a, $mock);
            if ($a['status'] === 'in_progress') {
                $a = AttemptService::claim($a, $r->str('client_id'), (bool) $r->input('takeover', false));
                $a = AttemptService::refresh($a, $mock);
            }
            return AttemptService::stateFor($a, $mock, $user);
        }, false);
    }

    public static function state(Request $r): array
    {
        return self::withAttempt($r, static function (array $a, array $mock, array $user): array {
            $a = AttemptService::touch(AttemptService::refresh($a, $mock));
            return AttemptService::stateFor($a, $mock, $user);
        });
    }

    public static function sectionStart(Request $r): array
    {
        return self::withAttempt($r, static function (array $a, array $mock, array $user) use ($r): array {
            $a = AttemptService::startSection($a, $mock, $r->str('section'));
            return AttemptService::stateFor(AttemptService::touch($a), $mock, $user);
        });
    }

    public static function sync(Request $r): array
    {
        return self::withAttempt($r, static function (array $a, array $mock) use ($r): array {
            // Avval saqlash (muddat + zaxira vaqt ichida bo'lsa), keyin muddatlarni tekshirish.
            [$a, $accepted] = AttemptService::save($a, $mock, $r->body);
            $events = $r->input('events', []);
            $a = AttemptService::recordEvents($a, is_array($events) ? $events : [], $mock);
            $a = AttemptService::touch(AttemptService::refresh($a, $mock));
            return self::summary($a, $mock) + ['accepted' => $accepted];
        });
    }

    public static function sectionFinish(Request $r): array
    {
        return self::withAttempt($r, static function (array $a, array $mock, array $user) use ($r): array {
            [$a] = AttemptService::save($a, $mock, $r->body);
            $a = AttemptService::finishSection($a, $mock, $r->str('section'));
            return AttemptService::stateFor(AttemptService::touch($a), $mock, $user);
        });
    }

    public static function audio(Request $r): FileResponse
    {
        $user = Auth::require('student');
        $a = AttemptService::ownedBy($user, (int) $r->param('id'));
        $mock = MockService::find((int) $a['mock_id']);
        if ($a['status'] !== 'in_progress' || $a['stage'] !== 'L') {
            throw new HttpError(403, 'forbidden', "Audio faqat Listening bo'limida mavjud.");
        }
        $assetId = (int) $r->param('asset');
        $allowed = array_column(AttemptService::listeningAudioManifest(Util::decode($mock['content_json'])), 'asset');
        if (!in_array($assetId, $allowed, true)) {
            throw new HttpError(404, 'not_found', 'Audio topilmadi.');
        }
        return self::assetFile($assetId, (int) $mock['id'], 'audio', 3 * 3600);
    }

    /** Speaking savoliga tegishli rasm yoki audio (faqat savol ochilgandan keyin). */
    public static function speakingAsset(Request $r): FileResponse
    {
        $user = Auth::require('student');
        $a = AttemptService::ownedBy($user, (int) $r->param('id'));
        $mock = MockService::find((int) $a['mock_id']);
        if ($a['status'] !== 'in_progress' || $a['stage'] !== 'S' || $a['stage_state'] !== 'active') {
            throw new HttpError(403, 'forbidden', "Speaking bo'limi faol emas.");
        }
        $assetId = (int) $r->param('asset');
        $begun = array_map('intval', array_keys((array) (Util::decode($a['meta_json'])['speaking']['begun'] ?? [])));
        foreach (AttemptService::speakingQuestions($mock) as $q) {
            if (!in_array((int) $q['no'], $begun, true)) {
                continue;
            }
            if (in_array($assetId, $q['images'], true)) {
                return self::assetFile($assetId, (int) $mock['id'], 'image', 3600);
            }
            if ($q['audio'] === $assetId) {
                return self::assetFile($assetId, (int) $mock['id'], 'audio', 3600);
            }
        }
        throw new HttpError(404, 'not_found', 'Fayl topilmadi.');
    }

    public static function assetFile(int $assetId, int $mockId, string $kind, int $maxAge): FileResponse
    {
        $asset = Db::one('SELECT * FROM assets WHERE id = ? AND mock_id = ? AND kind = ?', [$assetId, $mockId, $kind]);
        if ($asset === null) {
            throw new HttpError(404, 'not_found', 'Fayl topilmadi.');
        }
        return new FileResponse(Uploads::assetPath($kind, $asset['file']), $asset['mime'], $maxAge);
    }

    public static function speakingStart(Request $r): array
    {
        return self::withAttempt($r, static function (array $a, array $mock, array $user): array {
            $a = AttemptService::speakingStart($a, $mock);
            return AttemptService::stateFor(AttemptService::touch($a), $mock, $user);
        });
    }

    public static function speakingNext(Request $r): array
    {
        return self::withAttempt($r, static function (array $a, array $mock): array {
            [$a, $q] = AttemptService::speakingNext($a, $mock);
            AttemptService::touch($a);
            if ($q === null) {
                return ['done' => true, 'now' => Util::nowMs()];
            }
            return [
                'done' => false,
                'now' => Util::nowMs(),
                'question' => AttemptService::publicQuestion($q) + [
                    'begun_ms' => Util::nowMs(),
                    'window_ms' => AttemptService::speakingWindowMs($q),
                ],
            ];
        });
    }

    public static function speakingUpload(Request $r): array
    {
        $file = $r->files['audio'] ?? null;
        if (!is_array($file)) {
            throw new HttpError(422, 'no_file', 'Audio fayl yuborilmadi.');
        }
        return self::withAttempt($r, static function (array $a, array $mock) use ($r, $file): array {
            $a = AttemptService::speakingUpload($a, $mock, $r->int('q_no'), $file, (float) $r->input('duration', 0));
            AttemptService::touch($a);
            return ['ok' => true, 'q_no' => $r->int('q_no')];
        });
    }

    public static function speakingFinish(Request $r): array
    {
        return self::withAttempt($r, static function (array $a, array $mock, array $user): array {
            $a = AttemptService::speakingFinish($a, $mock);
            return AttemptService::stateFor($a, $mock, $user);
        });
    }
}
