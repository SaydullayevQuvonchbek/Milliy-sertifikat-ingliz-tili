<?php

declare(strict_types=1);

require_once __DIR__ . '/fixtures.php';

use App\Services\MockService;
use App\Util;

test('kompilyatsiya: o\'quvchi kontentida javob va transkript yo\'q', function (): void {
    $compiled = MockService::compile(sample_source());
    $json = Util::json($compiled['content']);
    ok(!str_contains($json, '"answer"') && !str_contains($json, '"answers"'), 'content ichida answer qolmasligi kerak');
    ok(!str_contains($json, 'secret'), 'transkript o\'quvchiga yuborilmasligi kerak');
    ok(!str_contains($json, 'nine'), 'gap-fill kaliti o\'quvchiga yuborilmasligi kerak');
    eq('B', $compiled['key']['L']['1']['answer']);
    eq('nine|9', $compiled['key']['L']['2']['answer']);
    eq('gap', $compiled['key']['L']['2']['type']);
    eq('TRUE', $compiled['key']['R']['1']['answer']);
    eq('A', $compiled['key']['R']['2']['answer']);
});

test('Listening davomiyligi frontend qoidasi bilan bir xil', function (): void {
    $content = MockService::compile(sample_source())['content'];
    // 10 s ko'rib chiqish + 2 × 20.5 s audio + 5 s oraliq + 60 s tekshirish = 116 s
    eq(116000, MockService::listeningDurationMs($content['listening']));
});

test('tekshiruv: to\'g\'ri mockda xato yo\'q', function (): void {
    $settings = MockService::defaultSettings();
    $result = MockService::validate(sample_source(), $settings);
    eq([], $result['errors'], 'xatolar: ' . Util::json($result['errors']));
    eq(2, $result['summary']['L']['questions']);
});

test('tekshiruv: takroriy raqam, javobsiz savol va audio yo\'qligi aniqlanadi', function (): void {
    $source = sample_source();
    $source['reading']['parts'][0]['blocks'][] = ['type' => 'tfng', 'n' => 2, 'prompt' => 'Duplicate.', 'answer' => ''];
    $source['listening']['parts'][0]['tracks'] = [];
    $result = MockService::validate($source, MockService::defaultSettings());
    $messages = implode(' | ', array_column($result['errors'], 'message'));
    ok(str_contains($messages, "2-savol raqami 2 marta"), $messages);
    ok(str_contains($messages, "to'g'ri javob kiritilmagan"), $messages);
    ok(str_contains($messages, 'Audio fayl biriktirilmagan'), $messages);
});

test('tekshiruv: raqamlar uzluksiz bo\'lishi shart', function (): void {
    $source = sample_source();
    $source['reading']['parts'][0]['blocks'][0]['n'] = 5;
    $result = MockService::validate($source, MockService::defaultSettings());
    $messages = implode(' | ', array_column($result['errors'], 'message'));
    ok(str_contains($messages, 'tushib qolgan'), $messages);
});

test('imlo lint: kirill harfi, takroriy so\'z, ortiqcha bo\'sh joy', function (): void {
    $issues = MockService::lintText("The river flows to the the sea.");
    ok(str_contains(implode(' ', $issues), 'Takrorlangan'), implode(' ', $issues));

    // "Lоndon" ichidagi "о" — kirill harfi.
    $issues = MockService::lintText("She lives in L\u{043E}ndon.");
    ok(str_contains(implode(' ', $issues), 'kirill'), implode(' ', $issues));

    $issues = MockService::lintText('Two  spaces here.');
    ok(str_contains(implode(' ', $issues), "bo'sh joy"), implode(' ', $issues));

    $issues = MockService::lintText('Hello , world.');
    ok(str_contains(implode(' ', $issues), 'Tinish'), implode(' ', $issues));

    $issues = MockService::lintText('It ends here. then it continues.');
    ok(str_contains(implode(' ', $issues), 'kichik harf'), implode(' ', $issues));

    eq([], MockService::lintText('A clean sentence, with commas; and a colon: fine. The time is 10:30 a.m. today.'));
    eq([], MockService::lintText('The museum opens at [[2]] a.m. on weekdays.'));
});

test('sozlamalar chegaralanadi', function (): void {
    $s = MockService::mergeSettings(['times' => ['reading' => 5], 'lockdown' => ['max_violations' => 999, 'action' => 'x'], 'grading' => ['raters' => 7]]);
    eq(60, $s['times']['reading']);
    eq(50, $s['lockdown']['max_violations']);
    eq('terminate', $s['lockdown']['action']);
    eq(2, $s['grading']['raters']);
});

test('urinishlar chegarasi 2 dan oshmaydi', function (): void {
    $mock = make_mock(['max_attempts' => 5]);
    eq(2, MockService::maxAttempts($mock));
    $mock = make_mock(['max_attempts' => 1]);
    eq(1, MockService::maxAttempts($mock));
});

test('faol mockni faqat xatosiz holatda faollashtirish mumkin', function (): void {
    $source = sample_source();
    $source['listening']['parts'][0]['blocks'][0]['answer'] = '';
    $mock = make_mock(['source' => $source], 'draft');
    throws(fn () => MockService::setStatus((int) $mock['id'], 'active', 1), 'validation');
    MockService::setStatus((int) $mock['id'], 'frozen', 1);
    eq('frozen', MockService::find((int) $mock['id'])['status']);
});
