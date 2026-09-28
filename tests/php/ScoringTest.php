<?php

declare(strict_types=1);

use App\Services\Rasch;
use App\Services\Scoring;

test('gap-fill: registr, nuqta va bo\'sh joylar hisobga olinmaydi', function (): void {
    ok(Scoring::gapCorrect('  Library. ', 'library'));
    ok(Scoring::gapCorrect('LIBRARY', 'library'));
    ok(Scoring::gapCorrect("don\u{2019}t", "don't"));
    ok(Scoring::gapCorrect('twenty-five', "twenty\u{2013}five"));
});

test('gap-fill: muqobillar "|" bilan, ortiqcha so\'z noto\'g\'ri', function (): void {
    ok(Scoring::gapCorrect('Color', 'colour|color'));
    ok(Scoring::gapCorrect('colour', 'colour|color'));
    ok(!Scoring::gapCorrect('the library', 'library'));
    ok(!Scoring::gapCorrect('librery', 'library'));
    ok(!Scoring::gapCorrect('', 'library'));
});

test('test javoblari harf bo\'yicha solishtiriladi', function (): void {
    ok(Scoring::isCorrect(['type' => 'mcq', 'answer' => 'B'], 'b'));
    ok(!Scoring::isCorrect(['type' => 'mcq', 'answer' => 'B'], 'C'));
    ok(Scoring::isCorrect(['type' => 'tfng', 'answer' => 'NOT GIVEN'], 'NOT GIVEN'));
    ok(!Scoring::isCorrect(['type' => 'tfng', 'answer' => 'TRUE'], ''));
    ok(!Scoring::isCorrect(['type' => 'mcq', 'answer' => ''], ''));
});

test('bo\'lim xom balli', function (): void {
    $key = [
        '1' => ['type' => 'mcq', 'answer' => 'A'],
        '2' => ['type' => 'gap', 'answer' => 'river|rivers', 'max_words' => 1],
        '3' => ['type' => 'tfng', 'answer' => 'FALSE'],
    ];
    $result = Scoring::scoreSection($key, ['1' => 'A', '2' => 'Rivers', '3' => 'TRUE']);
    eq(2, $result['raw']);
    eq(3, $result['total']);
    eq(['1' => 1, '2' => 1, '3' => 0], $result['items']);
});

test('taxminiy shkala agentlik chegaralariga mos', function (): void {
    near(0.0, Scoring::provisional75(0));
    near(38.0, Scoring::provisional75(10));
    near(51.0, Scoring::provisional75(18));
    near(65.0, Scoring::provisional75(28));
    near(75.0, Scoring::provisional75(35));
    near(44.5, Scoring::provisional75(14));
});

test('Rasch → 75 ballik: +2.5 va undan yuqori 75 ga teng', function (): void {
    near(50.0, Scoring::raschTo75(0.2, 0.2, 1.0));
    near(75.0, Scoring::raschTo75(2.7, 0.2, 1.0));
    near(75.0, Scoring::raschTo75(5.0, 0.2, 1.0));
    near(0.0, Scoring::raschTo75(-9.0, 0.0, 1.0));
    near(60.0, Scoring::raschTo75(1.5, 0.5, 1.0));
});

test('Writing va Speaking rasmiy jadvallari', function (): void {
    eq(75, Scoring::writing75(16));
    eq(57, Scoring::writing75(11));
    eq(55, Scoring::writing75(10.5));
    eq(0, Scoring::writing75(0));
    eq(55, Scoring::writing75(10.25), 'eng yaqin 0.5 ga yaxlitlanadi');
    eq(75, Scoring::speaking75(21));
    eq(49, Scoring::speaking75(13));
    eq(51, Scoring::speaking75(14));
});

test('umumiy ball va daraja', function (): void {
    $overall = Scoring::overall([60.5, 55.2, 57.0, 49.0]);
    near(55.4, (float) $overall);
    eq('B2', Scoring::level($overall));
    eq('C1', Scoring::level(65.0));
    eq('B1', Scoring::level(50.4));
    eq('B2', Scoring::level(50.5));
    eq('below', Scoring::level(37.4));
    eq(null, Scoring::overall([60.0, null]));
});

test('Rasch: sintetik ma\'lumotda haqiqiy qiymatlarni tiklaydi', function (): void {
    mt_srand(42);
    $difficulty = [];
    for ($i = 0; $i < 30; $i++) {
        $difficulty[] = -2.0 + 4.0 * $i / 29;
    }
    $abilities = [];
    $matrix = [];
    for ($n = 0; $n < 400; $n++) {
        $theta = (mt_rand() / mt_getrandmax() - 0.5) * 4;
        $abilities[] = $theta;
        $row = [];
        foreach ($difficulty as $b) {
            $p = 1 / (1 + exp($b - $theta));
            $row[] = (mt_rand() / mt_getrandmax()) < $p ? 1 : 0;
        }
        $matrix[] = $row;
    }
    $est = Rasch::estimate($matrix);
    $corr = static function (array $x, array $y): float {
        $n = count($x);
        $mx = array_sum($x) / $n;
        $my = array_sum($y) / $n;
        $sxy = $sxx = $syy = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $sxy += ($x[$i] - $mx) * ($y[$i] - $my);
            $sxx += ($x[$i] - $mx) ** 2;
            $syy += ($y[$i] - $my) ** 2;
        }
        return $sxy / sqrt($sxx * $syy);
    };
    ok($corr($difficulty, array_map('floatval', $est['difficulty'])) > 0.97, 'qiyinliklar korrelyatsiyasi');
    ok($corr($abilities, $est['theta']) > 0.85, 'qobiliyatlar korrelyatsiyasi');

    // Qiyinliklar ma'lum bo'lsa, alohida o'quvchi bahosi umumiy bahoga yaqin.
    $single = Rasch::personAbility($matrix[5], $est['difficulty']);
    near($est['theta'][5], $single, 0.35, 'bitta o\'quvchi bahosi');
});

test('Rasch: hamma to\'g\'ri topgan savol baholashdan chiqariladi', function (): void {
    $matrix = [[1, 1, 0], [1, 0, 0], [1, 1, 1], [1, 0, 1]];
    $est = Rasch::estimate($matrix);
    eq(null, $est['difficulty'][0]);
    ok($est['difficulty'][1] !== null && $est['difficulty'][2] !== null);
});

test('so\'z sanash frontend qoidasi bilan bir xil', function (): void {
    eq(0, App\Util::wordCount(''));
    eq(2, App\Util::wordCount('  Hello   world  '));
    eq(6, App\Util::wordCount("It's a well-known fact, isn't it?"));
    eq(1, App\Util::wordCount('— , . 2024'));
    eq(4, App\Util::wordCount("Line one\nline two"));
});
