<?php

// PHP testlari: php tests/php/run.php [filtr]

declare(strict_types=1);

putenv('MOCK_TESTING=1');
require __DIR__ . '/../../src/bootstrap.php';

use App\Auth;
use App\Config;
use App\Db;
use App\Installer;
use App\Settings;
use App\Util;

$GLOBALS['__tests'] = [];

function test(string $name, callable $fn): void
{
    $GLOBALS['__tests'][] = [$name, $fn];
}

final class AssertionFailed extends RuntimeException
{
}

function ok(bool $condition, string $message = 'shart bajarilmadi'): void
{
    if (!$condition) {
        throw new AssertionFailed($message);
    }
}

function eq(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new AssertionFailed(($message !== '' ? $message . ': ' : '') . 'kutilgan ' . var_export($expected, true) . ', olingan ' . var_export($actual, true));
    }
}

function near(float $expected, float $actual, float $eps = 0.01, string $message = ''): void
{
    if (abs($expected - $actual) > $eps) {
        throw new AssertionFailed(($message !== '' ? $message . ': ' : '') . "kutilgan {$expected}, olingan {$actual}");
    }
}

function throws(callable $fn, string $code): void
{
    try {
        $fn();
    } catch (App\Http\HttpError $e) {
        eq($code, $e->errorCode, 'xatolik kodi');
        return;
    }
    throw new AssertionFailed("'{$code}' xatoligi kutilgan edi");
}

/** Har test uchun toza baza. */
function fresh_db(): void
{
    Db::connect(['driver' => 'sqlite', 'sqlite_path' => ':memory:']);
    Installer::install();
    Settings::reset();
    Util::$testNowMs = null;
    Auth::enableTestMode();
}

Config::set('storage_path', sys_get_temp_dir() . '/mlmock-test-' . getmypid());

foreach (glob(__DIR__ . '/*Test.php') ?: [] as $file) {
    require $file;
}

$filter = $argv[1] ?? '';
$passed = 0;
$failed = 0;
foreach ($GLOBALS['__tests'] as [$name, $fn]) {
    if ($filter !== '' && !str_contains($name, $filter)) {
        continue;
    }
    fresh_db();
    try {
        $fn();
        $passed++;
        echo "  ✓ {$name}\n";
    } catch (Throwable $e) {
        $failed++;
        echo "  ✗ {$name}\n      " . $e::class . ': ' . $e->getMessage() . "\n      " . basename($e->getFile()) . ':' . $e->getLine() . "\n";
    }
}
echo "\n{$passed} ta o'tdi, {$failed} ta xato.\n";
exit($failed > 0 ? 1 : 0);
