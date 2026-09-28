<?php

declare(strict_types=1);

namespace App\Services;

use Normalizer;

/**
 * Baholash qoidalari: gap-fill javoblarini solishtirish, Listening/Reading xom balli,
 * Writing/Speaking rasmiy o'tkazish jadvallari, umumiy ball va daraja.
 */
final class Scoring
{
    public const TFNG = ['TRUE', 'FALSE', 'NOT GIVEN'];

    /** Writing: xom ball (16 dan) → 75 ballik shkala (rasmiy jadval). */
    public const WRITING_TABLE = [
        '16' => 75, '15.5' => 72, '15' => 69, '14.5' => 67, '14' => 65, '13.5' => 64, '13' => 63,
        '12.5' => 62, '12' => 61, '11.5' => 59, '11' => 57, '10.5' => 55, '10' => 53, '9.5' => 51,
        '9' => 50, '8.5' => 48, '8' => 47, '7.5' => 45, '7' => 43, '6.5' => 41, '6' => 40, '5.5' => 38,
        '5' => 37, '4.5' => 35, '4' => 33, '3.5' => 31, '3' => 28, '2.5' => 25, '2' => 21, '1.5' => 17,
        '1' => 14, '0.5' => 10, '0' => 0,
    ];

    /** Speaking: xom ball (21 dan) → 75 ballik shkala (rasmiy jadval). */
    public const SPEAKING_TABLE = [
        '21' => 75, '20.5' => 73, '20' => 71, '19.5' => 69, '19' => 67, '18.5' => 65, '18' => 64,
        '17.5' => 63, '17' => 61, '16.5' => 59, '16' => 57, '15.5' => 56, '15' => 54, '14.5' => 52,
        '14' => 51, '13.5' => 50, '13' => 49, '12.5' => 47, '12' => 46, '11.5' => 45, '11' => 43,
        '10.5' => 42, '10' => 40, '9.5' => 39, '9' => 38, '8.5' => 37, '8' => 35, '7.5' => 33, '7' => 32,
        '6.5' => 30, '6' => 29, '5.5' => 27, '5' => 26, '4.5' => 24, '4' => 23, '3.5' => 21, '3' => 19,
        '2.5' => 17, '2' => 15, '1.5' => 13, '1' => 11, '0.5' => 10, '0' => 0,
    ];

    /** Listening/Reading taxminiy shkalasi (Rasch hisoblanmaguncha): to'g'ri javoblar → 75 ballik. */
    public const PROVISIONAL_POINTS = [[0, 0], [10, 38], [18, 51], [28, 65], [35, 75]];

    public const LEVELS = [['C1', 65], ['B2', 51], ['B1', 38]];

    /** Gap-fill javobini solishtirish uchun bir xil shaklga keltirish. */
    public static function normalize(string $value): string
    {
        if (class_exists(Normalizer::class)) {
            $value = Normalizer::normalize($value, Normalizer::FORM_KC) ?: $value;
        }
        $value = mb_strtolower($value);
        // Apostrof va tire turlari.
        $value = str_replace(["\u{2019}", "\u{2018}", "\u{02BC}", "\u{02BB}", '`', "\u{00B4}"], "'", $value);
        $value = str_replace(["\u{2013}", "\u{2014}", "\u{2010}", "\u{2011}", "\u{2212}"], '-', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = trim($value);
        // Boshidagi va oxiridagi tinish belgilari ("library." → "library").
        $value = preg_replace('/^[\s.,;:!?"()\[\]]+|[\s.,;:!?"()\[\]]+$/u', '', $value) ?? $value;
        return $value;
    }

    public static function wordCount(string $normalized): int
    {
        return $normalized === '' ? 0 : count(explode(' ', $normalized));
    }

    /** Kalitdagi muqobillar "|" bilan ajratiladi: "colour|color". */
    public static function gapCorrect(string $given, string $key): bool
    {
        $answer = self::normalize($given);
        if ($answer === '') {
            return false;
        }
        foreach (explode('|', $key) as $alternative) {
            if ($answer === self::normalize($alternative)) {
                return true;
            }
        }
        return false;
    }

    public static function isCorrect(array $keyItem, mixed $given): bool
    {
        if (!is_scalar($given)) {
            return false;
        }
        $given = (string) $given;
        $type = (string) ($keyItem['type'] ?? '');
        $answer = (string) ($keyItem['answer'] ?? '');
        if ($answer === '') {
            return false;
        }
        if ($type === 'gap') {
            return self::gapCorrect($given, $answer);
        }
        return strtoupper(trim($given)) === strtoupper(trim($answer));
    }

    /**
     * @param array<string,array> $key    savol raqami → kalit
     * @param array<string,mixed> $answers savol raqami → javob
     * @return array{raw:int,total:int,items:array<string,int>}
     */
    public static function scoreSection(array $key, array $answers): array
    {
        $items = [];
        $raw = 0;
        foreach ($key as $n => $item) {
            $ok = self::isCorrect($item, $answers[(string) $n] ?? '') ? 1 : 0;
            $items[(string) $n] = $ok;
            $raw += $ok;
        }
        return ['raw' => $raw, 'total' => count($key), 'items' => $items];
    }

    public static function provisional75(int $raw, int $total = 35): float
    {
        if ($total <= 0) {
            return 0.0;
        }
        $x = $raw / $total * 35;
        $points = self::PROVISIONAL_POINTS;
        for ($i = 1, $n = count($points); $i < $n; $i++) {
            [$x1, $y1] = $points[$i];
            [$x0, $y0] = $points[$i - 1];
            if ($x <= $x1) {
                return round($y0 + ($y1 - $y0) * ($x - $x0) / ($x1 - $x0), 1);
            }
        }
        return 75.0;
    }

    /** Rasch natijasini 75 ballik shkalaga o'tkazish: T = 50 + 10Z, 0..75 oralig'ida. */
    public static function raschTo75(float $theta, float $mu, float $sigma): float
    {
        if ($sigma <= 0) {
            return 0.0;
        }
        $t = 50 + 10 * (($theta - $mu) / $sigma);
        return round(max(0.0, min(75.0, $t)), 1);
    }

    /** Xom ballni eng yaqin 0.5 ga yaxlitlash (yarmi yuqoriga). */
    public static function roundHalf(float $raw): float
    {
        return floor($raw * 2 + 0.5 + 1e-9) / 2;
    }

    public static function writing75(float $raw): int
    {
        return self::fromTable(self::WRITING_TABLE, $raw, 16);
    }

    public static function speaking75(float $raw): int
    {
        return self::fromTable(self::SPEAKING_TABLE, $raw, 21);
    }

    private static function fromTable(array $table, float $raw, float $max): int
    {
        $raw = max(0.0, min($max, self::roundHalf($raw)));
        $key = rtrim(rtrim(number_format($raw, 1, '.', ''), '0'), '.');
        return (int) ($table[$key] ?? 0);
    }

    /** Umumiy ball — kiritilgan ko'nikmalar ballining o'rtachasi. Biror ball yo'q bo'lsa null. */
    public static function overall(array $scores): ?float
    {
        if ($scores === []) {
            return null;
        }
        foreach ($scores as $score) {
            if ($score === null) {
                return null;
            }
        }
        return round(array_sum($scores) / count($scores), 1);
    }

    /** Daraja butun songa yaxlitlangan umumiy ball bo'yicha beriladi. */
    public static function level(?float $overall): ?string
    {
        if ($overall === null) {
            return null;
        }
        $rounded = (int) floor($overall + 0.5);
        foreach (self::LEVELS as [$level, $min]) {
            if ($rounded >= $min) {
                return $level;
            }
        }
        return 'below';
    }
}
