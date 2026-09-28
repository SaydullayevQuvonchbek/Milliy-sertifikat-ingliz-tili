<?php

declare(strict_types=1);

require_once __DIR__ . '/fixtures.php';

use App\Db;
use App\Services\AttemptService as A;
use App\Services\GradingService as G;
use App\Services\ScoreService;
use App\Util;

function writing_attempt(array $mock, string $login, array $texts): array
{
    $user = make_user('student', $login);
    $a = A::startSection(A::startOrResume($user, (int) $mock['id']), $mock, 'W');
    [$a] = A::save($a, $mock, ['section' => 'W', 'seq' => 1, 'writing' => $texts]);
    return A::finishSection($a, $mock, 'W');
}

test('Writing: ikki ekspert o\'rtachasi rasmiy jadval bilan', function (): void {
    $mock = make_mock(['settings' => ['sections' => ['W']]]);
    $a = writing_attempt($mock, '+998901110001', ['1.1' => 'Hello friend', '1.2' => 'Dear manager', '2' => 'My blog']);
    eq('queue', $a['grade_w']);

    $e1 = make_user('expert', 'expert1');
    $e2 = make_user('expert', 'expert2');
    $w = G::next($e1, 'W');
    eq((int) $a['id'], $w['attempt_id']);
    ok(!isset($w['name']) && !str_contains(Util::json($w), 'Test Foydalanuvchi'), 'ish anonim bo\'lishi kerak');

    G::rate($e1, (int) $a['id'], 'W', ['1.1' => 4, '1.2' => 3, '2' => 4], [], 'Yaxshi.');
    ok(G::next($e1, 'W') === null, 'bitta ekspert bir ishni ikki marta olmaydi');

    G::next($e2, 'W');
    $res = G::rate($e2, (int) $a['id'], 'W', ['1.1' => 3, '1.2' => 3, '2' => 4], [], '');
    ok($res['done']);
    $row = Db::one('SELECT * FROM attempts WHERE id = ?', [$a['id']]);
    near(10.5, (float) $row['w_raw']);
    eq(55.0, (float) $row['w_score']);
    eq('done', $row['grade_w']);
});

test('Writing: farq katta bo\'lsa 3-ekspert kerak', function (): void {
    $mock = make_mock(['settings' => ['sections' => ['W'], 'grading' => ['diff_w' => 3]]]);
    $a = writing_attempt($mock, '+998901110001', ['1.1' => 'Text', '1.2' => 'Text', '2' => 'Text']);
    $e = [make_user('expert', 'expert1'), make_user('expert', 'expert2'), make_user('expert', 'expert3')];
    G::next($e[0], 'W');
    G::rate($e[0], (int) $a['id'], 'W', ['1.1' => 5, '1.2' => 5, '2' => 6], [], '');
    G::next($e[1], 'W');
    $res = G::rate($e[1], (int) $a['id'], 'W', ['1.1' => 2, '1.2' => 2, '2' => 2], [], '');
    ok(!$res['done'], 'farq 10 > 3 — hali yakuniy emas');
    eq('queue', Db::val('SELECT grade_w FROM attempts WHERE id = ?', [$a['id']]));

    $w = G::next($e[2], 'W');
    eq((int) $a['id'], $w['attempt_id']);
    $res = G::rate($e[2], (int) $a['id'], 'W', ['1.1' => 4, '1.2' => 4, '2' => 5], [], '');
    ok($res['done']);
    // 3-ekspert (13) va unga yaqinroq birinchi (16) → 14.5
    near(14.5, (float) Db::val('SELECT w_raw FROM attempts WHERE id = ?', [$a['id']]));
});

test('Writing: maxsus belgi rasmiy qoidani qo\'llaydi', function (): void {
    $mock = make_mock(['settings' => ['sections' => ['W'], 'grading' => ['raters' => 1]]]);
    $a = writing_attempt($mock, '+998901110001', ['1.1' => 'Some text here', '1.2' => 'Copied text', '2' => '']);
    $e = make_user('expert', 'expert1');
    G::next($e, 'W');
    $res = G::rate($e, (int) $a['id'], 'W', ['1.1' => 4, '1.2' => 4, '2' => 3], ['1.1' => 'off_topic', '1.2' => 'copied'], '');
    eq(['1.1' => 1, '1.2' => 0, '2' => 3], $res['scores']);
});

test('Writing: bo\'sh ish avtomatik 0, navbatga tushmaydi', function (): void {
    $mock = make_mock(['settings' => ['sections' => ['W']]]);
    $a = writing_attempt($mock, '+998901110001', ['1.1' => '   ', '1.2' => '', '2' => '']);
    eq('done', $a['grade_w']);
    eq(0.0, (float) $a['w_score']);
    eq(null, G::next(make_user('expert', 'expert1'), 'W'));
});

test('baholash: ball chegarasi tekshiriladi', function (): void {
    $mock = make_mock(['settings' => ['sections' => ['W']]]);
    $a = writing_attempt($mock, '+998901110001', ['1.1' => 'x', '1.2' => 'y', '2' => 'z']);
    $e = make_user('expert', 'expert1');
    G::next($e, 'W');
    throws(fn () => G::rate($e, (int) $a['id'], 'W', ['1.1' => 6, '1.2' => 3, '2' => 3], [], ''), 'validation');
    throws(fn () => G::rate($e, (int) $a['id'], 'W', ['1.1' => 2.5, '1.2' => 3, '2' => 3], [], ''), 'validation');
    $other = make_user('expert', 'expert2');
    throws(fn () => G::rate($other, (int) $a['id'], 'W', ['1.1' => 3, '1.2' => 3, '2' => 3], [], ''), 'no_claim');
});

test('to\'liq natija: 4 ko\'nikma o\'rtachasi va daraja', function (): void {
    $mock = make_mock(['settings' => ['sections' => ['L', 'R', 'W'], 'grading' => ['raters' => 1]]]);
    $user = make_user();
    Util::$testNowMs = 1_800_000_000_000;
    $a = A::claim(A::startOrResume($user, (int) $mock['id']), 'client-aaaaaaaa', false);
    $a = A::startSection($a, $mock, 'L');
    Util::$testNowMs += 5000;
    [$a] = A::save($a, $mock, ['section' => 'L', 'seq' => 1, 'answers' => ['1' => 'B', '2' => '9']]);
    Util::$testNowMs = (int) $a['section_deadline_ms'] - 1000;
    $a = A::finishSection($a, $mock, 'L');
    $a = A::startSection($a, $mock, 'R');
    [$a] = A::save($a, $mock, ['section' => 'R', 'seq' => 2, 'answers' => ['1' => 'TRUE', '2' => 'B']]);
    $a = A::finishSection($a, $mock, 'R');
    $a = A::startSection($a, $mock, 'W');
    [$a] = A::save($a, $mock, ['section' => 'W', 'seq' => 3, 'writing' => ['1.1' => 'a', '1.2' => 'b', '2' => 'c']]);
    $a = A::finishSection($a, $mock, 'W');

    eq(2, (int) $a['l_raw']);
    eq(75.0, (float) $a['l_score']);
    eq(1, (int) $a['r_raw']);
    eq(null, $a['overall'], 'Writing baholanmaguncha umumiy ball yo\'q');

    $e = make_user('expert', 'expert1');
    G::next($e, 'W');
    G::rate($e, (int) $a['id'], 'W', ['1.1' => 3, '1.2' => 3, '2' => 3], [], '');
    $row = Db::one('SELECT * FROM attempts WHERE id = ?', [$a['id']]);
    // L=75, R=provisional(1/2*35=17.5)→50.2, W=9→50
    near(50.2, (float) $row['r_score']);
    eq(50.0, (float) $row['w_score']);
    near(round((75 + 50.2 + 50) / 3, 1), (float) $row['overall']);
    eq('B2', $row['level']);
});

test('kalitga muqobil javob qo\'shilsa, ballar qayta hisoblanadi', function (): void {
    $mock = make_mock(['settings' => ['sections' => ['L']]]);
    $user = make_user();
    Util::$testNowMs = 1_800_000_000_000;
    $a = A::startSection(A::startOrResume($user, (int) $mock['id']), $mock, 'L');
    Util::$testNowMs += 1000;
    [$a] = A::save($a, $mock, ['section' => 'L', 'seq' => 1, 'answers' => ['2' => '9am']]);
    Util::$testNowMs = (int) $a['section_deadline_ms'] - 1000;
    $a = A::finishSection($a, $mock, 'L');
    eq(0, (int) $a['l_raw']);
    ScoreService::acceptAlternative((int) $mock['id'], 'L', 2, '9am', 1);
    eq(1, (int) Db::val('SELECT l_raw FROM attempts WHERE id = ?', [$a['id']]));
    $items = ScoreService::itemAnalysis((int) $mock['id']);
    eq(1, $items['L'][1]['correct']);
});

test('qisqa mock: faqat mavjud topshiriqlar baholanadi, ball mutanosib o\'tkaziladi', function (): void {
    $source = sample_source();
    $source['writing']['parts'] = [['title' => 'Part 1', 'context' => '', 'tasks' => [['id' => '1.1', 'title' => 'Task 1.1', 'prompt' => 'Write.', 'min_words' => 10]]]];
    $mock = make_mock(['source' => $source, 'settings' => ['sections' => ['W'], 'grading' => ['raters' => 1]]]);
    eq(['1.1' => 5], G::rubric($mock, 'W'));
    $a = writing_attempt($mock, '+998901110001', ['1.1' => 'Some words here']);
    $e = make_user('expert', 'expert1');
    $work = G::next($e, 'W');
    eq([['part' => '1.1', 'max' => 5]], $work['rubric']);
    G::rate($e, (int) $a['id'], 'W', ['1.1' => 3], [], '');
    $row = Db::one('SELECT w_raw, w_score FROM attempts WHERE id = ?', [$a['id']]);
    near(3.0, (float) $row['w_raw']);
    // 3/5 × 16 = 9.6 → 9.5 → 51
    eq(51.0, (float) $row['w_score']);
});
