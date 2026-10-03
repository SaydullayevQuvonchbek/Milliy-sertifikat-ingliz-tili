<?php

declare(strict_types=1);

namespace App\Services;

use App\Db;
use App\Http\HttpError;
use App\Settings;
use App\Util;
use PDOException;

/**
 * Urinish (attempt) holatlari mashinasi.
 *
 * Bosqichlar: L → R → W → S → done (mockda bor bo'limlar bo'yicha).
 * Har bosqich avval "pending" (boshlanishini kutmoqda), keyin "active" (taymer ketmoqda).
 * Vaqt har doim serverda hisoblanadi; muddat o'tgan bo'lsa, keyingi so'rovda
 * bo'lim avtomatik yopiladi (cron shart emas).
 */
final class AttemptService
{
    /** Muddatdan keyin ham saqlashni qabul qilish (sekin internet uchun). Qisqa: amalda qo'shimcha vaqt bo'lmasin. */
    public const GRACE_MS = 45_000;
    /** Listening boshlanishidan oldingi 3-2-1 sanog'i. */
    public const LISTENING_LEAD_MS = 3_000;
    /** Birinchi bo'limni boshlash uchun beriladigan vaqt; o'tsa, bo'lim o'zi boshlanadi. */
    public const FIRST_START_WINDOW_MS = 30 * 60_000;
    /** Listening audiosi yuklangandan keyin bo'lim ko'pi bilan shuncha vaqtda boshlanadi. */
    public const AUDIO_PREVIEW_LIMIT_MS = 3 * 60_000;
    /** Shuncha vaqt aloqa bo'lmasa, qurilma "uzilgan" hisoblanadi. */
    public const STALE_CLIENT_MS = 45_000;
    public const SPEAKING_OVERHEAD_MS = 4_000;
    public const SPEAKING_UPLOAD_GRACE_MS = 5 * 60_000;
    /** Keyingi savolni ochishdagi soat farqi uchun zaxira. */
    public const SPEAKING_NEXT_TOLERANCE_MS = 1_500;
    public const SPEAKING_ABANDON_MS = 30 * 60_000;
    public const MAX_EVENTS_PER_REQUEST = 100;
    /** "Vaqt tugamasdan yakunlash" o'chiq bo'lsa ham muddatdan shuncha oldin yakunlash qabul qilinadi (soat farqi). */
    public const EARLY_FINISH_TOLERANCE_MS = 3_000;

    public const VIOLATION_TYPES = ['focus_lost', 'fullscreen_exit', 'page_closed', 'device_takeover', 'multiple_tabs', 'devtools'];
    public const INFO_TYPES = [
        'paste_blocked', 'copy_blocked', 'cut_blocked', 'shortcut_blocked', 'contextmenu_blocked', 'drop_blocked',
        'print_blocked', 'offline', 'online', 'reload', 'returned', 'audio_error', 'audio_resync', 'large_insert',
        'mic_error', 'device_change', 'section_view', 'seb_missing', 'resize',
        // Video nazorat
        'camera_ok', 'camera_none', 'camera_denied', 'camera_lost', 'screen_ok', 'screen_denied', 'screen_wrong',
        'screen_stopped', 'screen_unsupported', 'multi_screen', 'rec_error', 'rec_dropped', 'rec_unsupported',
    ];
    /** Shu hodisalar mock sozlamasida tegishli nazorat "majburiy" bo'lsa qoidabuzarlik hisoblanadi. */
    private const PROCTOR_VIOLATIONS = ['screen_stopped' => 'screen', 'camera_lost' => 'camera'];
    /** Kamera/ekran holati (mijoz yuboradi). */
    public const PROCTOR_STATES = ['off', 'ok', 'none', 'denied', 'wrong', 'unsupported', 'error', 'stopped', 'lost'];

    // ---------------------------------------------------------------------
    // Yordamchilar
    // ---------------------------------------------------------------------

    public static function lock(int $id): array
    {
        $row = Db::one('SELECT * FROM attempts WHERE id = ?' . Db::forUpdate(), [$id]);
        if ($row === null) {
            throw new HttpError(404, 'not_found', 'Urinish topilmadi.');
        }
        return $row;
    }

    public static function ownedBy(array $user, int $id): array
    {
        $row = self::lock($id);
        if ((int) $row['user_id'] !== (int) $user['id']) {
            throw new HttpError(404, 'not_found', 'Urinish topilmadi.');
        }
        return $row;
    }

    private static function persist(array $a, array $changes): array
    {
        if ($changes !== []) {
            Db::update('attempts', $changes, 'id = ?', [$a['id']]);
        }
        return array_merge($a, $changes);
    }

    private static function meta(array $a): array
    {
        return Util::decode($a['meta_json'] ?? '{}');
    }

    /** @return string[] */
    public static function sequence(array $a): array
    {
        return array_values(array_filter(explode(',', (string) $a['sections'])));
    }

    private static function nextStage(array $a, string $current): string
    {
        $seq = self::sequence($a);
        $index = array_search($current, $seq, true);
        if ($index === false || !isset($seq[$index + 1])) {
            return 'done';
        }
        return $seq[$index + 1];
    }

    private static function isFirstStage(array $a, string $stage): bool
    {
        $seq = self::sequence($a);
        return ($seq[0] ?? null) === $stage;
    }

    public static function sectionDurationMs(array $mock, string $stage): int
    {
        $settings = MockService::settings($mock);
        return match ($stage) {
            'L' => MockService::listeningDurationMs(Util::decode($mock['content_json'])['listening'] ?? []),
            'R' => $settings['times']['reading'] * 1000,
            'W' => $settings['times']['writing'] * 1000,
            default => 0,
        };
    }

    public static function autoStartAtMs(array $a, array $mock): ?int
    {
        if (!in_array($a['stage'], ['L', 'R', 'W'], true) || $a['stage_state'] !== 'pending') {
            return null;
        }
        $wait = self::isFirstStage($a, $a['stage'])
            ? self::FIRST_START_WINDOW_MS
            : MockService::settings($mock)['break_sec'] * 1000;
        $at = (int) $a['stage_since_ms'] + $wait;
        $fetched = self::meta($a)['audio_fetch_ms'] ?? null;
        if ($a['stage'] === 'L' && is_numeric($fetched)) {
            $at = min($at, (int) $fetched + self::AUDIO_PREVIEW_LIMIT_MS);
        }
        return $at;
    }

    /** Listening kutish holatida audio birinchi marta yuklanganda vaqtni yozib qo'yish. */
    public static function noteAudioFetch(array $a): array
    {
        if ($a['stage'] !== 'L' || $a['stage_state'] !== 'pending') {
            return $a;
        }
        $meta = self::meta($a);
        if (isset($meta['audio_fetch_ms'])) {
            return $a;
        }
        $meta['audio_fetch_ms'] = Util::nowMs();
        return self::persist($a, ['meta_json' => Util::json($meta)]);
    }

    // ---------------------------------------------------------------------
    // Boshlash
    // ---------------------------------------------------------------------

    /**
     * Mockni boshlash yoki davom ettirish.
     * Qat'iy qoida: bitta o'quvchi bitta mockni ko'pi bilan max_attempts (≤ 2) marta ishlaydi.
     */
    public static function startOrResume(array $user, int $mockId, string $ip = '', string $userAgent = ''): array
    {
        return Db::tx(static function () use ($user, $mockId, $ip, $userAgent): array {
            // O'quvchi qatorini qulflash: bir vaqtda ikki "Boshlash" bosilsa ham bittasi o'tadi.
            Db::one('SELECT id FROM users WHERE id = ?' . Db::forUpdate(), [$user['id']]);
            $mock = MockService::find($mockId);

            $existing = Db::one(
                "SELECT * FROM attempts WHERE mock_id = ? AND user_id = ? AND status = 'in_progress' ORDER BY id DESC LIMIT 1",
                [$mockId, $user['id']]
            );
            if ($existing !== null) {
                return self::refresh($existing, $mock);
            }

            self::assertStartable($mock);

            $used = (int) Db::val('SELECT COUNT(*) FROM attempts WHERE mock_id = ? AND user_id = ?', [$mockId, $user['id']]);
            $max = MockService::maxAttempts($mock);
            if ($used >= $max) {
                throw new HttpError(403, 'attempt_limit', "Siz bu mockni {$max} marta ishlagansiz. Bitta mockni {$max} martadan ortiq ishlab bo'lmaydi.");
            }

            $busy = Db::one(
                "SELECT a.id, m.title FROM attempts a JOIN mocks m ON m.id = a.mock_id
                 WHERE a.user_id = ? AND a.status = 'in_progress' AND a.stage IN ('L', 'R', 'W')",
                [$user['id']]
            );
            if ($busy !== null) {
                throw new HttpError(409, 'other_active', "Sizda yakunlanmagan imtihon bor: \"{$busy['title']}\". Avval uni yakunlang.", ['attempt_id' => (int) $busy['id']]);
            }

            $sections = MockService::sections($mock);
            if ($sections === []) {
                throw new HttpError(422, 'empty_mock', "Bu mockda bo'limlar yo'q.");
            }
            $now = Util::nowMs();
            $row = [
                'mock_id' => $mockId,
                'user_id' => (int) $user['id'],
                'attempt_no' => $used + 1,
                'status' => 'in_progress',
                'sections' => implode(',', $sections),
                'stage' => $sections[0],
                'stage_state' => 'pending',
                'stage_since_ms' => $now,
                'answers_json' => '{}',
                'writing_json' => '{}',
                'meta_json' => '{}',
                'save_seq' => 0,
                'violations' => 0,
                'anon_code' => self::uniqueAnonCode(),
                'started_at' => intdiv($now, 1000),
                'ip' => mb_substr($ip, 0, 64),
                'user_agent' => mb_substr($userAgent, 0, 300),
            ];
            try {
                $id = Db::insert('attempts', $row);
            } catch (PDOException $e) {
                if (Db::isUniqueViolation($e)) {
                    throw new HttpError(409, 'attempt_conflict', "Imtihon allaqachon boshlangan. Sahifani yangilang.");
                }
                throw $e;
            }
            return self::lock($id);
        });
    }

    public static function assertStartable(array $mock): void
    {
        if ($mock['status'] === 'frozen') {
            throw new HttpError(403, 'mock_frozen', "Bu mock vaqtincha muzlatilgan. Yangi urinish boshlab bo'lmaydi.");
        }
        if ($mock['status'] !== 'active') {
            throw new HttpError(403, 'mock_unavailable', 'Bu mock hozir mavjud emas.');
        }
        $now = time();
        if ($mock['available_from'] !== null && $now < (int) $mock['available_from']) {
            throw new HttpError(403, 'not_yet', 'Mock hali ochilmagan: ' . date('d.m.Y H:i', (int) $mock['available_from']) . '.');
        }
        if ($mock['available_to'] !== null && $now > (int) $mock['available_to']) {
            throw new HttpError(403, 'closed', 'Mock muddati tugagan.');
        }
    }

    private static function uniqueAnonCode(): string
    {
        for ($i = 0; $i < 20; $i++) {
            $code = Util::randomCode(6);
            if (!Db::val('SELECT COUNT(*) FROM attempts WHERE anon_code = ?', [$code])) {
                return $code;
            }
        }
        return Util::randomCode(10);
    }

    // ---------------------------------------------------------------------
    // Holatni yangilash (muddatlar)
    // ---------------------------------------------------------------------

    public static function refresh(array $a, ?array $mock = null): array
    {
        if ($a['status'] !== 'in_progress') {
            return $a;
        }
        $mock ??= MockService::find((int) $a['mock_id']);
        $now = Util::nowMs();

        for ($guard = 0; $guard < 12; $guard++) {
            $stage = $a['stage'];
            if (in_array($stage, ['L', 'R', 'W'], true)) {
                if ($a['stage_state'] === 'active') {
                    $deadline = (int) $a['section_deadline_ms'];
                    if ($now > $deadline + self::GRACE_MS) {
                        $a = self::closeSection($a, $mock, $stage, 'timeout', $deadline);
                        continue;
                    }
                    break;
                }
                $autoAt = (int) self::autoStartAtMs($a, $mock);
                $fallback = $autoAt + (self::isFirstStage($a, $stage) ? 0 : self::GRACE_MS);
                if ($now > $fallback) {
                    $a = self::activate($a, $mock, $stage, $autoAt);
                    continue;
                }
                break;
            }

            if ($stage === 'S') {
                $settings = MockService::settings($mock);
                $to = $settings['speaking']['to'];
                if ($a['stage_state'] === 'pending') {
                    if ($to !== null && $now > $to * 1000) {
                        $a = self::closeSpeaking($a, 'closed');
                        continue;
                    }
                    break;
                }
                $endAt = self::speakingAutoCloseMs($a, $mock);
                if ($endAt !== null && $now > $endAt) {
                    $a = self::closeSpeaking($a, 'timeout');
                    continue;
                }
                break;
            }
            break;
        }
        return $a;
    }

    private static function activate(array $a, array $mock, string $stage, int $startMs): array
    {
        $meta = self::markProctorGaps(self::meta($a), $mock, ['camera', 'screen']);
        $meta['sections'][$stage]['started_ms'] = $startMs;
        return self::persist($a, [
            'stage_state' => 'active',
            'section_started_ms' => $startMs,
            'section_deadline_ms' => $startMs + self::sectionDurationMs($mock, $stage),
            'meta_json' => Util::json($meta),
        ]);
    }

    private static function closeSection(array $a, array $mock, string $stage, string $reason, int $atMs): array
    {
        $meta = self::meta($a);
        $meta['sections'][$stage]['finished_ms'] = $atMs;
        $meta['sections'][$stage]['reason'] = $reason;
        if ($stage === 'W') {
            $writing = Util::decode($a['writing_json']);
            $words = 0;
            foreach ($writing as $text) {
                $words += Util::wordCount((string) $text);
            }
            if ($words === 0) {
                $meta['w_auto_zero'] = true;
            }
        }

        $next = self::nextStage($a, $stage);
        $changes = [
            'meta_json' => Util::json($meta),
            'section_started_ms' => null,
            'section_deadline_ms' => null,
        ];
        if ($stage === 'W') {
            $changes['grade_w'] = empty($meta['w_auto_zero']) ? 'queue' : 'done';
        }
        if ($next === 'done') {
            $changes += ['stage' => 'done', 'stage_state' => 'pending', 'status' => 'completed', 'finished_at' => intdiv($atMs, 1000)];
        } else {
            $changes += ['stage' => $next, 'stage_state' => 'pending', 'stage_since_ms' => $atMs];
        }
        $a = self::persist($a, $changes);
        return ScoreService::recompute($a, $mock);
    }

    // ---------------------------------------------------------------------
    // Qurilma (bitta imtihon — bitta oyna)
    // ---------------------------------------------------------------------

    public static function claim(array $a, string $clientId, bool $takeover): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/', $clientId)) {
            throw new HttpError(422, 'bad_client', "Qurilma identifikatori noto'g'ri.");
        }
        $now = Util::nowMs();
        $current = (string) ($a['client_id'] ?? '');
        if ($current === '' || $current === $clientId) {
            return self::persist($a, ['client_id' => $clientId, 'last_seen_ms' => $now]);
        }
        $stale = $a['last_seen_ms'] === null || $now - (int) $a['last_seen_ms'] > self::STALE_CLIENT_MS;
        if ($stale) {
            $a = self::persist($a, ['client_id' => $clientId, 'last_seen_ms' => $now]);
            self::insertEvent($a, 'device_change', 'Oldingi oyna/qurilma aloqasi uzilgan edi.', false, null);
            return $a;
        }
        if (!$takeover) {
            throw new HttpError(409, 'other_device', 'Bu imtihon boshqa oyna yoki qurilmada ochiq.');
        }
        $a = self::persist($a, ['client_id' => $clientId, 'last_seen_ms' => $now]);
        return self::recordEvents($a, [['type' => 'device_takeover', 'detail' => 'Imtihon boshqa oynaga ko\'chirildi.']]);
    }

    public static function assertClient(array $a, string $clientId): void
    {
        if ($clientId === '' || (string) $a['client_id'] !== $clientId) {
            throw new HttpError(409, 'taken_over', 'Imtihon boshqa oyna yoki qurilmada ochildi. Bu oyna yopildi.');
        }
    }

    // ---------------------------------------------------------------------
    // Bo'lim amallari
    // ---------------------------------------------------------------------

    public static function startSection(array $a, array $mock, string $section): array
    {
        $a = self::refresh($a, $mock);
        self::assertInProgress($a);
        if ($a['stage'] !== $section || !in_array($section, ['L', 'R', 'W'], true)) {
            return $a; // Allaqachon keyingi bosqichga o'tilgan — joriy holat qaytariladi.
        }
        if ($a['stage_state'] === 'active') {
            return $a;
        }
        // Majburiy kamera/ekran — imtihonning birinchi bo'limini o'quvchi o'zi boshlaganda tekshiriladi. Keyingi
        // bo'limlarda kamera uzilsa, mijoz imtihonni yopib qo'yadi va bu qoidabuzarlik bo'ladi (tanaffusdagi avtomatik
        // boshlanish to'xtab qolmasligi uchun bu yerda tekshirilmaydi).
        if (self::isFirstStage($a, $section)) {
            self::assertProctoring($a, $mock, ['camera', 'screen']);
        }
        $lead = $section === 'L' ? self::LISTENING_LEAD_MS : 0;
        return self::activate($a, $mock, $section, Util::nowMs() + $lead);
    }

    /** Mockda kamera yoki ekran yozuvi majburiy bo'lsa, ular ishlamaguncha bo'lim boshlanmaydi. */
    private static function assertProctoring(array $a, array $mock, array $kinds): void
    {
        $required = MockService::settings($mock)['proctoring'];
        $status = self::meta($a)['proctor'] ?? [];
        if (in_array('camera', $kinds, true) && $required['camera'] === 'required' && ($status['camera'] ?? null) !== 'ok') {
            throw new HttpError(422, 'camera_required', "Bu imtihon kamera bilan o'tkaziladi. Kamerani yoqing va ruxsat bering.");
        }
        if (in_array('screen', $kinds, true) && $required['screen'] === 'required' && ($status['screen'] ?? null) !== 'ok') {
            throw new HttpError(422, 'screen_required', "Bu imtihonda ekran yozib olinadi. \"Butun ekran\"ni ulashing.");
        }
    }

    /**
     * "Kamerasiz"/"ekransiz" belgisi: nazorat yoqilgan, lekin bo'lim boshlanganda (yoki bo'lim davomida) qurilma
     * ishlamagan bo'lsa. Darvozadagi oraliq holatlar (kamera yoqilib, ekran hali so'ralmagan) belgi qo'ymaydi.
     */
    private static function markProctorGaps(array $meta, array $mock, array $kinds): array
    {
        $settings = MockService::settings($mock)['proctoring'];
        foreach ($kinds as $kind) {
            if ($settings[$kind] === 'off') {
                continue;
            }
            if (($meta['proctor'][$kind] ?? null) !== 'ok') {
                $meta['proctor'][$kind . '_missing'] = true;
            }
        }
        return $meta;
    }

    /**
     * Sahifa yangidan ochilganda (claim): oldingi oynadagi kamera/ekran endi ishlamaydi — "ishlayapti" holati
     * o'chiriladi, o'quvchi ularni qayta yoqqach yangilanadi. Aks holda tanaffusda sahifa yangilansa, keyingi bo'lim
     * yozuvsiz, belgisiz boshlanib ketardi.
     */
    public static function resetProctorLive(array $a): array
    {
        $meta = self::meta($a);
        if (!isset($meta['proctor']['camera']) && !isset($meta['proctor']['screen'])) {
            return $a;
        }
        unset($meta['proctor']['camera'], $meta['proctor']['screen']);
        return self::persist($a, ['meta_json' => Util::json($meta)]);
    }

    /**
     * Mijozdagi kamera va ekran holatini saqlash (darvozadan o'tishda va o'zgarganda). Mijoz hali so'ralmagan
     * qurilmani yubormaydi. Bo'lim faol paytdagi nosozlik "kamerasiz"/"ekransiz" belgisini qo'yadi.
     */
    public static function setProctorStatus(array $a, array $mock, array $input): array
    {
        if ($a['status'] !== 'in_progress') {
            return $a;
        }
        $settings = MockService::settings($mock)['proctoring'];
        $meta = self::meta($a);
        $old = (array) ($meta['proctor'] ?? []);
        $new = $old;
        $active = $a['stage_state'] === 'active';
        foreach (['camera', 'screen'] as $kind) {
            $value = $input[$kind] ?? null;
            if ($settings[$kind] === 'off') {
                $new[$kind] = 'off';
            } elseif (is_string($value) && in_array($value, self::PROCTOR_STATES, true)) {
                $new[$kind] = $value;
                // Speaking'da ekran yozilmaydi — u yerdagi ekran holati belgi qo'ymaydi.
                if ($active && !in_array($value, ['ok', 'off'], true) && !($kind === 'screen' && $a['stage'] === 'S')) {
                    $new[$kind . '_missing'] = true;
                }
            }
        }
        if (array_key_exists('screens', $input) && is_numeric($input['screens'])) {
            $new['screens'] = max(1, min(9, (int) $input['screens']));
        }
        if ($new === $old) {
            return $a;
        }
        $new['at_ms'] = Util::nowMs();
        $meta['proctor'] = $new;
        return self::persist($a, ['meta_json' => Util::json($meta)]);
    }

    /**
     * Javoblarni saqlash. Mijoz har safar bo'limning to'liq holatini yuboradi,
     * seq esa eski (kechikib kelgan) so'rov yangisini ustidan yozmasligi uchun.
     * @return array{0: array, 1: bool} [attempt, accepted]
     */
    public static function save(array $a, array $mock, array $payload): array
    {
        $section = (string) ($payload['section'] ?? '');
        $seq = (int) ($payload['seq'] ?? 0);
        $now = Util::nowMs();
        if (
            $a['status'] !== 'in_progress'
            || $a['stage'] !== $section
            || $a['stage_state'] !== 'active'
            || !in_array($section, ['L', 'R', 'W'], true)
            || $now > (int) $a['section_deadline_ms'] + self::GRACE_MS
            || $now < (int) $a['section_started_ms'] - 60_000
        ) {
            return [$a, false];
        }
        if ($seq <= (int) $a['save_seq']) {
            return [$a, false];
        }

        $changes = ['save_seq' => $seq];
        if ($section === 'L' || $section === 'R') {
            $key = Util::decode($mock['key_json'])[$section] ?? [];
            $clean = [];
            foreach ((array) ($payload['answers'] ?? []) as $n => $value) {
                $n = (string) $n;
                if (isset($key[$n]) && is_scalar($value)) {
                    $value = trim(Util::cleanText($value, 200));
                    if ($value !== '') {
                        $clean[$n] = $value;
                    }
                }
            }
            $answers = Util::decode($a['answers_json']);
            $answers[$section] = $clean;
            $changes['answers_json'] = Util::json($answers);
        } else {
            $taskIds = self::writingTaskIds($mock);
            $clean = [];
            foreach ((array) ($payload['writing'] ?? []) as $taskId => $text) {
                if (in_array((string) $taskId, $taskIds, true) && is_scalar($text)) {
                    $clean[(string) $taskId] = Util::cleanText($text, 20000);
                }
            }
            $changes['writing_json'] = Util::json($clean);
            if (is_array($payload['wstats'] ?? null)) {
                $meta = self::meta($a);
                foreach ($payload['wstats'] as $taskId => $stats) {
                    if (in_array((string) $taskId, $taskIds, true) && is_array($stats)) {
                        $meta['wstats'][(string) $taskId] = [
                            'keys' => max(0, (int) ($stats['keys'] ?? 0)),
                            'blocked' => max(0, (int) ($stats['blocked'] ?? 0)),
                            'large' => max(0, (int) ($stats['large'] ?? 0)),
                        ];
                    }
                }
                $changes['meta_json'] = Util::json($meta);
            }
        }
        return [self::persist($a, $changes), true];
    }

    /** @return string[] */
    public static function writingTaskIds(array $mock): array
    {
        $ids = [];
        foreach ((Util::decode($mock['content_json'])['writing']['parts'] ?? []) as $part) {
            foreach ($part['tasks'] ?? [] as $task) {
                $ids[] = (string) $task['id'];
            }
        }
        return $ids;
    }

    public static function finishSection(array $a, array $mock, string $section): array
    {
        $a = self::refresh($a, $mock);
        if ($a['status'] !== 'in_progress' || $a['stage'] !== $section || $a['stage_state'] !== 'active') {
            return $a; // Allaqachon yopilgan — takroriy so'rov xato emas.
        }
        $at = min(Util::nowMs(), (int) $a['section_deadline_ms']);
        // Administrator "vaqt tugamasdan yakunlash"ni o'chirgan bo'lsa — faqat vaqt tugaganda.
        if (
            !MockService::settings($mock)['flow']['early_finish']
            && Util::nowMs() < (int) $a['section_deadline_ms'] - self::EARLY_FINISH_TOLERANCE_MS
        ) {
            throw new HttpError(422, 'early_finish_disabled', "Bu mockda bo'limni vaqt tugamasdan yakunlab bo'lmaydi.");
        }
        // Listening audio tugamasdan yakunlab bo'lmaydi.
        if ($section === 'L' && Util::nowMs() < (int) $a['section_deadline_ms'] - self::listeningReviewMs($mock)) {
            throw new HttpError(422, 'listening_running', "Listening audiosi tugamaguncha bo'limni yakunlab bo'lmaydi.");
        }
        return self::closeSection($a, $mock, $section, 'submitted', $at);
    }

    private static function listeningReviewMs(array $mock): int
    {
        return (int) ((Util::decode($mock['content_json'])['listening']['review_sec'] ?? 0) * 1000);
    }

    private static function assertInProgress(array $a): void
    {
        if ($a['status'] === 'terminated') {
            throw new HttpError(409, 'terminated', 'Imtihon qoidabuzarlik sababli yakunlangan.');
        }
        if ($a['status'] !== 'in_progress') {
            throw new HttpError(409, 'finished', 'Imtihon yakunlangan.');
        }
    }

    // ---------------------------------------------------------------------
    // Hodisalar va qoidabuzarliklar
    // ---------------------------------------------------------------------

    public static function recordEvents(array $a, array $events, ?array $mock = null): array
    {
        if ($events === []) {
            return $a;
        }
        $mock ??= MockService::find((int) $a['mock_id']);
        $proctor = MockService::settings($mock)['proctoring'];
        $newViolations = 0;
        // Qoidabuzarliklar birinchi ko'rib chiqiladi — ko'p ma'lumot hodisasi ularni "siqib chiqara" olmaydi.
        $events = array_values(array_filter($events, 'is_array'));
        usort($events, static fn ($x, $y) => (int) !in_array($x['type'] ?? '', self::VIOLATION_TYPES, true)
            <=> (int) !in_array($y['type'] ?? '', self::VIOLATION_TYPES, true));
        foreach (array_slice($events, 0, self::MAX_EVENTS_PER_REQUEST) as $event) {
            $type = (string) ($event['type'] ?? '');
            $isViolation = in_array($type, self::VIOLATION_TYPES, true)
                || (isset(self::PROCTOR_VIOLATIONS[$type]) && $proctor[self::PROCTOR_VIOLATIONS[$type]] === 'required');
            if (!$isViolation && !in_array($type, self::INFO_TYPES, true)) {
                continue;
            }
            $detail = mb_substr(Util::cleanText($event['detail'] ?? '', 300), 0, 300);
            $clientMs = is_numeric($event['t'] ?? null) ? (int) $event['t'] : null;
            // Mijoz hodisani qayta yuborishi mumkin (javob kelmay turib sahifa yopilsa) — bir marta hisoblanadi.
            $key = is_string($event['id'] ?? null) && preg_match('/^[A-Za-z0-9]{6,32}$/', $event['id']) ? $event['id'] : null;
            if ($key !== null && Db::val('SELECT COUNT(*) FROM attempt_events WHERE attempt_id = ? AND event_key = ?', [$a['id'], $key])) {
                continue;
            }
            $section = in_array($event['section'] ?? null, ['L', 'R', 'W', 'S'], true) ? $event['section'] : null;
            // Hisoblanadi: imtihon davom etayotgan bo'lsa va hodisa bo'lim faol paytda sodir bo'lgan bo'lsa
            // (internet uzilib, kechikib kelgan hodisa ham — mijoz uni "active" deb belgilaydi).
            $happenedActive = $a['stage_state'] === 'active' || ($event['active'] ?? false) === true;
            $counted = $isViolation && $a['status'] === 'in_progress' && $a['stage'] !== 'done' && $happenedActive;
            self::insertEvent($a, $type, $detail, $counted, $clientMs, $key, $section);
            if ($counted) {
                $newViolations++;
            }
        }
        if ($newViolations === 0) {
            return $a;
        }

        $a = self::persist($a, ['violations' => (int) $a['violations'] + $newViolations]);
        $lockdown = MockService::settings($mock)['lockdown'];
        $max = (int) $lockdown['max_violations'];
        if ($lockdown['action'] === 'terminate' && $max > 0 && (int) $a['violations'] > $max) {
            $a = self::terminate($a, $mock, "Qoidabuzarliklar soni {$max} tadan oshdi.");
        }
        return $a;
    }

    private static function insertEvent(array $a, string $type, string $detail, bool $violation, ?int $clientMs, ?string $key = null, ?string $section = null): void
    {
        Db::insert('attempt_events', [
            'attempt_id' => $a['id'],
            'type' => $type,
            'section' => $section ?? (in_array($a['stage'], ['L', 'R', 'W', 'S'], true) ? $a['stage'] : null),
            'detail' => $detail,
            'is_violation' => $violation ? 1 : 0,
            'created_ms' => Util::nowMs(),
            'client_ms' => $clientMs,
            'event_key' => $key,
        ]);
    }

    public static function terminate(array $a, array $mock, string $reason): array
    {
        if ($a['status'] !== 'in_progress') {
            return $a;
        }
        $now = Util::nowMs();
        $meta = self::meta($a);
        if (in_array($a['stage'], ['L', 'R', 'W'], true) && $a['stage_state'] === 'active') {
            $meta['sections'][$a['stage']]['finished_ms'] = $now;
            $meta['sections'][$a['stage']]['reason'] = 'terminated';
        }
        $meta['terminated'] = ['at_ms' => $now, 'reason' => $reason];
        self::insertEvent($a, 'terminated', $reason, false, null);
        $a = self::persist($a, [
            'status' => 'terminated',
            'stage' => 'done',
            'stage_state' => 'pending',
            'section_started_ms' => null,
            'section_deadline_ms' => null,
            'finished_at' => intdiv($now, 1000),
            'meta_json' => Util::json($meta),
        ]);
        return ScoreService::recompute($a, $mock);
    }

    public static function touch(array $a): array
    {
        return self::persist($a, ['last_seen_ms' => Util::nowMs()]);
    }

    // ---------------------------------------------------------------------
    // Speaking
    // ---------------------------------------------------------------------

    /** @return array<int, array> Barcha savollar tartib bilan (qism ma'lumoti bilan). */
    public static function speakingQuestions(array $mock): array
    {
        $out = [];
        foreach ((Util::decode($mock['content_json'])['speaking']['parts'] ?? []) as $part) {
            foreach ($part['questions'] ?? [] as $q) {
                $out[] = $q + [
                    'part_id' => $part['id'],
                    'part_title' => $part['title'],
                    'instructions' => $part['instructions'],
                    'images' => $part['images'],
                    'topic' => $part['topic'],
                    'for' => $part['for'],
                    'against' => $part['against'],
                ];
            }
        }
        return $out;
    }

    public static function speakingWindowMs(array $q): int
    {
        return (int) round(((float) ($q['audio_duration'] ?? 0) + (int) $q['prep_sec'] + (int) $q['answer_sec']) * 1000)
            + self::SPEAKING_OVERHEAD_MS;
    }

    private static function speakingAutoCloseMs(array $a, array $mock): ?int
    {
        $meta = self::meta($a);
        $begun = (array) ($meta['speaking']['begun'] ?? []);
        $questions = self::speakingQuestions($mock);
        if ($begun === []) {
            return (int) ($meta['speaking']['started_ms'] ?? $a['stage_since_ms']) + self::SPEAKING_ABANDON_MS;
        }
        $lastNo = max(array_map('intval', array_keys($begun)));
        $last = null;
        foreach ($questions as $q) {
            if ((int) $q['no'] === $lastNo) {
                $last = $q;
            }
        }
        $lastEnd = (int) $begun[(string) $lastNo] + ($last ? self::speakingWindowMs($last) : 0);
        return $lastEnd + (count($begun) >= count($questions) ? self::SPEAKING_UPLOAD_GRACE_MS : self::SPEAKING_ABANDON_MS);
    }

    public static function speakingStart(array $a, array $mock): array
    {
        $a = self::refresh($a, $mock);
        self::assertInProgress($a);
        if ($a['stage'] !== 'S') {
            throw new HttpError(409, 'not_speaking', "Speaking bo'limi hali ochilmagan.");
        }
        if ($a['stage_state'] === 'active') {
            return $a;
        }
        $speaking = MockService::settings($mock)['speaking'];
        $now = time();
        if ($speaking['from'] !== null && $now < $speaking['from']) {
            throw new HttpError(403, 'speaking_not_yet', "Speaking " . date('d.m.Y H:i', $speaking['from']) . " dan boshlanadi.");
        }
        // Speaking'da faqat kamera (va ovoz) yoziladi — ekran so'ralmaydi.
        self::assertProctoring($a, $mock, ['camera']);
        $meta = self::markProctorGaps(self::meta($a), $mock, ['camera']);
        $meta['speaking'] = ['started_ms' => Util::nowMs(), 'begun' => []];
        return self::persist($a, ['stage_state' => 'active', 'meta_json' => Util::json($meta)]);
    }

    /** Keyingi savolni ochish. @return array{0: array, 1: ?array} */
    public static function speakingNext(array $a, array $mock): array
    {
        $a = self::refresh($a, $mock);
        self::assertInProgress($a);
        if ($a['stage'] !== 'S' || $a['stage_state'] !== 'active') {
            throw new HttpError(409, 'not_speaking', "Speaking bo'limi faol emas.");
        }
        $meta = self::meta($a);
        $begun = (array) ($meta['speaking']['begun'] ?? []);
        $questions = self::speakingQuestions($mock);
        $index = count($begun);
        // Oldingi savol vaqti tugamaguncha keyingisi ochilmaydi (savollarni oldindan ko'rib bo'lmaydi).
        // Administrator "javobni erta tugatish"ga ruxsat bergan bo'lsa — oldingi savolga javob yuklangach o'quvchi
        // o'zi keyingisiga o'tadi (javobsiz ketma-ket ochib, hamma savolni oldindan ko'rib bo'lmaydi).
        $skipped = false;
        if ($index > 0 && MockService::settings($mock)['flow']['speaking_skip']) {
            $skipped = (bool) Db::val(
                'SELECT COUNT(*) FROM speaking_answers WHERE attempt_id = ? AND q_no = ?',
                [$a['id'], (int) $questions[$index - 1]['no']]
            );
        }
        if ($index > 0 && !$skipped) {
            $previous = $questions[$index - 1];
            $readyAt = (int) $begun[(string) $previous['no']] + self::speakingWindowMs($previous)
                - self::SPEAKING_OVERHEAD_MS - self::SPEAKING_NEXT_TOLERANCE_MS;
            $wait = $readyAt - Util::nowMs();
            if ($wait > 0) {
                throw new HttpError(425, 'too_early', 'Oldingi savol vaqti hali tugamagan.', ['wait_ms' => $wait]);
            }
        }
        if (!isset($questions[$index])) {
            return [$a, null];
        }
        $q = $questions[$index];
        $meta['speaking']['begun'][(string) $q['no']] = Util::nowMs();
        $a = self::persist($a, ['meta_json' => Util::json($meta)]);
        return [$a, $q];
    }

    public static function speakingUpload(array $a, array $mock, int $qNo, array $file, float $duration): array
    {
        $a = self::refresh($a, $mock);
        self::assertInProgress($a);
        if ($a['stage'] !== 'S' || $a['stage_state'] !== 'active') {
            throw new HttpError(409, 'not_speaking', "Speaking bo'limi faol emas.");
        }
        $meta = self::meta($a);
        $begunAt = $meta['speaking']['begun'][(string) $qNo] ?? null;
        $question = null;
        foreach (self::speakingQuestions($mock) as $q) {
            if ((int) $q['no'] === $qNo) {
                $question = $q;
            }
        }
        if ($begunAt === null || $question === null) {
            throw new HttpError(422, 'bad_question', 'Bu savol hali ochilmagan.');
        }
        if (Util::nowMs() > (int) $begunAt + self::speakingWindowMs($question) + self::SPEAKING_UPLOAD_GRACE_MS) {
            throw new HttpError(422, 'too_late', 'Javob yuklash muddati tugagan.');
        }
        if (Db::val('SELECT COUNT(*) FROM speaking_answers WHERE attempt_id = ? AND q_no = ?', [$a['id'], $qNo])) {
            return $a; // Takroriy yuklash (qayta urinish) — birinchisi saqlanadi.
        }
        if ($duration > (int) $question['answer_sec'] + 10) {
            throw new HttpError(422, 'too_long', 'Yozuv ruxsat etilgan vaqtdan uzun.');
        }

        $stored = Uploads::storeSpeaking($file, (int) $a['id'], $qNo);
        try {
            Db::insert('speaking_answers', [
                'attempt_id' => $a['id'],
                'q_no' => $qNo,
                'file' => $stored['file'],
                'mime' => $stored['mime'],
                'size' => $stored['size'],
                'duration' => round(max(0.0, $duration), 2),
                'created_at' => time(),
            ]);
        } catch (PDOException $e) {
            @unlink(Uploads::speakingPath($stored['file']));
            if (!Db::isUniqueViolation($e)) {
                throw $e;
            }
        }
        return $a;
    }

    public static function speakingFinish(array $a, array $mock): array
    {
        $a = self::refresh($a, $mock);
        if ($a['status'] !== 'in_progress' || $a['stage'] !== 'S' || $a['stage_state'] !== 'active') {
            return $a;
        }
        $meta = self::meta($a);
        if (count((array) ($meta['speaking']['begun'] ?? [])) < count(self::speakingQuestions($mock))) {
            throw new HttpError(422, 'speaking_unfinished', 'Hali barcha savollarga javob berilmagan.');
        }
        return self::closeSpeaking($a, 'submitted');
    }

    private static function closeSpeaking(array $a, string $reason): array
    {
        $meta = self::meta($a);
        $meta['speaking']['finished_ms'] = Util::nowMs();
        $meta['speaking']['reason'] = $reason;
        if (!Db::val('SELECT COUNT(*) FROM speaking_answers WHERE attempt_id = ?', [$a['id']])) {
            $meta['s_auto_zero'] = true;
        }
        $a = self::persist($a, [
            'stage' => 'done',
            'stage_state' => 'pending',
            'status' => 'completed',
            'finished_at' => time(),
            'meta_json' => Util::json($meta),
            'grade_s' => empty($meta['s_auto_zero']) ? 'queue' : 'done',
        ]);
        return ScoreService::recompute($a);
    }

    // ---------------------------------------------------------------------
    // Mijozga yuboriladigan holat
    // ---------------------------------------------------------------------

    public static function stateFor(array $a, array $mock, array $user): array
    {
        $settings = MockService::settings($mock);
        $content = Util::decode($mock['content_json']);
        $meta = self::meta($a);
        $state = [
            'attempt' => [
                'id' => (int) $a['id'],
                'attempt_no' => (int) $a['attempt_no'],
                'status' => $a['status'],
                'stage' => $a['stage'],
                'stage_state' => $a['stage_state'],
                'sections' => self::sequence($a),
                'violations' => (int) $a['violations'],
                'save_seq' => (int) $a['save_seq'],
                'candidate_no' => $a['anon_code'],
            ],
            'mock' => [
                'id' => (int) $mock['id'],
                'title' => $mock['title'],
                'lockdown' => $settings['lockdown'],
                'break_sec' => $settings['break_sec'],
                'speaking_mode' => $settings['speaking']['mode'],
                'flow' => $settings['flow'],
                'proctoring' => $settings['proctoring'] + [
                    'video_kbps' => Settings::int('rec_video_kbps'),
                    'segment_sec' => Settings::int('rec_segment_min') * 60,
                ],
            ],
            'candidate' => ['name' => $user['full_name']],
            'now' => Util::nowMs(),
            'section' => null,
        ];
        if ($a['status'] === 'terminated') {
            $state['terminated_reason'] = $meta['terminated']['reason'] ?? '';
        }
        if ($a['status'] !== 'in_progress') {
            return $state;
        }

        $stage = $a['stage'];
        $answers = Util::decode($a['answers_json']);
        if (in_array($stage, ['L', 'R', 'W'], true)) {
            $section = ['code' => $stage, 'state' => $a['stage_state']];
            if ($a['stage_state'] === 'pending') {
                $section['auto_start_ms'] = self::autoStartAtMs($a, $mock);
                $section['duration_ms'] = self::sectionDurationMs($mock, $stage);
                if ($stage === 'L') {
                    $section['audio'] = self::listeningAudioManifest($content);
                }
            } else {
                $section['started_ms'] = (int) $a['section_started_ms'];
                $section['deadline_ms'] = (int) $a['section_deadline_ms'];
                $section['grace_ms'] = self::GRACE_MS;
                if ($stage === 'L') {
                    $section['content'] = $content['listening'];
                    $section['audio'] = self::listeningAudioManifest($content);
                    $section['answers'] = (object) ($answers['L'] ?? []);
                } elseif ($stage === 'R') {
                    $section['content'] = $content['reading'];
                    $section['answers'] = (object) ($answers['R'] ?? []);
                } else {
                    $section['content'] = $content['writing'];
                    $section['writing'] = (object) Util::decode($a['writing_json']);
                }
            }
            $state['section'] = $section;
            return $state;
        }

        if ($stage === 'S') {
            $questions = self::speakingQuestions($mock);
            $begun = (array) ($meta['speaking']['begun'] ?? []);
            $uploaded = array_map('intval', array_column(
                Db::all('SELECT q_no FROM speaking_answers WHERE attempt_id = ?', [$a['id']]),
                'q_no'
            ));
            $section = [
                'code' => 'S',
                'state' => $a['stage_state'],
                'total' => count($questions),
                'begun' => array_map('intval', array_keys($begun)),
                'uploaded' => $uploaded,
                'from' => $settings['speaking']['from'],
                'to' => $settings['speaking']['to'],
            ];
            if ($a['stage_state'] === 'active' && $begun !== []) {
                $lastNo = max(array_map('intval', array_keys($begun)));
                foreach ($questions as $q) {
                    if ((int) $q['no'] === $lastNo) {
                        $section['current'] = self::publicQuestion($q) + [
                            'begun_ms' => (int) $begun[(string) $lastNo],
                            'window_ms' => self::speakingWindowMs($q),
                        ];
                    }
                }
            }
            $state['section'] = $section;
        }
        return $state;
    }

    public static function publicQuestion(array $q): array
    {
        return [
            'no' => (int) $q['no'],
            'part_id' => $q['part_id'],
            'part_title' => $q['part_title'],
            'instructions' => $q['instructions'],
            'text' => $q['text'],
            'prep_sec' => (int) $q['prep_sec'],
            'answer_sec' => (int) $q['answer_sec'],
            'audio' => $q['audio'],
            'audio_duration' => (float) $q['audio_duration'],
            'images' => $q['images'],
            'topic' => $q['topic'],
            'for' => $q['for'],
            'against' => $q['against'],
        ];
    }

    /** @return array<int, array{asset:int, duration:float}> */
    public static function listeningAudioManifest(array $content): array
    {
        $out = [];
        foreach ($content['listening']['parts'] ?? [] as $part) {
            foreach ($part['tracks'] ?? [] as $track) {
                if (!empty($track['asset'])) {
                    $out[(int) $track['asset']] = ['asset' => (int) $track['asset'], 'duration' => (float) $track['duration']];
                }
            }
        }
        return array_values($out);
    }
}
