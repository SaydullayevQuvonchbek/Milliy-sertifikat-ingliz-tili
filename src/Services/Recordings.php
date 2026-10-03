<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use App\Db;
use App\Http\HttpError;
use App\Settings;
use App\Util;
use finfo;

/**
 * Video nazorat yozuvlari (ekran + kamera; Speaking'da kamera + ovoz).
 *
 * Brauzer har ~15 soniyada bitta bo'lak (piece) yuboradi; bo'laklar alohida fayllarga yoziladi (qayta yuborilsa
 * ustiga yoziladi — takrorlanmaydi). Oxirgi bo'lak kelganda yoki yozuv uzoq vaqt to'xtab qolsa, bo'laklar bitta
 * faylga yig'iladi (segment, ~10 daqiqa, 49 MB gacha) va Telegram navbatiga qo'yiladi.
 *
 * Holatlar: recording → ready → sent (Telegram'ga ketdi) | failed (ko'p urinishdan keyin) | expired (o'chirildi).
 * Uzilib qolgan (to'liq bo'lmagan) yozuv hali yuborilmagan bo'lsa, internet tiklanganda davom ettiriladi.
 * Yozma qism videolari Telegram'ga yuborilgach serverdan o'chiriladi; Speaking videolari serverda qoladi.
 *
 * Fayl amallari takrorlanganda ham to'g'ri natija beradi (baza tranzaksiyasi qaytarilsa yoki qayta urinilsa):
 * yig'ish har safar bo'laklardan noldan quriladi, bo'laklar faqat yozuv to'liq tugagach o'chiriladi.
 */
final class Recordings
{
    public const PIECE_LIMIT = 12 * 1024 * 1024;
    /** Telegram bot 50 MB gacha fayl yuboradi. */
    public const SEGMENT_LIMIT = 49 * 1024 * 1024;
    /** Imtihon davom etayotgan bo'lsa: shuncha vaqt bo'lak kelmasa yozuv yopiladi (brauzer internet uzilganda kutadi). */
    public const STALE_ACTIVE_MS = 2 * 3600_000;
    /** Imtihon tugagan bo'lsa: oxirgi bo'lakdan shuncha vaqt o'tgach yozuv yopiladi. */
    public const STALE_DONE_MS = 30 * 60_000;
    /** Yangi yozuv boshlanganda shu urinishning boshqa ochiq yozuvlari faqat shuncha vaqt jim bo'lsa yopiladi. */
    public const IDLE_CLOSE_MS = 45_000;
    /** Imtihon tugagandan keyin ham bo'laklar shuncha vaqt qabul qilinadi (internet kech tiklansa). */
    public const AFTER_FINISH_GRACE_SEC = 3 * 3600;
    /** Bitta urinishda ko'pi bilan shuncha yozuv (sahifa ko'p yangilansa ham yetadi). */
    public const MAX_SEGMENTS_PER_ATTEMPT = 300;
    /** Bir daqiqada yangi yozuvlar soni (suiiste'molga qarshi). */
    public const MAX_NEW_SEGMENTS_PER_MINUTE = 8;
    /** Urinish boshidan beri o'tgan vaqtga nisbatan ruxsat etilgan hajm (kbit/s) — eng yuqori sifatdan ham ko'p. */
    private const ATTEMPT_KBPS_CAP = 2600;
    public const CONTENTS = ['screen+camera', 'screen', 'camera'];
    /** Telegram: bitta kanalga daqiqasiga 20 tagacha xabar — har yuborish orasida kamida shuncha soniya. */
    public const SEND_SPACING_SEC = 3.2;
    public const MAX_TRIES = 12;
    private const MIMES = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
    /** Birinchi bo'lakning haqiqiy turi (finfo) — shulardan biri bo'lishi kerak. */
    private const DETECTED = ['video/mp4', 'video/webm', 'video/x-matroska', 'audio/webm', 'audio/mp4', 'video/quicktime'];

    // ---------------------------------------------------------------------
    // Fayl yo'llari va format
    // ---------------------------------------------------------------------

    public static function root(): string
    {
        return Config::storagePath('recordings');
    }

    /** Bazadagi nisbiy nom ("a12/abc.mp4") → to'liq yo'l. */
    public static function path(string $file): string
    {
        $parts = array_values(array_filter(explode('/', str_replace('\\', '/', $file)), static fn ($p) => $p !== '' && $p !== '.' && $p !== '..'));
        return self::root() . '/' . implode('/', $parts);
    }

    private static function partPath(array $row, int $n): string
    {
        return self::path('a' . (int) $row['attempt_id'] . '/' . $row['seg_key'] . '.' . $n . '.part');
    }

    public static function baseMime(string $mime): ?string
    {
        $base = strtolower(trim(explode(';', $mime)[0]));
        return isset(self::MIMES[$base]) ? $base : null;
    }

    /** "video/mp4;codecs=avc1.42E01E,mp4a.40.2" → "avc1" (birinchi — video kodek). */
    public static function videoCodec(string $mime): ?string
    {
        if (!preg_match('/codecs\s*=\s*"?([A-Za-z0-9]+)/i', $mime, $m)) {
            return null;
        }
        $codec = strtolower($m[1]);
        return in_array($codec, ['avc1', 'avc3', 'vp8', 'vp9', 'vp09', 'av01', 'hvc1', 'hev1', 'h264'], true) ? $codec : null;
    }

    /** Telegram ichida video sifatida ko'rinadimi (MP4 + H.264); aks holda fayl (hujjat) sifatida yuboriladi. */
    public static function telegramPlayable(array $row): bool
    {
        return $row['mime'] === 'video/mp4' && in_array((string) ($row['codec'] ?? ''), ['avc1', 'avc3', 'h264'], true);
    }

    // ---------------------------------------------------------------------
    // Bo'lak qabul qilish
    // ---------------------------------------------------------------------

    /**
     * Chaqiruvchi urinish qatorini qulflagan bo'lishi kerak (ExamController::withAttempt) — bitta urinishning
     * so'rovlari navbat bilan bajariladi.
     *
     * @param array $in seg, piece, section, content, audio, mime, final, duration_ms, width, height
     * @param array|null $file $_FILES['data']
     * @return array{ok:bool, expected:int, done?:bool, duplicate?:bool}
     */
    public static function acceptPiece(array $a, array $mock, array $in, ?array $file): array
    {
        $settings = MockService::settings($mock)['proctoring'];
        if ($settings['camera'] === 'off' && $settings['screen'] === 'off') {
            throw new HttpError(403, 'rec_disabled', "Bu mockda video nazorat o'chirilgan.");
        }
        if ($a['status'] !== 'in_progress') {
            $finished = (int) ($a['finished_at'] ?? 0);
            if ($finished === 0 || time() - $finished > self::AFTER_FINISH_GRACE_SEC) {
                throw new HttpError(409, 'rec_closed', 'Imtihon yakunlangan — yozuv qabul qilinmaydi.');
            }
        }

        $seg = (string) ($in['seg'] ?? '');
        if (!preg_match('/^[A-Za-z0-9]{12,32}$/', $seg)) {
            throw new HttpError(422, 'bad_segment', "Yozuv identifikatori noto'g'ri.");
        }
        $piece = filter_var($in['piece'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000]]);
        if ($piece === false) {
            throw new HttpError(422, 'bad_piece', "Bo'lak raqami noto'g'ri.");
        }
        $section = (string) ($in['section'] ?? '');
        if (!in_array($section, AttemptService::sequence($a), true)) {
            throw new HttpError(422, 'bad_section', "Bo'lim noto'g'ri.");
        }
        $final = in_array((string) ($in['final'] ?? '0'), ['1', 'true'], true);
        $durationMs = max(0, min(3_600_000, (int) ($in['duration_ms'] ?? 0)));

        [$tmp, $size] = self::checkUpload($file, $final);
        $now = Util::nowMs();

        // Urinish qatori qulflangan — bu yerda qo'shimcha qulf (FOR UPDATE) shart emas (MySQL'da u boshqa
        // urinishlar bilan "deadlock" berishi mumkin edi).
        $row = Db::one('SELECT * FROM recordings WHERE attempt_id = ? AND seg_key = ?', [$a['id'], $seg]);
        if ($row === null) {
            if ($piece !== 0) {
                throw new HttpError(409, 'piece_gap', "Yozuvning boshi yo'q.", ['expected' => 0]);
            }
            $mime = self::baseMime((string) ($in['mime'] ?? ''));
            $content = (string) ($in['content'] ?? '');
            if ($mime === null || !in_array($content, self::CONTENTS, true)) {
                throw new HttpError(415, 'bad_type', "Video formati qo'llab-quvvatlanmaydi.");
            }
            if ($tmp === null) {
                throw new HttpError(422, 'empty_piece', "Bo'lak bo'sh.");
            }
            $detected = (string) (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
            if (!in_array($detected, self::DETECTED, true)) {
                throw new HttpError(415, 'bad_type', "Video formati qo'llab-quvvatlanmaydi ({$detected}).");
            }
            self::assertSegmentQuota($a, $now);
            // Shu urinishning uzoq jim turgan ochiq yozuvlari (sahifa yangilangan, eski oyna) yopiladi.
            // Hali bo'lak kelayotgan yozuvga tegilmaydi (masalan, eski oynaning navbati hali yuborilayotgan bo'lsa).
            foreach (Db::all("SELECT * FROM recordings WHERE attempt_id = ? AND status = 'recording' AND last_ms < ?", [$a['id'], $now - self::IDLE_CLOSE_MS]) as $open) {
                self::finalize($open);
            }
            $id = Db::insert('recordings', [
                'attempt_id' => $a['id'],
                'seg_key' => $seg,
                'section' => $section,
                'content' => $content,
                'has_audio' => in_array((string) ($in['audio'] ?? '0'), ['1', 'true'], true) ? 1 : 0,
                'mime' => $mime,
                'codec' => self::videoCodec((string) ($in['mime'] ?? '')),
                'file' => 'a' . (int) $a['id'] . '/' . $seg . '.' . self::MIMES[$mime],
                'size' => 0,
                'pieces' => 0,
                'started_ms' => $now - $durationMs,
                'last_ms' => $now,
                'duration_ms' => 0,
                'width' => self::dim($in['width'] ?? null),
                'height' => self::dim($in['height'] ?? null),
                'status' => 'recording',
                'created_at' => intdiv($now, 1000),
            ]);
            $row = Db::one('SELECT * FROM recordings WHERE id = ?', [$id]);
        }

        $expected = (int) $row['pieces'];
        if ($piece < $expected) {
            // Qayta yuborilgan (javob yo'qolgan). Bu oxirgi bo'lak bo'lsa va yozuv hali yopilmagan bo'lsa — yopamiz.
            if ($final && $piece === $expected - 1 && $row['status'] === 'recording') {
                self::finalize($row, true);
            }
            return ['ok' => true, 'expected' => $expected, 'duplicate' => true];
        }
        if ($piece > $expected) {
            throw new HttpError(409, 'piece_gap', "Yozuv bo'laklari tartibi buzildi.", ['expected' => $expected]);
        }
        if ($row['status'] !== 'recording') {
            // Uzoq uzilishdan keyin yopilgan, lekin hali Telegram'ga ketmagan to'liq bo'lmagan yozuv — davom ettiriladi.
            $reopenable = $row['status'] === 'ready' && !(int) $row['complete'] && !(int) $row['file_deleted'] && $row['sent_at'] === null;
            if (!$reopenable) {
                throw new HttpError(409, 'segment_closed', 'Bu yozuv yopilgan. Yangisi boshlanadi.');
            }
            Db::update('recordings', ['status' => 'recording'], 'id = ?', [$row['id']]);
            $row['status'] = 'recording';
        }
        if ((int) $row['size'] + $size > self::SEGMENT_LIMIT) {
            throw new HttpError(413, 'segment_full', 'Yozuv fayli chegaraga yetdi. Yangisi boshlanadi.');
        }
        self::assertBytesQuota($a, $size, $now);

        if ($tmp !== null) {
            self::moveUpload($tmp, self::partPath($row, $piece));
        }
        $changes = [
            'pieces' => $expected + 1,
            'size' => (int) $row['size'] + $size,
            'last_ms' => $now,
            'duration_ms' => max((int) $row['duration_ms'], $durationMs),
        ];
        Db::update('recordings', $changes, 'id = ?', [$row['id']]);
        if ($final) {
            self::finalize(array_merge($row, $changes), true);
        }
        return ['ok' => true, 'expected' => $expected + 1, 'done' => $final];
    }

    private static function dim(mixed $value): ?int
    {
        $v = (int) $value;
        return $v >= 16 && $v <= 8192 ? $v : null;
    }

    /** Yangi yozuvlar soni va tezligi (bitta urinish uchun). */
    private static function assertSegmentQuota(array $a, int $now): void
    {
        $total = (int) Db::val('SELECT COUNT(*) FROM recordings WHERE attempt_id = ?', [$a['id']]);
        $recent = (int) Db::val('SELECT COUNT(*) FROM recordings WHERE attempt_id = ? AND created_at >= ?', [$a['id'], intdiv($now, 1000) - 60]);
        if ($total >= self::MAX_SEGMENTS_PER_ATTEMPT || $recent >= self::MAX_NEW_SEGMENTS_PER_MINUTE) {
            throw new HttpError(403, 'rec_quota', 'Video yozuvlar soni chegaradan oshdi.');
        }
    }

    /**
     * Hajm cheklovlari: urinish boshidan beri o'tgan vaqtga mos hajm (bitta o'quvchi serverni to'ldira olmasin)
     * va serverdagi barcha yozuvlar uchun umumiy chegara (Sozlamalar → rec_max_disk_mb).
     */
    private static function assertBytesQuota(array $a, int $adding, int $now): void
    {
        $elapsedSec = max(0, intdiv($now, 1000) - (int) $a['started_at']) + 900;
        $allowed = (int) ($elapsedSec * self::ATTEMPT_KBPS_CAP * 1000 / 8);
        $used = (int) Db::val('SELECT COALESCE(SUM(size), 0) FROM recordings WHERE attempt_id = ?', [$a['id']]);
        if ($used + $adding > $allowed) {
            throw new HttpError(403, 'rec_quota', 'Video yozuv hajmi chegaradan oshdi.');
        }
        $limitMb = Settings::int('rec_max_disk_mb');
        if ($limitMb > 0) {
            $disk = (int) Db::val('SELECT COALESCE(SUM(size), 0) FROM recordings WHERE file_deleted = 0');
            if ($disk + $adding > $limitMb * 1048576) {
                throw new HttpError(403, 'rec_disk_full', "Serverda video uchun ajratilgan joy to'ldi. Imtihon videosiz davom etadi.");
            }
        }
    }

    /** @return array{0:?string,1:int} vaqtinchalik fayl (bo'sh yakuniy bo'lak uchun null) va hajm */
    private static function checkUpload(?array $file, bool $final): array
    {
        if ($file === null || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || (int) ($file['size'] ?? 0) === 0) {
            if ($final) {
                return [null, 0]; // Yakunlash belgisi (oxirgi bo'lak allaqachon yuborilgan).
            }
            throw new HttpError(422, 'empty_piece', "Bo'lak bo'sh.");
        }
        $error = (int) $file['error'];
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new HttpError(413, 'too_large', "Bo'lak hajmi server chegarasidan katta.");
        }
        if ($error !== UPLOAD_ERR_OK || !is_file((string) ($file['tmp_name'] ?? ''))) {
            throw new HttpError(422, 'upload_failed', "Bo'lak yuklanmadi.");
        }
        $size = (int) $file['size'];
        if ($size > self::PIECE_LIMIT) {
            throw new HttpError(413, 'too_large', "Bo'lak hajmi juda katta.");
        }
        return [(string) $file['tmp_name'], $size];
    }

    private static function moveUpload(string $tmp, string $dest): void
    {
        $dir = dirname($dest);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new HttpError(500, 'store_failed', 'Yozuv papkasini yaratib bo\'lmadi.');
        }
        $ok = is_uploaded_file($tmp) ? move_uploaded_file($tmp, $dest) : (getenv('MOCK_TESTING') === '1' && copy($tmp, $dest));
        if (!$ok) {
            throw new HttpError(500, 'store_failed', "Yozuvni saqlab bo'lmadi.");
        }
    }

    // ---------------------------------------------------------------------
    // Yig'ish
    // ---------------------------------------------------------------------

    /**
     * Bo'laklarni bitta faylga yig'ib, navbatga qo'yish. Takrorlansa ham bir xil natija: fayl har safar bo'laklardan
     * qayta quriladi (vaqtinchalik fayl → rename). Bo'laklar faqat yozuv to'liq tugaganda ($complete) o'chiriladi —
     * to'liq bo'lmagan yozuv keyin davom ettirilishi mumkin.
     */
    public static function finalize(array $row, bool $complete = false): void
    {
        $dest = self::path((string) $row['file']);
        $dir = dirname($dest);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $pieces = (int) $row['pieces'];
        $parts = [];
        for ($i = 0; $i < $pieces; $i++) {
            $part = self::partPath($row, $i);
            if (!is_file($part)) {
                break;
            }
            $parts[] = $part;
        }
        $size = null;
        $expected = (int) $row['size'];
        if (is_file($dest) && (int) filesize($dest) === $expected && count($parts) < $pieces) {
            // Avvalgi yig'ish muvaffaqiyatli bo'lgan, bo'laklar o'chirilgan (baza o'zgarishi qaytarilgandan keyin).
            $size = $expected;
        } elseif ($parts !== []) {
            $out = @fopen($dest . '.tmp', 'wb');
            if ($out !== false) {
                $written = 0;
                foreach ($parts as $part) {
                    $in = @fopen($part, 'rb');
                    if ($in === false) {
                        break;
                    }
                    $written += (int) stream_copy_to_stream($in, $out);
                    fclose($in);
                }
                fclose($out);
                if ($written > 0 && @rename($dest . '.tmp', $dest)) {
                    $size = $written;
                } else {
                    @unlink($dest . '.tmp');
                }
            }
        } elseif (is_file($dest) && filesize($dest) > 0) {
            $size = (int) filesize($dest);
        }

        if ($size === null) {
            Db::update('recordings', ['status' => 'expired', 'file_deleted' => 1, 'size' => 0, 'tg_error' => "Yozuv bo'sh"], 'id = ?', [$row['id']]);
            return;
        }
        Db::update('recordings', [
            'status' => 'ready',
            'complete' => $complete ? 1 : (int) ($row['complete'] ?? 0),
            'size' => $size,
            'tg_next_at' => 0,
        ], 'id = ?', [$row['id']]);
        if ($complete) {
            self::deleteParts($row);
        }
    }

    private static function deleteParts(array $row): void
    {
        foreach (glob(self::path('a' . (int) $row['attempt_id']) . '/' . $row['seg_key'] . '.*.part') ?: [] as $part) {
            @unlink($part);
        }
    }

    /** Uzoq vaqt bo'lak kelmagan yozuvlarni yopish (sahifa yopilgan, internet uzilgan). */
    public static function closeStale(): int
    {
        $now = Util::nowMs();
        $activeCutoff = $now - self::STALE_ACTIVE_MS;
        $doneCutoff = $now - self::STALE_DONE_MS;
        $rows = Db::all(
            "SELECT r.id, r.last_ms FROM recordings r JOIN attempts a ON a.id = r.attempt_id
             WHERE r.status = 'recording'
               AND ((a.status = 'in_progress' AND r.last_ms < ?) OR (a.status <> 'in_progress' AND r.last_ms < ?))",
            [$activeCutoff, $doneCutoff]
        );
        $closed = 0;
        foreach ($rows as $stale) {
            $closed += (int) Db::tx(static function () use ($stale): bool {
                // Qulf ostida qayta tekshirish: shu orada yangi bo'lak kelgan bo'lsa — tegilmaydi.
                $row = Db::one("SELECT * FROM recordings WHERE id = ? AND status = 'recording' AND last_ms = ?" . Db::forUpdate(), [$stale['id'], $stale['last_ms']]);
                if ($row === null) {
                    return false;
                }
                self::finalize($row);
                return true;
            });
        }
        return $closed;
    }

    // ---------------------------------------------------------------------
    // Saqlash muddati va o'chirish
    // ---------------------------------------------------------------------

    /** Muddati o'tgan fayllarni o'chirish (sozlamalar: rec_keep_days, rec_speaking_keep_days). */
    public static function expire(): int
    {
        $count = 0;
        $now = time();
        // Telegram'ga yuborilgan, lekin fayli o'chmay qolgan yozma qism videolari (masalan, server to'xtab qolgan).
        foreach (Db::all("SELECT * FROM recordings WHERE file_deleted = 0 AND section <> 'S' AND status = 'sent'") as $row) {
            self::deleteFile($row, 'sent');
            $count++;
        }
        $keep = Settings::int('rec_keep_days');
        if ($keep > 0) {
            $rows = Db::all(
                "SELECT * FROM recordings WHERE file_deleted = 0 AND section <> 'S' AND status IN ('ready', 'failed') AND created_at < ?",
                [$now - $keep * 86400]
            );
            foreach ($rows as $row) {
                self::deleteFile($row, 'expired');
                $count++;
            }
        }
        $keepS = Settings::int('rec_speaking_keep_days');
        if ($keepS > 0) {
            $rows = Db::all(
                "SELECT * FROM recordings WHERE file_deleted = 0 AND section = 'S' AND status IN ('ready', 'failed', 'sent') AND created_at < ?",
                [$now - $keepS * 86400]
            );
            foreach ($rows as $row) {
                self::deleteFile($row, $row['status'] === 'sent' ? 'sent' : 'expired');
                $count++;
            }
        }
        return $count;
    }

    private static function deleteFile(array $row, string $status): void
    {
        @unlink(self::path((string) $row['file']));
        self::deleteParts($row);
        Db::update('recordings', ['file_deleted' => 1, 'status' => $status], 'id = ?', [$row['id']]);
    }

    /** Urinish(lar) o'chirilganda: barcha video fayllar (bo'laklar bilan). Baza qatorlari CASCADE bilan o'chadi. */
    public static function deleteForAttempts(array $attemptIds): void
    {
        foreach ($attemptIds as $id) {
            $dir = self::root() . '/a' . (int) $id;
            if (is_dir($dir)) {
                foreach (glob($dir . '/*') ?: [] as $file) {
                    @unlink($file);
                }
                @rmdir($dir);
            }
        }
    }

    // ---------------------------------------------------------------------
    // Telegram navbati
    // ---------------------------------------------------------------------

    private static function statusFile(): string
    {
        return self::root() . '/queue.json';
    }

    public static function queueStatus(): array
    {
        $raw = is_file(self::statusFile()) ? (string) @file_get_contents(self::statusFile()) : '';
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private static function saveStatus(array $changes): void
    {
        $data = array_merge(self::queueStatus(), $changes);
        if (!is_dir(self::root())) {
            @mkdir(self::root(), 0775, true);
        }
        @file_put_contents(self::statusFile(), json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    /**
     * Navbatni ishlatish: uzilgan yozuvlarni yopish, eskilarini o'chirish va tayyorlarini Telegram'ga yuborish.
     * Bir vaqtda faqat bitta jarayon ishlaydi (cron va admin tugmasi bir-biriga xalaqit bermaydi).
     *
     * @return array{busy?:bool, closed:int, expired:int, sent:int, failed:int, configured:bool, error?:string}
     */
    public static function process(float $budgetSec = 50.0, ?callable $sleep = null): array
    {
        $sleep ??= static fn (float $s) => usleep((int) ($s * 1_000_000));
        if (!is_dir(self::root()) && !@mkdir(self::root(), 0775, true) && !is_dir(self::root())) {
            return ['closed' => 0, 'expired' => 0, 'sent' => 0, 'failed' => 0, 'configured' => Telegram::configured(), 'error' => "storage/recordings papkasini yaratib bo'lmadi."];
        }
        $lock = @fopen(self::root() . '/.queue.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            return ['busy' => true, 'closed' => 0, 'expired' => 0, 'sent' => 0, 'failed' => 0, 'configured' => Telegram::configured()];
        }
        $started = microtime(true);
        $result = ['closed' => 0, 'expired' => 0, 'sent' => 0, 'failed' => 0, 'configured' => Telegram::configured()];
        try {
            self::saveStatus(['last_run_at' => time()]);
            $result['closed'] = self::closeStale();
            $result['expired'] = self::expire();
            if (!$result['configured']) {
                return $result;
            }
            $status = self::queueStatus();
            if ((int) ($status['paused_until'] ?? 0) > time()) {
                $result['error'] = 'Telegram cheklovi: ' . ((int) $status['paused_until'] - time()) . ' soniya kutilmoqda.';
                return $result;
            }
            $lastSend = 0.0;
            while (microtime(true) - $started < $budgetSec) {
                $row = Db::one(
                    "SELECT * FROM recordings WHERE status = 'ready' AND file_deleted = 0 AND tg_next_at <= ? ORDER BY id LIMIT 1",
                    [time()]
                );
                if ($row === null) {
                    break;
                }
                $wait = $lastSend + self::SEND_SPACING_SEC - microtime(true);
                if ($wait > 0) {
                    if (microtime(true) + $wait - $started >= $budgetSec) {
                        break;
                    }
                    $sleep($wait);
                }
                $lastSend = microtime(true);
                $outcome = self::sendOne($row);
                if ($outcome === 'sent') {
                    $result['sent']++;
                } else {
                    $result['failed']++;
                    if ($outcome === 'pause') {
                        break;
                    }
                }
            }
            return $result;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return 'sent'|'retry'|'pause' */
    private static function sendOne(array $row): string
    {
        $path = self::path((string) $row['file']);
        if (!is_file($path)) {
            Db::update('recordings', ['status' => 'expired', 'file_deleted' => 1, 'tg_error' => 'Fayl topilmadi'], 'id = ?', [$row['id']]);
            return 'retry';
        }
        $info = Db::one(
            'SELECT a.id, a.attempt_no, a.anon_code, a.meta_json, u.full_name, m.title
             FROM attempts a JOIN users u ON u.id = a.user_id JOIN mocks m ON m.id = a.mock_id WHERE a.id = ?',
            [$row['attempt_id']]
        );
        if ($info === null) {
            return 'retry';
        }
        $chat = Telegram::chatFor((string) $row['section']);
        try {
            $messageId = Telegram::sendVideoFile(
                $chat,
                $path,
                (string) $row['mime'],
                self::telegramPlayable($row),
                self::fileName($row, $info),
                self::caption($row, $info),
                (int) round((int) $row['duration_ms'] / 1000),
                $row['width'] !== null ? (int) $row['width'] : null,
                $row['height'] !== null ? (int) $row['height'] : null
            );
        } catch (TelegramError $e) {
            $tries = (int) $row['tg_tries'] + 1;
            $retryAfter = $e->retryAfter;
            $network = $e->apiCode === 0;
            $delay = $retryAfter > 0 ? $retryAfter + 1 : min(3600, 30 * (2 ** min($tries, 7)));
            Db::update('recordings', [
                'tg_tries' => $tries,
                'tg_next_at' => time() + $delay,
                'tg_error' => mb_substr($e->getMessage(), 0, 300),
                // Ulanish xatosi (Telegram bloklangan, internet yo'q) — navbatda qoladi; Telegram rad etsa — ko'p
                // urinishdan keyin "xato" bo'ladi (admin qayta yuborishi mumkin).
                'status' => !$network && $retryAfter === 0 && $tries >= self::MAX_TRIES ? 'failed' : 'ready',
            ], "id = ? AND status = 'ready'", [$row['id']]);
            self::saveStatus(['last_error' => mb_substr($e->getMessage(), 0, 300), 'last_error_at' => time()]
                + ($retryAfter > 0 ? ['paused_until' => time() + $retryAfter] : []));
            return $retryAfter > 0 || $network ? 'pause' : 'retry';
        }
        // Avval baza (keyin fayl): yuborish paytida yozuv davom ettirilgan bo'lsa (o'quvchining interneti tiklangan),
        // holat o'zgarmaydi — u to'liq tugagach yana yuboriladi.
        $marked = Db::update('recordings', [
            'status' => 'sent',
            'tg_chat' => $chat,
            'tg_message_id' => $messageId,
            'tg_error' => null,
            'sent_at' => time(),
        ], "id = ? AND status = 'ready'", [$row['id']]);
        if ($marked > 0) {
            // Yozma qism videolari faqat Telegram'da qoladi; Speaking videolari serverda ham saqlanadi.
            self::deleteParts($row);
            if ($row['section'] !== 'S') {
                @unlink($path);
                Db::update('recordings', ['file_deleted' => 1], 'id = ?', [$row['id']]);
            }
        }
        $status = self::queueStatus();
        self::saveStatus(['last_sent_at' => time(), 'sent_total' => (int) ($status['sent_total'] ?? 0) + 1]);
        return 'sent';
    }

    public static function fileName(array $row, array $info): string
    {
        $date = date('Ymd-Hi', intdiv((int) $row['started_ms'], 1000));
        $ext = self::MIMES[(string) $row['mime']] ?? 'bin';
        return sprintf('%s_%s_%s_%s.%s', $info['anon_code'], $date, $row['section'], substr((string) $row['seg_key'], 0, 6), $ext);
    }

    /** Kanal xabari matni (oddiy matn, Telegram 1024 belgigacha). */
    public static function caption(array $row, array $info): string
    {
        $names = ['L' => 'Listening', 'R' => 'Reading', 'W' => 'Writing', 'S' => 'Speaking'];
        $index = 1 + (int) Db::val(
            'SELECT COUNT(*) FROM recordings WHERE attempt_id = ? AND section = ? AND started_ms < ?',
            [$row['attempt_id'], $row['section'], $row['started_ms']]
        );
        $from = intdiv((int) $row['started_ms'], 1000);
        $to = $from + intdiv((int) $row['duration_ms'], 1000);
        $contents = ['screen+camera' => 'ekran + kamera', 'screen' => 'ekran (kamerasiz)', 'camera' => 'kamera (ekransiz)'];
        $proctor = (array) (Util::decode($info['meta_json'] ?? '{}')['proctor'] ?? []);
        $flags = [];
        if (!empty($proctor['camera_missing'])) {
            $flags[] = 'kamera ishlamagan payt bor';
        }
        if (!empty($proctor['screen_missing'])) {
            $flags[] = 'ekran ulashilmagan payt bor';
        }
        if ((int) ($proctor['screens'] ?? 1) > 1) {
            $flags[] = $proctor['screens'] . ' ta monitor';
        }
        $lines = [
            '🎥 ' . $info['title'],
            '👤 ' . $info['full_name'] . ' · kod ' . $info['anon_code'] . ' · ' . $info['attempt_no'] . '-urinish',
            '📘 ' . ($names[$row['section']] ?? $row['section']) . ' · ' . $index . '-qism · '
                . date('d.m.Y H:i', $from) . '–' . date('H:i', $to)
                . ((int) $row['complete'] ? '' : " · to'liq emas"),
            '🖥 ' . ($contents[$row['content']] ?? $row['content']) . ((int) $row['has_audio'] ? ' + ovoz' : ''),
        ];
        if ($flags !== []) {
            $lines[] = '⚠️ ' . implode(', ', $flags);
        }
        return implode("\n", $lines);
    }

    // ---------------------------------------------------------------------
    // Admin uchun
    // ---------------------------------------------------------------------

    public static function forAttempt(int $attemptId): array
    {
        $rows = Db::all(
            'SELECT id, seg_key, section, content, has_audio, mime, codec, size, pieces, started_ms, duration_ms, status, complete,
                    file_deleted, tg_chat, tg_message_id, tg_tries, tg_error, sent_at
             FROM recordings WHERE attempt_id = ? ORDER BY started_ms, id',
            [$attemptId]
        );
        foreach ($rows as &$row) {
            $row['tg_link'] = Telegram::messageLink($row['tg_chat'], $row['tg_message_id'] !== null ? (int) $row['tg_message_id'] : null);
            $row['playable'] = !(int) $row['file_deleted'] && $row['status'] !== 'recording';
            unset($row['tg_chat']);
        }
        unset($row);
        return $rows;
    }

    public static function summary(): array
    {
        $counts = [];
        foreach (Db::all('SELECT status, COUNT(*) AS n, SUM(CASE WHEN file_deleted = 0 THEN size ELSE 0 END) AS bytes FROM recordings GROUP BY status') as $r) {
            $counts[$r['status']] = ['count' => (int) $r['n'], 'bytes' => (int) $r['bytes']];
        }
        $disk = 0;
        foreach ($counts as $c) {
            $disk += $c['bytes'];
        }
        $s = Telegram::settings();
        $host = parse_url($s['api_base'], PHP_URL_HOST) ?: $s['api_base'];
        return [
            'telegram' => [
                'configured' => Telegram::configured(),
                'token' => Telegram::maskedToken(),
                'chat' => $s['chat'],
                'speaking_chat' => $s['speaking_chat'],
                'api_host' => $host,
                'relay' => $host !== 'api.telegram.org',
                'proxy' => $s['proxy'] !== '',
                'curl' => function_exists('curl_init'),
            ],
            'counts' => $counts,
            'disk_bytes' => $disk,
            'disk_limit_bytes' => Settings::int('rec_max_disk_mb') * 1048576,
            'queue' => self::queueStatus(),
            'now' => time(),
        ];
    }
}
