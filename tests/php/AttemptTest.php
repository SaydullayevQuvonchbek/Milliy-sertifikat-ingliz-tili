<?php

declare(strict_types=1);

require_once __DIR__ . '/fixtures.php';

use App\Db;
use App\Services\AttemptService as A;
use App\Services\MockService;
use App\Util;

/** Urinishni boshidan oxirigacha o'tkazish (Speaking'siz). */
function complete_written(array $attempt, array $mock): array
{
    $cid = 'client-aaaaaaaa';
    $attempt = A::claim($attempt, $cid, false);
    foreach (array_intersect(['L', 'R', 'W'], A::sequence($attempt)) as $section) {
        $attempt = A::startSection($attempt, $mock, $section);
        if ($section === 'L') {
            // Listening audio tugaguncha yakunlab bo'lmaydi — soatni oxiriga suramiz.
            Util::$testNowMs = (int) $attempt['section_deadline_ms'] - 1000;
        }
        $attempt = A::finishSection($attempt, $mock, $section);
    }
    return $attempt;
}

test('boshlash: ikkinchi bosish yangi urinish ochmaydi (davom ettiradi)', function (): void {
    $mock = make_mock();
    $user = make_user();
    $a1 = A::startOrResume($user, (int) $mock['id']);
    $a2 = A::startOrResume($user, (int) $mock['id']);
    eq((int) $a1['id'], (int) $a2['id']);
    eq(1, (int) $a1['attempt_no']);
    eq('L', $a1['stage']);
    eq('pending', $a1['stage_state']);
    eq('L,R,W,S', $a1['sections']);
});

test('bitta o\'quvchi bitta mockni 2 martadan ortiq ishlay olmaydi', function (): void {
    $mock = make_mock(['settings' => ['sections' => ['L', 'R', 'W']]]);
    $user = make_user();
    Util::$testNowMs = 1_800_000_000_000;

    $a = complete_written(A::startOrResume($user, (int) $mock['id']), $mock);
    eq('completed', $a['status']);

    Util::$testNowMs += 10_000_000;
    $b = A::startOrResume($user, (int) $mock['id']);
    eq(2, (int) $b['attempt_no']);
    $b = complete_written($b, $mock);
    eq('completed', $b['status']);

    throws(fn () => A::startOrResume($user, (int) $mock['id']), 'attempt_limit');
    eq(2, (int) Db::val('SELECT COUNT(*) FROM attempts'));
});

test('boshqa o\'quvchining urinishlari chegaraga ta\'sir qilmaydi', function (): void {
    $mock = make_mock(['max_attempts' => 1, 'settings' => ['sections' => ['R']]]);
    $u1 = make_user('student', '+998901110001');
    $u2 = make_user('student', '+998901110002');
    complete_written(A::startOrResume($u1, (int) $mock['id']), $mock);
    $b = A::startOrResume($u2, (int) $mock['id']);
    eq(1, (int) $b['attempt_no']);
});

test('muzlatilgan mockda yangi urinish boshlanmaydi, boshlangani davom etadi', function (): void {
    $mock = make_mock();
    $u1 = make_user('student', '+998901110001');
    $u2 = make_user('student', '+998901110002');
    $started = A::startOrResume($u1, (int) $mock['id']);
    MockService::setStatus((int) $mock['id'], 'frozen', 1);
    $mock = MockService::find((int) $mock['id']);
    throws(fn () => A::startOrResume($u2, (int) $mock['id']), 'mock_frozen');
    $resumed = A::startOrResume($u1, (int) $mock['id']);
    eq((int) $started['id'], (int) $resumed['id']);
});

test('qoralama va arxivdagi mock ochilmaydi', function (): void {
    $draft = make_mock([], 'draft');
    $user = make_user();
    throws(fn () => A::startOrResume($user, (int) $draft['id']), 'mock_unavailable');
});

test('bir vaqtda faqat bitta yozma imtihon', function (): void {
    $m1 = make_mock(['title' => 'Birinchi']);
    $m2 = make_mock(['title' => 'Ikkinchi']);
    $user = make_user();
    A::startOrResume($user, (int) $m1['id']);
    throws(fn () => A::startOrResume($user, (int) $m2['id']), 'other_active');
});

test('muddat serverda: vaqt o\'tsa bo\'lim o\'zi yopiladi', function (): void {
    $mock = make_mock(['settings' => ['sections' => ['R', 'W'], 'times' => ['reading' => 600]]]);
    $user = make_user();
    Util::$testNowMs = 1_800_000_000_000;
    $a = A::claim(A::startOrResume($user, (int) $mock['id']), 'client-aaaaaaaa', false);
    $a = A::startSection($a, $mock, 'R');
    eq(1_800_000_000_000 + 600_000, (int) $a['section_deadline_ms']);

    // Muddat + zaxira vaqt ichida hali ochiq.
    Util::$testNowMs = (int) $a['section_deadline_ms'] + 30_000;
    eq('R', A::refresh($a, $mock)['stage']);

    Util::$testNowMs = (int) $a['section_deadline_ms'] + A::GRACE_MS + 1;
    $a = A::refresh($a, $mock);
    eq('W', $a['stage']);
    eq('pending', $a['stage_state']);
    $meta = Util::decode($a['meta_json']);
    eq('timeout', $meta['sections']['R']['reason']);
});

test('tanaffus tugagach keyingi bo\'lim avtomatik boshlanadi', function (): void {
    $mock = make_mock(['settings' => ['sections' => ['R', 'W'], 'break_sec' => 60]]);
    $user = make_user();
    Util::$testNowMs = 1_800_000_000_000;
    $a = A::startSection(A::startOrResume($user, (int) $mock['id']), $mock, 'R');
    $a = A::finishSection($a, $mock, 'R');
    eq('W', $a['stage']);
    $finished = Util::$testNowMs;
    Util::$testNowMs = $finished + 60_000 + A::GRACE_MS + 5;
    $a = A::refresh($a, $mock);
    eq('active', $a['stage_state']);
    eq($finished + 60_000, (int) $a['section_started_ms']);
});

test('saqlash: eski seq rad etiladi, begona savol raqamlari tashlanadi', function (): void {
    $mock = make_mock();
    $user = make_user();
    $a = A::startSection(A::startOrResume($user, (int) $mock['id']), $mock, 'L');
    Util::$testNowMs = (int) $a['section_started_ms'] + 5000;

    [$a, $ok] = A::save($a, $mock, ['section' => 'L', 'seq' => 2, 'answers' => ['1' => 'B', '99' => 'X', '2' => ' nine ']]);
    ok($ok);
    eq(['1' => 'B', '2' => 'nine'], Util::decode($a['answers_json'])['L']);

    [$a, $ok] = A::save($a, $mock, ['section' => 'L', 'seq' => 1, 'answers' => ['1' => 'C']]);
    ok(!$ok, 'eski seq qabul qilinmasligi kerak');
    eq('B', Util::decode($a['answers_json'])['L']['1']);

    [$a, $ok] = A::save($a, $mock, ['section' => 'R', 'seq' => 3, 'answers' => ['1' => 'TRUE']]);
    ok(!$ok, 'joriy bo\'lim bo\'lmagan bo\'limga saqlanmaydi');
});

test('Listening audio tugamaguncha yakunlanmaydi', function (): void {
    $mock = make_mock();
    $user = make_user();
    Util::$testNowMs = 1_800_000_000_000;
    $a = A::startSection(A::startOrResume($user, (int) $mock['id']), $mock, 'L');
    Util::$testNowMs += 20_000;
    throws(fn () => A::finishSection($a, $mock, 'L'), 'listening_running');
    // Tekshirish vaqti (review) ichida yakunlash mumkin.
    Util::$testNowMs = (int) $a['section_deadline_ms'] - 30_000;
    eq('R', A::finishSection($a, $mock, 'L')['stage']);
});

test('qoidabuzarlik: chegaradan oshsa imtihon to\'xtatiladi', function (): void {
    $mock = make_mock(['settings' => ['lockdown' => ['max_violations' => 2, 'action' => 'terminate']]]);
    $user = make_user();
    $a = A::startSection(A::startOrResume($user, (int) $mock['id']), $mock, 'L');
    $a = A::recordEvents($a, [['type' => 'focus_lost'], ['type' => 'paste_blocked']], $mock);
    eq(1, (int) $a['violations']);
    $a = A::recordEvents($a, [['type' => 'fullscreen_exit']], $mock);
    eq('in_progress', $a['status']);
    $a = A::recordEvents($a, [['type' => 'focus_lost']], $mock);
    eq('terminated', $a['status']);
    eq('done', $a['stage']);
    eq(null, $a['overall']);
    // Noma'lum hodisa turlari yozilmaydi.
    A::recordEvents($a, [['type' => 'hack']], $mock);
    eq(0, (int) Db::val("SELECT COUNT(*) FROM attempt_events WHERE type = 'hack'"));
});

test('qoidabuzarlik: "faqat jurnal" rejimida to\'xtatilmaydi', function (): void {
    $mock = make_mock(['settings' => ['lockdown' => ['max_violations' => 1, 'action' => 'log']]]);
    $user = make_user();
    $a = A::startSection(A::startOrResume($user, (int) $mock['id']), $mock, 'L');
    $a = A::recordEvents($a, array_fill(0, 5, ['type' => 'focus_lost']), $mock);
    eq(5, (int) $a['violations']);
    eq('in_progress', $a['status']);
});

test('ikkinchi oyna: ruxsatsiz ochilmaydi, ko\'chirish qoidabuzarlik', function (): void {
    $mock = make_mock();
    $user = make_user();
    Util::$testNowMs = 1_800_000_000_000;
    $a = A::claim(A::startOrResume($user, (int) $mock['id']), 'client-first-1', false);
    $a = A::startSection($a, $mock, 'L');
    throws(fn () => A::claim($a, 'client-second-2', false), 'other_device');
    throws(fn () => A::assertClient($a, 'client-second-2'), 'taken_over');
    $a = A::claim($a, 'client-second-2', true);
    eq('client-second-2', $a['client_id']);
    eq(1, (int) $a['violations']);

    // Oldingi oyna uzoq vaqt jim bo'lsa — qoidabuzarliksiz ko'chiriladi.
    Util::$testNowMs += A::STALE_CLIENT_MS + 1000;
    $a = A::claim($a, 'client-third-3', false);
    eq(1, (int) $a['violations']);
});

test('Speaking: savollar ketma-ket ochiladi, yakunda ekspert navbatiga tushadi', function (): void {
    $mock = make_mock(['settings' => ['sections' => ['S']]]);
    $user = make_user();
    Util::$testNowMs = 1_800_000_000_000;
    $a = A::startOrResume($user, (int) $mock['id']);
    eq('S', $a['stage']);
    $a = A::speakingStart($a, $mock);
    eq('active', $a['stage_state']);
    throws(fn () => A::speakingFinish($a, $mock), 'speaking_unfinished');
    $nos = [];
    for ($i = 0; $i < 3; $i++) {
        [$a, $q] = A::speakingNext($a, $mock);
        $nos[] = $q['no'];
        if ($i === 0) {
            // Oldingi savol vaqti tugamaguncha keyingisi ochilmaydi.
            throws(fn () => A::speakingNext($a, $mock), 'too_early');
        }
        Util::$testNowMs += ($q['prep_sec'] + $q['answer_sec']) * 1000;
    }
    eq([1, 2, 3], $nos);
    [$a, $q] = A::speakingNext($a, $mock);
    eq(null, $q);
    $a = A::speakingFinish($a, $mock);
    eq('completed', $a['status']);
    // Yozuv yo'q — avtomatik 0.
    eq('done', $a['grade_s']);
    eq(0.0, (float) $a['s_score']);
});

test('Speaking: tashlab ketilsa avtomatik yopiladi', function (): void {
    $mock = make_mock(['settings' => ['sections' => ['S']]]);
    $user = make_user();
    Util::$testNowMs = 1_800_000_000_000;
    $a = A::speakingStart(A::startOrResume($user, (int) $mock['id']), $mock);
    [$a] = A::speakingNext($a, $mock);
    Util::$testNowMs += A::SPEAKING_ABANDON_MS + 120_000;
    $a = A::refresh($a, $mock);
    eq('completed', $a['status']);
});

test('yozma qismdan keyin Speaking alohida kutadi', function (): void {
    $mock = make_mock();
    $user = make_user();
    Util::$testNowMs = 1_800_000_000_000;
    $a = complete_written(A::startOrResume($user, (int) $mock['id']), $mock);
    eq('in_progress', $a['status']);
    eq('S', $a['stage']);
    eq('pending', $a['stage_state']);
    // Uzoq vaqt o'tsa ham Speaking o'zi boshlanmaydi.
    Util::$testNowMs += 86_400_000;
    eq('pending', A::refresh($a, $mock)['stage_state']);
    // Yozma qism tugagani uchun boshqa mockni boshlash mumkin.
    $other = make_mock(['title' => 'Boshqa']);
    $b = A::startOrResume($user, (int) $other['id']);
    eq(1, (int) $b['attempt_no']);
});

test('holat: bo\'lim kontenti faqat bo\'lim boshlanganda beriladi', function (): void {
    $mock = make_mock();
    $user = make_user();
    $a = A::startOrResume($user, (int) $mock['id']);
    $state = A::stateFor($a, $mock, $user);
    ok(!isset($state['section']['content']), 'kutish holatida savollar yuborilmaydi');
    eq(1, count($state['section']['audio']));
    $a = A::startSection($a, $mock, 'L');
    $state = A::stateFor($a, $mock, $user);
    ok(isset($state['section']['content']['parts']));
    ok(!str_contains(Util::json($state), 'nine'), 'kalit yuborilmasligi kerak');
});

test('qoidabuzarlik ko\'p ma\'lumot hodisalari orasida yo\'qolmaydi', function (): void {
    $mock = make_mock();
    $user = make_user();
    $a = A::startSection(A::startOrResume($user, (int) $mock['id']), $mock, 'L');
    $events = array_fill(0, 120, ['type' => 'contextmenu_blocked']);
    $events[] = ['type' => 'focus_lost', 'active' => true];
    $a = A::recordEvents($a, $events, $mock);
    eq(1, (int) $a['violations']);
});

test('kechikib kelgan (internet uzilgan paytdagi) qoidabuzarlik ham hisoblanadi', function (): void {
    $mock = make_mock(['settings' => ['sections' => ['R', 'W']]]);
    $user = make_user();
    $a = A::startSection(A::startOrResume($user, (int) $mock['id']), $mock, 'R');
    $a = A::finishSection($a, $mock, 'R');
    eq('pending', $a['stage_state']);
    $a = A::recordEvents($a, [['type' => 'focus_lost', 'active' => true, 'section' => 'R'], ['type' => 'focus_lost']], $mock);
    eq(1, (int) $a['violations'], "faqat faol paytdagisi hisoblanadi");
});

test('Listening audiosi yuklangach bo\'lim 3 daqiqada o\'zi boshlanadi', function (): void {
    $mock = make_mock();
    $user = make_user();
    Util::$testNowMs = 1_800_000_000_000;
    $a = A::startOrResume($user, (int) $mock['id']);
    eq(1_800_000_000_000 + A::FIRST_START_WINDOW_MS, A::autoStartAtMs($a, $mock));
    Util::$testNowMs += 60_000;
    $a = A::noteAudioFetch($a);
    eq(Util::$testNowMs + A::AUDIO_PREVIEW_LIMIT_MS, A::autoStartAtMs($a, $mock));
    Util::$testNowMs += A::AUDIO_PREVIEW_LIMIT_MS + 1;
    eq('active', A::refresh($a, $mock)['stage_state']);
});

test('Speaking: ruxsat etilgan vaqtdan uzun yozuv qabul qilinmaydi', function (): void {
    $mock = make_mock(['settings' => ['sections' => ['S']]]);
    $user = make_user();
    $a = A::speakingStart(A::startOrResume($user, (int) $mock['id']), $mock);
    [$a] = A::speakingNext($a, $mock);
    throws(fn () => A::speakingUpload($a, $mock, 1, ['error' => UPLOAD_ERR_OK, 'tmp_name' => __FILE__, 'size' => 10], 600.0), 'too_long');
});
