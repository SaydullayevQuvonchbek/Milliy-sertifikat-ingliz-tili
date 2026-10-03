<?php

declare(strict_types=1);

require_once __DIR__ . '/fixtures.php';

use App\Config;
use App\Db;
use App\Settings;
use App\Services\AttemptService as A;
use App\Services\MockService;
use App\Services\Recordings;
use App\Services\Telegram;
use App\Util;

// ---------------------------------------------------------------------
// Yordamchilar
// ---------------------------------------------------------------------

/** Haqiqiy MP4 boshiga o'xshash bayt (finfo uni video/mp4 deb taniydi). */
function rec_mp4_head(): string
{
    return "\x00\x00\x00\x18ftypisom\x00\x00\x02\x00isomiso2" . "\x00\x00\x00\x08free" . str_repeat("\x11", 64);
}

/** Kichik WAV (Speaking javobi sifatida yuklash uchun). */
function rec_wav(): string
{
    $n = 800;
    return 'RIFF' . pack('V', 36 + $n) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, 8000, 8000, 1, 8) . 'data' . pack('V', $n) . str_repeat("\x80", $n);
}

function rec_file(string $bytes): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'piece');
    file_put_contents($tmp, $bytes);
    return ['tmp_name' => $tmp, 'size' => strlen($bytes), 'error' => UPLOAD_ERR_OK, 'name' => 'p.mp4'];
}

function rec_in(array $overrides = []): array
{
    return $overrides + [
        'seg' => 'SEGaaaaaaaaaaaaaaaa1',
        'piece' => '0',
        'section' => 'L',
        'content' => 'screen+camera',
        'audio' => '0',
        'mime' => 'video/mp4;codecs=avc1.42E01E',
        'final' => '0',
        'duration_ms' => '30000',
        'width' => '1280',
        'height' => '720',
    ];
}

/** Mock + o'quvchi + boshlangan urinish. */
function rec_setup(array $settings = []): array
{
    static $n = 0;
    $n++;
    $mock = make_mock(['settings' => $settings]);
    $user = make_user('student', '+9989055' . str_pad((string) $n, 5, '0', STR_PAD_LEFT));
    $a = A::startOrResume($user, (int) $mock['id']);
    return [$a, $mock, $user];
}

/** Telegram'ni soxta uzatish bilan sozlash; chaqiruvlar $calls ga yoziladi. */
function rec_fake_telegram(array &$calls, ?callable $respond = null): void
{
    Config::set('telegram', ['bot_token' => '123456:SECRETTOKENabcd', 'chat_id' => '-1001234567890', 'speaking_chat_id' => '-1009999999999']);
    $n = 100;
    Telegram::$transport = static function (string $method, array $fields) use (&$calls, &$n, $respond): array {
        $calls[] = [$method, $fields];
        if ($respond !== null) {
            return $respond($method, $fields);
        }
        return [200, json_encode(['ok' => true, 'result' => ['message_id' => ++$n]])];
    };
}

function rec_reset_telegram(): void
{
    Telegram::$transport = null;
    Config::set('telegram', []);
}

// ---------------------------------------------------------------------
// Sozlamalar
// ---------------------------------------------------------------------

test('mock sozlamasi: video nazorat va o\'tish — standart qiymatlar, noto\'g\'ri qiymat tuzatiladi', function (): void {
    $s = MockService::mergeSettings([]);
    eq(['camera' => 'optional', 'screen' => 'optional'], $s['proctoring']);
    eq(['early_finish' => true, 'speaking_skip' => false], $s['flow']);
    $s = MockService::mergeSettings(['proctoring' => ['camera' => 'required', 'screen' => 'yo\'q', 'extra' => 1], 'flow' => ['early_finish' => 0, 'speaking_skip' => '1']]);
    eq(['camera' => 'required', 'screen' => 'optional'], $s['proctoring']);
    eq(['early_finish' => false, 'speaking_skip' => true], $s['flow']);
    // Buzilgan (massiv emas) guruh — standart.
    $s = MockService::mergeSettings(['proctoring' => 'off', 'lockdown' => 5]);
    eq('optional', $s['proctoring']['camera']);
    eq(true, $s['lockdown']['fullscreen']);
});

test('umumiy sozlama: video sifati chegaralangan', function (): void {
    eq(250, Settings::int('rec_video_kbps'));
    Settings::set('rec_video_kbps', 99999);
    eq(2000, Settings::int('rec_video_kbps'));
    Settings::set('rec_segment_min', 0);
    eq(2, Settings::int('rec_segment_min'));
});

// ---------------------------------------------------------------------
// "Vaqt tugamasdan yakunlash" va Speaking'da keyingi savol
// ---------------------------------------------------------------------

test('early_finish o\'chiq: Reading vaqtidan oldin yakunlanmaydi, vaqt tugaganda yakunlanadi', function (): void {
    $mock = make_mock(['settings' => ['sections' => ['R', 'W'], 'flow' => ['early_finish' => false]]]);
    $user = make_user();
    Util::$testNowMs = 1_800_000_000_000;
    $a = A::claim(A::startOrResume($user, (int) $mock['id']), 'client-aaaaaaaa', false);
    $a = A::startSection($a, $mock, 'R');
    throws(fn () => A::finishSection($a, $mock, 'R'), 'early_finish_disabled');
    Util::$testNowMs = (int) $a['section_deadline_ms'] - 2000; // soat farqi uchun zaxira ichida
    $a = A::finishSection($a, $mock, 'R');
    eq('W', $a['stage']);
    $state = A::stateFor($a, $mock, $user);
    eq(false, $state['mock']['flow']['early_finish']);
});

test('early_finish yoqiq (standart): bo\'limni oldinroq yakunlash mumkin', function (): void {
    $mock = make_mock(['settings' => ['sections' => ['R']]]);
    $user = make_user();
    $a = A::startSection(A::startOrResume($user, (int) $mock['id']), $mock, 'R');
    $a = A::finishSection($a, $mock, 'R');
    eq('completed', $a['status']);
});

test('speaking_skip: javob yuklangach keyingi savol darhol ochiladi; javobsiz — kutiladi; o\'chiq bo\'lsa — doim kutiladi', function (): void {
    foreach ([false, true] as $skip) {
        $mock = make_mock(['settings' => ['sections' => ['S'], 'flow' => ['speaking_skip' => $skip]]]);
        $user = make_user('student', '+99890111' . ($skip ? '0001' : '0002'));
        Util::$testNowMs = 1_800_000_000_000;
        $a = A::speakingStart(A::startOrResume($user, (int) $mock['id']), $mock);
        [$a, $q1] = A::speakingNext($a, $mock);
        eq(1, (int) $q1['no']);
        Util::$testNowMs += 5_000; // javob 30 soniyalik, 5 soniyada tugatildi
        // Javob yuklanmagan — barcha savollarni ketma-ket ochib ko'rib bo'lmaydi.
        throws(fn () => A::speakingNext($a, $mock), 'too_early');
        $a = A::speakingUpload($a, $mock, 1, rec_file(rec_wav()), 5.0);
        if ($skip) {
            [$a, $q2] = A::speakingNext($a, $mock);
            eq(2, (int) $q2['no']);
        } else {
            throws(fn () => A::speakingNext($a, $mock), 'too_early');
        }
    }
});

// ---------------------------------------------------------------------
// Kamera / ekran holati
// ---------------------------------------------------------------------

test('majburiy kamera: holat "ok" bo\'lmaguncha birinchi bo\'lim boshlanmaydi', function (): void {
    [$a, $mock] = rec_setup(['proctoring' => ['camera' => 'required', 'screen' => 'optional']]);
    throws(fn () => A::startSection($a, $mock, 'L'), 'camera_required');
    $a = A::setProctorStatus($a, $mock, ['camera' => 'denied', 'screen' => 'denied']);
    throws(fn () => A::startSection($a, $mock, 'L'), 'camera_required');
    $a = A::setProctorStatus($a, $mock, ['camera' => 'ok', 'screen' => 'denied']);
    $a = A::startSection($a, $mock, 'L');
    eq('active', $a['stage_state']);
    $meta = Util::decode($a['meta_json']);
    eq('ok', $meta['proctor']['camera']);
    // Darvozadagi oraliq holat (avval rad etib, keyin yoqqan) belgi qo'ymaydi; boshlanganda ishlamagan ekran — belgi.
    ok(empty($meta['proctor']['camera_missing']), 'darvozadagi oraliq holat belgi qo\'ymasligi kerak');
    ok(!empty($meta['proctor']['screen_missing']), 'ekran boshlanganda ishlamagan');
});

test('belgilar: kamera yoqilib ekran hali so\'ralmagan holat belgi qo\'ymaydi; bo\'lim davomidagi uzilish — belgi', function (): void {
    [$a, $mock] = rec_setup();
    $a = A::setProctorStatus($a, $mock, ['camera' => 'ok']);             // ekran hali so'ralmagan — yuborilmaydi
    $a = A::setProctorStatus($a, $mock, ['camera' => 'ok', 'screen' => 'ok']);
    $a = A::startSection($a, $mock, 'L');
    $p = Util::decode($a['meta_json'])['proctor'];
    ok(empty($p['camera_missing']) && empty($p['screen_missing']), 'yolg\'on belgi: ' . json_encode($p));
    $a = A::setProctorStatus($a, $mock, ['screen' => 'stopped']);
    ok(!empty(Util::decode($a['meta_json'])['proctor']['screen_missing']), "bo'lim davomida to'xtatildi");
    // Holat umuman yuborilmagan bo'lsa (masalan, eski brauzer keshi) — yozuv yo'q, belgi qo'yiladi.
    [$b, $mock2] = rec_setup();
    $b = A::startSection($b, $mock2, 'L');
    $p = Util::decode($b['meta_json'])['proctor'];
    ok(!empty($p['camera_missing']) && !empty($p['screen_missing']));
});

test('sahifa yangilansa (claim) kamera/ekran holati o\'chadi: qayta yoqilmasa keyingi bo\'lim belgi bilan boshlanadi', function (): void {
    [$a, $mock] = rec_setup(['sections' => ['R', 'W']]);
    $a = A::setProctorStatus($a, $mock, ['camera' => 'ok', 'screen' => 'ok']);
    $a = A::finishSection(A::startSection($a, $mock, 'R'), $mock, 'R');
    ok(empty(Util::decode($a['meta_json'])['proctor']['camera_missing']));
    // Tanaffusda F5: yangi oyna — eski qurilmalar ishlamaydi.
    $a = A::resetProctorLive($a);
    $p = Util::decode($a['meta_json'])['proctor'];
    ok(!isset($p['camera']) && !isset($p['screen']));
    $a = A::startSection($a, $mock, 'W');
    $p = Util::decode($a['meta_json'])['proctor'];
    ok(!empty($p['camera_missing']) && !empty($p['screen_missing']), 'qayta yoqilmagan — belgi: ' . json_encode($p));
});

test('majburiy kamera faqat birinchi bo\'limda tekshiriladi (tanaffusdan keyingi bo\'lim to\'xtab qolmaydi)', function (): void {
    [$a, $mock] = rec_setup(['sections' => ['R', 'W'], 'proctoring' => ['camera' => 'required', 'screen' => 'off']]);
    $a = A::setProctorStatus($a, $mock, ['camera' => 'ok']);
    $a = A::finishSection(A::startSection($a, $mock, 'R'), $mock, 'R');
    $a = A::setProctorStatus($a, $mock, ['camera' => 'lost']);
    $a = A::startSection($a, $mock, 'W');
    eq('active', $a['stage_state']);
    ok(!empty(Util::decode($a['meta_json'])['proctor']['camera_missing']));
});

test('Speaking: faqat kamera tekshiriladi (ekran majburiy bo\'lsa ham Speaking to\'xtab qolmaydi)', function (): void {
    [$s, $mockS] = rec_setup(['sections' => ['S'], 'proctoring' => ['camera' => 'optional', 'screen' => 'required']]);
    $s = A::speakingStart($s, $mockS);
    eq('active', $s['stage_state']);

    [$a, $mock] = rec_setup(['sections' => ['S'], 'proctoring' => ['camera' => 'required', 'screen' => 'off']]);
    throws(fn () => A::speakingStart($a, $mock), 'camera_required');
    $a = A::setProctorStatus($a, $mock, ['camera' => 'ok', 'screen' => 'ok']);
    eq('off', Util::decode($a['meta_json'])['proctor']['screen'], "o'chiq rejimda holat 'off'");
    $a = A::speakingStart($a, $mock);
    eq('active', $a['stage_state']);

    [$b, $mock2] = rec_setup(['sections' => ['R']]);
    $b = A::startSection($b, $mock2, 'R');
    eq('active', $b['stage_state']);
});

test('holat: monitorlar soni, noma\'lum qiymat e\'tiborsiz, tugagan urinish o\'zgarmaydi', function (): void {
    [$a, $mock] = rec_setup();
    $a = A::setProctorStatus($a, $mock, ['camera' => 'hack', 'screen' => 'ok', 'screens' => 3]);
    $p = Util::decode($a['meta_json'])['proctor'];
    ok(!isset($p['camera']));
    eq('ok', $p['screen']);
    eq(3, $p['screens']);
    Db::exec("UPDATE attempts SET status = 'completed' WHERE id = ?", [$a['id']]);
    $done = A::setProctorStatus(A::lock((int) $a['id']), $mock, ['camera' => 'denied']);
    ok(!isset(Util::decode($done['meta_json'])['proctor']['camera']));
});

test('ekran ulashishni to\'xtatish: majburiy bo\'lsa qoidabuzarlik, ixtiyoriyda faqat jurnal', function (): void {
    foreach (['required' => 1, 'optional' => 0] as $mode => $expected) {
        [$a, $mock] = rec_setup(['proctoring' => ['camera' => 'optional', 'screen' => $mode]]);
        Db::exec('DELETE FROM attempt_events');
        if ($mode === 'required') {
            $a = A::setProctorStatus($a, $mock, ['screen' => 'ok']);
        }
        $a = A::startSection($a, $mock, 'L');
        $a = A::recordEvents($a, [['type' => 'screen_stopped', 'detail' => 'x'], ['type' => 'camera_ok', 'detail' => 'cam']], $mock);
        eq($expected, (int) $a['violations'], $mode);
        eq(2, (int) Db::val('SELECT COUNT(*) FROM attempt_events WHERE attempt_id = ?', [$a['id']]));
        Db::exec('DELETE FROM attempts');
        Db::exec('DELETE FROM users');
    }
});

// ---------------------------------------------------------------------
// Bo'laklarni qabul qilish
// ---------------------------------------------------------------------

test('bo\'laklar: tartib bilan qabul qilinadi, takror — tasdiq, oraliq tushib qolsa — xato, oxirgisi faylni yig\'adi', function (): void {
    [$a, $mock] = rec_setup();
    $p0 = rec_mp4_head();
    $p1 = str_repeat('B', 1000);
    $p2 = str_repeat('C', 500);
    $r = Recordings::acceptPiece($a, $mock, rec_in(), rec_file($p0));
    eq(1, $r['expected']);
    $dup = Recordings::acceptPiece($a, $mock, rec_in(), rec_file($p0));
    ok(!empty($dup['duplicate']));
    throws(fn () => Recordings::acceptPiece($a, $mock, rec_in(['piece' => '2']), rec_file($p2)), 'piece_gap');
    Recordings::acceptPiece($a, $mock, rec_in(['piece' => '1', 'duration_ms' => '60000']), rec_file($p1));
    $done = Recordings::acceptPiece($a, $mock, rec_in(['piece' => '2', 'final' => '1', 'duration_ms' => '75000']), rec_file($p2));
    ok($done['done']);

    $row = Db::one('SELECT * FROM recordings');
    eq('ready', $row['status']);
    eq(1, (int) $row['complete']);
    eq('video/mp4', $row['mime']);
    eq('avc1', $row['codec']);
    eq(75000, (int) $row['duration_ms']);
    eq(1280, (int) $row['width']);
    eq($p0 . $p1 . $p2, (string) file_get_contents(Recordings::path($row['file'])));
    eq([], glob(dirname(Recordings::path($row['file'])) . '/*.part') ?: [], "bo'lak fayllari o'chirilishi kerak");
    // Yopilgan yozuvga yangi bo'lak — yangi fayl boshlash kerak.
    throws(fn () => Recordings::acceptPiece($a, $mock, rec_in(['piece' => '3']), rec_file('D')), 'segment_closed');
});

test('bo\'laklar: yangi yozuv boshlansa, jim turgan eski yozuv yopiladi, bo\'lak kelayotgani — yo\'q', function (): void {
    [$a, $mock] = rec_setup();
    Util::$testNowMs = 1_800_000_000_000;
    Recordings::acceptPiece($a, $mock, rec_in(['seg' => 'OLDaaaaaaaaaaaaaaaa1']), rec_file(rec_mp4_head()));
    // Ikkinchi oyna (masalan, eski oynaning navbati hali yuborilmoqda) — eski yozuv faol, yopilmaydi.
    Util::$testNowMs += 10_000;
    Recordings::acceptPiece($a, $mock, rec_in(['seg' => 'TWOaaaaaaaaaaaaaaaa1', 'section' => 'S']), rec_file(rec_mp4_head()));
    eq('recording', Db::val("SELECT status FROM recordings WHERE seg_key = 'OLDaaaaaaaaaaaaaaaa1'"));
    // Sahifa yangilangan: eski yozuv 45 soniyadan beri jim.
    Util::$testNowMs += Recordings::IDLE_CLOSE_MS + 1000;
    Recordings::acceptPiece($a, $mock, rec_in(['seg' => 'NEWaaaaaaaaaaaaaaaa1', 'section' => 'R']), rec_file(rec_mp4_head()));
    $old = Db::one("SELECT * FROM recordings WHERE seg_key = 'OLDaaaaaaaaaaaaaaaa1'");
    eq('ready', $old['status']);
    eq(0, (int) $old['complete']);
    eq('recording', Db::val("SELECT status FROM recordings WHERE seg_key = 'NEWaaaaaaaaaaaaaaaa1'"));
});

test('bo\'laklar: uzilib yopilgan (hali yuborilmagan) yozuv internet tiklanganda davom ettiriladi', function (): void {
    [$a, $mock] = rec_setup();
    Util::$testNowMs = 1_800_000_000_000;
    $p0 = rec_mp4_head();
    Recordings::acceptPiece($a, $mock, rec_in(), rec_file($p0));
    Util::$testNowMs += Recordings::STALE_ACTIVE_MS + 1000;
    eq(1, Recordings::closeStale());
    eq('ready', Db::val('SELECT status FROM recordings'));
    Recordings::acceptPiece($a, $mock, rec_in(['piece' => '1']), rec_file('second'));
    eq('recording', Db::val('SELECT status FROM recordings'), 'qayta ochildi');
    Recordings::acceptPiece($a, $mock, rec_in(['piece' => '2', 'final' => '1']), rec_file('third'));
    $row = Db::one('SELECT * FROM recordings');
    eq('ready', $row['status']);
    eq(1, (int) $row['complete']);
    eq($p0 . 'second' . 'third', (string) file_get_contents(Recordings::path($row['file'])));
    // Yuborilgandan keyin endi davom ettirilmaydi.
    Db::exec("UPDATE recordings SET status = 'sent', sent_at = 1, complete = 0");
    throws(fn () => Recordings::acceptPiece($a, $mock, rec_in(['piece' => '3']), rec_file('late')), 'segment_closed');
});

test('yangi sahifa eski (tugallanmagan) yozuvni yopadi; keyin bo\'lak kelsa — davom etadi', function (): void {
    [$a, $mock] = rec_setup();
    Recordings::acceptPiece($a, $mock, rec_in(['seg' => 'DEADaaaaaaaaaaaaaaa1']), rec_file(rec_mp4_head()));
    ok(!Recordings::closeSegment($a, '../x'), "noto'g'ri kalit");
    ok(!Recordings::closeSegment($a, 'NONEaaaaaaaaaaaaaaa1'), "yo'q kalit");
    ok(Recordings::closeSegment($a, 'DEADaaaaaaaaaaaaaaa1'));
    $row = Db::one('SELECT * FROM recordings');
    eq('ready', $row['status']);
    eq(0, (int) $row['complete']);
    ok(!Recordings::closeSegment($a, 'DEADaaaaaaaaaaaaaaa1'), 'ikkinchi marta — o\'zgarmaydi');
    // Boshqa urinishning kaliti bilan yopib bo'lmaydi.
    [$b] = rec_setup();
    Recordings::acceptPiece($b, $mock, rec_in(['seg' => 'OTHRaaaaaaaaaaaaaaa1']), rec_file(rec_mp4_head()));
    ok(!Recordings::closeSegment($a, 'OTHRaaaaaaaaaaaaaaa1'));
    eq('recording', Db::val("SELECT status FROM recordings WHERE seg_key = 'OTHRaaaaaaaaaaaaaaa1'"));
});

test('bo\'laklar: javobi yo\'qolgan oxirgi bo\'lak qayta yuborilsa, yozuv yopiladi', function (): void {
    [$a, $mock] = rec_setup();
    Recordings::acceptPiece($a, $mock, rec_in(), rec_file(rec_mp4_head()));
    Recordings::acceptPiece($a, $mock, rec_in(['piece' => '1']), rec_file('tail'));
    $r = Recordings::acceptPiece($a, $mock, rec_in(['piece' => '1', 'final' => '1']), rec_file('tail'));
    ok(!empty($r['duplicate']));
    $row = Db::one('SELECT * FROM recordings');
    eq('ready', $row['status']);
    eq(1, (int) $row['complete']);
});

test('yig\'ish takrorlansa ham bir xil (baza o\'zgarishi qaytarilgandan keyin) — "bo\'sh" deb o\'chirilmaydi', function (): void {
    [$a, $mock] = rec_setup();
    $p0 = rec_mp4_head();
    Recordings::acceptPiece($a, $mock, rec_in(), rec_file($p0));
    $before = Db::one('SELECT * FROM recordings');
    Recordings::finalize($before, true);           // fayl yig'ildi, bo'laklar o'chdi
    Db::exec("UPDATE recordings SET status = 'recording', complete = 0"); // tranzaksiya qaytarildi
    Recordings::finalize(Db::one('SELECT * FROM recordings'), true);
    $row = Db::one('SELECT * FROM recordings');
    eq('ready', $row['status']);
    eq(strlen($p0), (int) $row['size']);
    eq($p0, (string) file_get_contents(Recordings::path($row['file'])));
});

test('bo\'laklar: noto\'g\'ri tur, bo\'lim, identifikator, o\'chiq nazorat va chegara', function (): void {
    [$a, $mock] = rec_setup(['sections' => ['L', 'R', 'W']]);
    throws(fn () => Recordings::acceptPiece($a, $mock, rec_in(), rec_file('<?php echo 1; ?>')), 'bad_type');
    throws(fn () => Recordings::acceptPiece($a, $mock, rec_in(['mime' => 'image/png']), rec_file(rec_mp4_head())), 'bad_type');
    throws(fn () => Recordings::acceptPiece($a, $mock, rec_in(['section' => 'S']), rec_file(rec_mp4_head())), 'bad_section');
    throws(fn () => Recordings::acceptPiece($a, $mock, rec_in(['seg' => '../../etc']), rec_file(rec_mp4_head())), 'bad_segment');
    throws(fn () => Recordings::acceptPiece($a, $mock, rec_in(['content' => 'desktop']), rec_file(rec_mp4_head())), 'bad_type');
    throws(fn () => Recordings::acceptPiece($a, $mock, rec_in(), null), 'empty_piece');
    eq(0, (int) Db::val('SELECT COUNT(*) FROM recordings'));

    Recordings::acceptPiece($a, $mock, rec_in(), rec_file(rec_mp4_head()));
    Db::exec('UPDATE recordings SET size = ?', [Recordings::SEGMENT_LIMIT - 10]);
    throws(fn () => Recordings::acceptPiece($a, $mock, rec_in(['piece' => '1']), rec_file(str_repeat('x', 100))), 'segment_full');

    [$b, $off] = rec_setup(['proctoring' => ['camera' => 'off', 'screen' => 'off']]);
    throws(fn () => Recordings::acceptPiece($b, $off, rec_in(), rec_file(rec_mp4_head())), 'rec_disabled');
    // Video bo'lmagan ixtiyoriy baytlar (application/octet-stream) birinchi bo'lak sifatida qabul qilinmaydi.
    throws(fn () => Recordings::acceptPiece($b, $mock, rec_in(['seg' => 'BINaaaaaaaaaaaaaaaa1']), rec_file(random_bytes(4000))), 'bad_type');
});

test('chegaralar: urinish hajmi vaqtga mos, yozuvlar soni, serverdagi umumiy joy', function (): void {
    [$a, $mock] = rec_setup();
    Util::$testNowMs = ((int) $a['started_at']) * 1000; // urinish endi boshlandi: ~15 daqiqalik hajmgacha ruxsat
    Recordings::acceptPiece($a, $mock, rec_in(), rec_file(rec_mp4_head()));
    // Shu urinishning boshqa (oldingi) yozuvlari allaqachon katta hajmda.
    Db::insert('recordings', [
        'attempt_id' => $a['id'], 'seg_key' => 'BIGaaaaaaaaaaaaaaaa1', 'section' => 'L', 'content' => 'screen', 'mime' => 'video/mp4',
        'file' => 'a' . $a['id'] . '/BIG.mp4', 'size' => 300 * 1048576, 'started_ms' => 0, 'last_ms' => 0, 'status' => 'ready', 'created_at' => 0,
    ]);
    throws(fn () => Recordings::acceptPiece($a, $mock, rec_in(['piece' => '1']), rec_file('x')), 'rec_quota');
    Db::exec('DELETE FROM recordings');

    [$b, $mock2] = rec_setup();
    Util::$testNowMs = ((int) $b['started_at']) * 1000 + 3600_000;
    Settings::set('rec_max_disk_mb', 100);
    Recordings::acceptPiece($b, $mock2, rec_in(['seg' => 'DISKaaaaaaaaaaaaaaa1']), rec_file(rec_mp4_head()));
    // Telegram'ni kutayotgan 60 MB bor: yangi fayl boshlanmaydi (49 MB zaxira), boshlangani esa tugatiladi.
    $backlog = Db::insert('recordings', [
        'attempt_id' => $b['id'], 'seg_key' => 'WAITaaaaaaaaaaaaaaa1', 'section' => 'L', 'content' => 'screen', 'mime' => 'video/mp4',
        'file' => 'a' . $b['id'] . '/WAIT.mp4', 'size' => 60 * 1048576, 'started_ms' => 0, 'last_ms' => 0, 'status' => 'ready', 'created_at' => 0,
    ]);
    throws(fn () => Recordings::acceptPiece($b, $mock2, rec_in(['seg' => 'DISKbbbbbbbbbbbbbbb1']), rec_file(rec_mp4_head())), 'rec_disk_full');
    Recordings::acceptPiece($b, $mock2, rec_in(['seg' => 'DISKaaaaaaaaaaaaaaa1', 'piece' => '1']), rec_file('more'));
    // Telegram'ga yuborilgan Speaking videolari chegaraga kirmaydi (ularni rec_speaking_keep_days boshqaradi).
    Db::exec("UPDATE recordings SET section = 'S', status = 'sent', sent_at = 1 WHERE id = ?", [$backlog]);
    Recordings::acceptPiece($b, $mock2, rec_in(['seg' => 'DISKbbbbbbbbbbbbbbb1']), rec_file(rec_mp4_head()));
    Settings::set('rec_max_disk_mb', 0);

    [$c, $mock3] = rec_setup();
    Util::$testNowMs = ((int) $c['started_at']) * 1000 + 3600_000;
    for ($i = 0; $i < Recordings::MAX_NEW_SEGMENTS_PER_MINUTE; $i++) {
        Recordings::acceptPiece($c, $mock3, rec_in(['seg' => 'RATE' . str_pad((string) $i, 16, 'a')]), rec_file(rec_mp4_head()));
    }
    throws(fn () => Recordings::acceptPiece($c, $mock3, rec_in(['seg' => 'RATEzzzzzzzzzzzzzzzz']), rec_file(rec_mp4_head())), 'rec_quota');
});

test('bo\'laklar: imtihon tugagach bir necha soat qabul qilinadi (internet kech tiklansa), keyin yo\'q', function (): void {
    [$a, $mock] = rec_setup();
    Db::exec("UPDATE attempts SET status = 'completed', finished_at = ? WHERE id = ?", [time() - 60, $a['id']]);
    $a = A::lock((int) $a['id']);
    Recordings::acceptPiece($a, $mock, rec_in(['final' => '1']), rec_file(rec_mp4_head()));
    eq('ready', Db::val('SELECT status FROM recordings'));
    Db::exec('UPDATE attempts SET finished_at = ? WHERE id = ?', [time() - Recordings::AFTER_FINISH_GRACE_SEC - 5, $a['id']]);
    $a = A::lock((int) $a['id']);
    throws(fn () => Recordings::acceptPiece($a, $mock, rec_in(['seg' => 'LATEaaaaaaaaaaaaaaa1']), rec_file(rec_mp4_head())), 'rec_closed');
});

test('uzilib qolgan yozuv: imtihon davom etsa 2 soat, tugagan bo\'lsa 30 daqiqadan keyin yig\'iladi', function (): void {
    [$a, $mock] = rec_setup();
    Util::$testNowMs = 1_800_000_000_000;
    Recordings::acceptPiece($a, $mock, rec_in(), rec_file(rec_mp4_head()));
    Util::$testNowMs += Recordings::STALE_DONE_MS + 1000;
    eq(0, Recordings::closeStale(), 'imtihon davom etmoqda — brauzer internetni kutyapti');
    Db::exec("UPDATE attempts SET status = 'completed', finished_at = ? WHERE id = ?", [time(), $a['id']]);
    eq(1, Recordings::closeStale());
    $row = Db::one('SELECT * FROM recordings');
    eq('ready', $row['status']);
    eq(0, (int) $row['complete']);
    ok(is_file(Recordings::path($row['file'])));

    [$b, $mock2] = rec_setup();
    Recordings::acceptPiece($b, $mock2, rec_in(['seg' => 'ACTVaaaaaaaaaaaaaaa1']), rec_file(rec_mp4_head()));
    Util::$testNowMs += Recordings::STALE_ACTIVE_MS + 1000;
    eq(1, Recordings::closeStale());
});

// ---------------------------------------------------------------------
// Telegram navbati
// ---------------------------------------------------------------------

/** Tayyor (ready) yozuv yaratish. */
function rec_ready(array $a, array $mock, string $seg, string $section, string $mime = 'video/mp4;codecs=avc1.42E01E'): array
{
    Recordings::acceptPiece($a, $mock, rec_in(['seg' => $seg, 'section' => $section, 'final' => '1', 'mime' => $mime]), rec_file(rec_mp4_head()));
    return Db::one('SELECT * FROM recordings WHERE seg_key = ?', [$seg]);
}

test('navbat: Telegram\'ga yuboradi; yozma qism serverdan o\'chadi, Speaking qoladi; matnda ism va bo\'lim', function (): void {
    $calls = [];
    rec_fake_telegram($calls);
    try {
        [$a, $mock] = rec_setup();
        $l = rec_ready($a, $mock, 'Laaaaaaaaaaaaaaaaaa1', 'L');
        $s = rec_ready($a, $mock, 'Saaaaaaaaaaaaaaaaaa1', 'S', 'video/webm;codecs=vp9,opus');
        $slept = [];
        $result = Recordings::process(30, function (float $s) use (&$slept): void {
            $slept[] = $s;
        });
        eq(2, $result['sent']);
        eq(0, $result['failed']);
        eq('sendVideo', $calls[0][0], 'MP4 (H.264) — video');
        eq('-1001234567890', $calls[0][1]['chat_id']);
        ok(str_contains($calls[0][1]['caption'], 'Test Foydalanuvchi'), 'ismi');
        ok(str_contains($calls[0][1]['caption'], 'Listening'), "bo'lim");
        eq('true', $calls[0][1]['supports_streaming']);
        eq('sendDocument', $calls[1][0], 'WebM — fayl');
        eq('-1009999999999', $calls[1][1]['chat_id'], 'Speaking — alohida kanal');
        ok(count($slept) === 1 && $slept[0] > 2.5, 'yuborishlar orasida kutish (daqiqasiga 20 tagacha)');

        $l = Db::one('SELECT * FROM recordings WHERE id = ?', [$l['id']]);
        eq('sent', $l['status']);
        eq(1, (int) $l['file_deleted']);
        ok(!is_file(Recordings::path($l['file'])));
        $s = Db::one('SELECT * FROM recordings WHERE id = ?', [$s['id']]);
        eq('sent', $s['status']);
        eq(0, (int) $s['file_deleted']);
        ok(is_file(Recordings::path($s['file'])), 'Speaking videosi serverda qoladi');
        eq('https://t.me/c/9999999999/' . $s['tg_message_id'], Telegram::messageLink($s['tg_chat'], (int) $s['tg_message_id']));
        ok(!str_contains(json_encode(Recordings::summary()), 'SECRETTOKEN'), "token admin sahifasiga chiqmasligi kerak");
    } finally {
        rec_reset_telegram();
    }
});

test('navbat: Telegram\'ga ulanib bo\'lmasa — navbatda qoladi; 429 — kutadi; rad etsa — ko\'p urinishdan keyin xato', function (): void {
    $calls = [];
    $mode = 'network';
    rec_fake_telegram($calls, static function () use (&$mode): array {
        if ($mode === 'network') {
            throw new App\Services\TelegramError(0, "Telegram'ga ulanib bo'lmadi: timeout.");
        }
        if ($mode === '429') {
            return [429, json_encode(['ok' => false, 'error_code' => 429, 'description' => 'Too Many Requests', 'parameters' => ['retry_after' => 17]])];
        }
        return [400, json_encode(['ok' => false, 'error_code' => 400, 'description' => 'Bad Request: chat not found'])];
    });
    try {
        [$a, $mock] = rec_setup();
        $row = rec_ready($a, $mock, 'Naaaaaaaaaaaaaaaaaa1', 'R');
        $r = Recordings::process(30, static fn () => null);
        eq(0, $r['sent']);
        $row = Db::one('SELECT * FROM recordings WHERE id = ?', [$row['id']]);
        eq('ready', $row['status']);
        ok((int) $row['tg_next_at'] > time());
        ok(str_contains((string) $row['tg_error'], 'ulanib'));

        $mode = '429';
        Db::exec('UPDATE recordings SET tg_next_at = 0');
        Recordings::process(30, static fn () => null);
        $status = Recordings::queueStatus();
        ok((int) $status['paused_until'] >= time() + 16, 'retry_after hisobga olinadi');
        $r = Recordings::process(30, static fn () => null);
        ok(str_contains((string) ($r['error'] ?? ''), 'kutilmoqda'));

        $mode = 'reject';
        @unlink(Recordings::root() . '/queue.json');
        Db::exec('UPDATE recordings SET tg_next_at = 0, tg_tries = ?', [Recordings::MAX_TRIES - 1]);
        Recordings::process(30, static fn () => null);
        $row = Db::one('SELECT * FROM recordings WHERE id = ?', [$row['id']]);
        eq('failed', $row['status']);
        ok(str_contains((string) $row['tg_error'], 'chat not found'));
        ok(is_file(Recordings::path($row['file'])), 'yuborilmagan fayl o\'chmaydi');
    } finally {
        rec_reset_telegram();
    }
});

test('navbat: yuborish paytida yozuv davom ettirilsa — "yuborildi" deb belgilanmaydi va fayli o\'chmaydi', function (): void {
    $calls = [];
    rec_fake_telegram($calls, static function (): array {
        // O'quvchining interneti tiklanib, yozuv aynan shu payt qayta ochildi.
        Db::exec("UPDATE recordings SET status = 'recording'");
        return [200, json_encode(['ok' => true, 'result' => ['message_id' => 9]])];
    });
    try {
        [$a, $mock] = rec_setup();
        $row = rec_ready($a, $mock, 'RACEaaaaaaaaaaaaaaa1', 'L');
        Recordings::process(5, static fn () => null);
        $row = Db::one('SELECT * FROM recordings WHERE id = ?', [$row['id']]);
        eq('recording', $row['status']);
        eq(0, (int) $row['file_deleted']);
        ok(is_file(Recordings::path($row['file'])));
    } finally {
        rec_reset_telegram();
    }
});

test('jadval topilmasa (eski zaxira nusxadan tiklangan) — belgi fayli o\'chadi, keyingi so\'rov jadvallarni yaratadi', function (): void {
    $flag = Config::storagePath('schema.v' . App\Installer::SCHEMA_VERSION);
    @mkdir(dirname($flag), 0775, true);
    file_put_contents($flag, 'x');
    Db::exec('DROP TABLE recordings');
    try {
        Db::val('SELECT COUNT(*) FROM recordings');
        ok(false, 'xato kutilgan edi');
    } catch (PDOException $e) {
        ok(App\Installer::handleMissingTable($e));
    }
    ok(!is_file($flag));
    App\Installer::ensureSchema();
    eq(0, (int) Db::val('SELECT COUNT(*) FROM recordings'));
    ok(is_file($flag));
    ok(!App\Installer::handleMissingTable(new RuntimeException('boshqa xato')));
});

test('navbat: Telegram sozlanmagan — hech narsa yuborilmaydi, muddati o\'tgan yuborilmaganlar o\'chiriladi', function (): void {
    rec_reset_telegram();
    [$a, $mock] = rec_setup();
    $w = rec_ready($a, $mock, 'Waaaaaaaaaaaaaaaaaa1', 'W');
    $s = rec_ready($a, $mock, 'Saaaaaaaaaaaaaaaaaa2', 'S');
    $r = Recordings::process(5, static fn () => null);
    eq(false, $r['configured']);
    eq(0, $r['sent']);
    Db::exec('UPDATE recordings SET created_at = ?', [time() - 31 * 86400]);
    eq(1, Recordings::expire(), "faqat yozma qism (Speaking — doim saqlanadi)");
    eq('expired', Db::val('SELECT status FROM recordings WHERE id = ?', [$w['id']]));
    ok(!is_file(Recordings::path($w['file'])));
    ok(is_file(Recordings::path($s['file'])));
    Settings::set('rec_speaking_keep_days', 30);
    eq(1, Recordings::expire());
    ok(!is_file(Recordings::path($s['file'])));
});

test('urinish o\'chirilganda video fayllari ham o\'chadi', function (): void {
    [$a, $mock] = rec_setup();
    $row = rec_ready($a, $mock, 'Daaaaaaaaaaaaaaaaaa1', 'L');
    Recordings::acceptPiece($a, $mock, rec_in(['seg' => 'Paaaaaaaaaaaaaaaaaa1']), rec_file(rec_mp4_head()));
    ok(is_file(Recordings::path($row['file'])));
    Recordings::deleteForAttempts([(int) $a['id']]);
    ok(!is_dir(Recordings::root() . '/a' . (int) $a['id']));
});

test('Telegram: token yashiriladi, havola faqat kanal uchun', function (): void {
    Config::set('telegram', ['bot_token' => '7000000001:AAAbbbCCCdddEEE', 'chat_id' => '@markaz_kanal']);
    try {
        eq('7000000001:…dEEE', Telegram::maskedToken());
        eq('https://t.me/markaz_kanal/5', Telegram::messageLink('@markaz_kanal', 5));
        eq(null, Telegram::messageLink('12345', 5));
        eq('@markaz_kanal', Telegram::chatFor('S'), 'Speaking kanali ko\'rsatilmasa — asosiy kanal');
    } finally {
        rec_reset_telegram();
    }
});
