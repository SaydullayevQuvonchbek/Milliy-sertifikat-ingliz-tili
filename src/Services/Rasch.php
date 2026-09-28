<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Dixotomik Rasch modeli — JMLE (Joint Maximum Likelihood Estimation).
 * Kichik dasturiy yadro: savol qiyinligi (b) va o'quvchi qobiliyati (θ) logitlarda.
 */
final class Rasch
{
    private const MAX_ITER = 200;
    private const TOLERANCE = 0.0005;
    private const MAX_STEP = 1.0;
    /** 0 yoki to'liq ball uchun tuzatish (cheksiz baho chiqmasligi uchun). */
    private const EXTREME = 0.3;

    /**
     * @param array<int, array<int,int>> $matrix [o'quvchi][savol] = 0|1
     * @return array{theta: float[], difficulty: array<int, float|null>, iterations:int}
     */
    public static function estimate(array $matrix): array
    {
        $persons = array_values($matrix);
        $np = count($persons);
        $ni = $np > 0 ? count($persons[0]) : 0;
        if ($np === 0 || $ni === 0) {
            return ['theta' => [], 'difficulty' => [], 'iterations' => 0];
        }

        $itemScore = array_fill(0, $ni, 0);
        foreach ($persons as $row) {
            foreach ($row as $i => $x) {
                $itemScore[$i] += $x ? 1 : 0;
            }
        }
        // Hamma to'g'ri yoki hamma noto'g'ri topgan savollar baholashda qatnashmaydi.
        $active = [];
        for ($i = 0; $i < $ni; $i++) {
            if ($itemScore[$i] > 0 && $itemScore[$i] < $np) {
                $active[] = $i;
            }
        }
        $l = count($active);
        $difficulty = array_fill(0, $ni, null);
        if ($l === 0) {
            return ['theta' => array_fill(0, $np, 0.0), 'difficulty' => $difficulty, 'iterations' => 0];
        }

        // Boshlang'ich qiymatlar: logit(p).
        $b = [];
        foreach ($active as $i) {
            $p = $itemScore[$i] / $np;
            $b[$i] = log((1 - $p) / $p);
        }
        $b = self::center($b);

        $raw = [];
        $theta = [];
        foreach ($persons as $n => $row) {
            $r = 0;
            foreach ($active as $i) {
                $r += $row[$i] ? 1 : 0;
            }
            $raw[$n] = self::adjust($r, $l);
            $theta[$n] = log($raw[$n] / ($l - $raw[$n]));
        }

        $iterations = 0;
        for ($iter = 0; $iter < self::MAX_ITER; $iter++) {
            $iterations++;
            $maxChange = 0.0;

            foreach ($persons as $n => $row) {
                $expected = 0.0;
                $info = 0.0;
                foreach ($active as $i) {
                    $p = self::p($theta[$n], $b[$i]);
                    $expected += $p;
                    $info += $p * (1 - $p);
                }
                $step = $info > 0 ? ($raw[$n] - $expected) / $info : 0.0;
                $step = max(-self::MAX_STEP, min(self::MAX_STEP, $step));
                $theta[$n] += $step;
                $maxChange = max($maxChange, abs($step));
            }

            foreach ($active as $i) {
                $expected = 0.0;
                $info = 0.0;
                foreach ($persons as $n => $row) {
                    $p = self::p($theta[$n], $b[$i]);
                    $expected += $p;
                    $info += $p * (1 - $p);
                }
                $step = $info > 0 ? ($expected - $itemScore[$i]) / $info : 0.0;
                $step = max(-self::MAX_STEP, min(self::MAX_STEP, $step));
                $b[$i] += $step;
                $maxChange = max($maxChange, abs($step));
            }
            $b = self::center($b);

            if ($maxChange < self::TOLERANCE) {
                break;
            }
        }

        // JMLE siljishini tuzatish: (L − 1) / L.
        $factor = $l > 1 ? ($l - 1) / $l : 1.0;
        foreach ($active as $i) {
            $difficulty[$i] = round($b[$i] * $factor, 4);
        }
        $thetaOut = [];
        foreach ($theta as $n => $value) {
            $thetaOut[$n] = round($value * $factor, 4);
        }

        return ['theta' => $thetaOut, 'difficulty' => $difficulty, 'iterations' => $iterations];
    }

    /**
     * Qiyinliklar ma'lum bo'lganda bitta o'quvchi qobiliyatini baholash (keyin qo'shilganlar uchun).
     * @param array<int,int> $responses
     * @param array<int,float|null> $difficulty
     */
    public static function personAbility(array $responses, array $difficulty): float
    {
        $items = [];
        foreach ($difficulty as $i => $d) {
            if ($d !== null) {
                $items[$i] = (float) $d;
            }
        }
        $l = count($items);
        if ($l === 0) {
            return 0.0;
        }
        $r = 0;
        foreach ($items as $i => $d) {
            $r += !empty($responses[$i]) ? 1 : 0;
        }
        $r = self::adjust($r, $l);
        $theta = log($r / ($l - $r));
        for ($iter = 0; $iter < self::MAX_ITER; $iter++) {
            $expected = 0.0;
            $info = 0.0;
            foreach ($items as $d) {
                $p = self::p($theta, $d);
                $expected += $p;
                $info += $p * (1 - $p);
            }
            $step = $info > 0 ? ($r - $expected) / $info : 0.0;
            $step = max(-self::MAX_STEP, min(self::MAX_STEP, $step));
            $theta += $step;
            if (abs($step) < self::TOLERANCE) {
                break;
            }
        }
        return round($theta, 4);
    }

    /** @param float[] $values */
    public static function meanSd(array $values): array
    {
        $n = count($values);
        if ($n === 0) {
            return [0.0, 0.0];
        }
        $mean = array_sum($values) / $n;
        $var = 0.0;
        foreach ($values as $v) {
            $var += ($v - $mean) ** 2;
        }
        $sd = $n > 1 ? sqrt($var / ($n - 1)) : 0.0;
        return [round($mean, 4), round($sd, 4)];
    }

    private static function p(float $theta, float $b): float
    {
        return 1.0 / (1.0 + exp($b - $theta));
    }

    private static function adjust(float $score, int $max): float
    {
        if ($score <= 0) {
            return self::EXTREME;
        }
        if ($score >= $max) {
            return $max - self::EXTREME;
        }
        return $score;
    }

    /** @param array<int,float> $b */
    private static function center(array $b): array
    {
        $mean = array_sum($b) / max(1, count($b));
        foreach ($b as $i => $value) {
            $b[$i] = $value - $mean;
        }
        return $b;
    }
}
