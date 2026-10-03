<?php

// Video nazorat navbati: uzilib qolgan yozuvlarni yig'ish, muddati o'tganlarini o'chirish va tayyor videolarni
// Telegram kanalga yuborish. Cron orqali har daqiqada ishga tushiring:
//
//   * * * * *  php /yo'l/bin/recordings.php
//
// (reg.ru: /opt/php/8.3/bin/php /var/www/u1234567/data/www/sayt/bin/recordings.php)
//
//   php bin/recordings.php            # navbatni ishlatish (~50 soniya)
//   php bin/recordings.php --test     # Telegram kanal(lar)iga sinov xabari
//   php bin/recordings.php --status   # navbat holati
//   php bin/recordings.php --retry    # xato bo'lgan yozuvlarni qayta navbatga qo'yish

declare(strict_types=1);

// Faqat buyruq qatoridan (yoki cron'dan): veb-so'rov orqali ochilsa (masalan, .htaccess ishlamay qolganda) hech narsa
// qilmaydi. Ayrim hostinglarda cron php-cgi bilan ishlaydi — u REQUEST_METHOD siz keladi, shuning uchun to'xtatilmaydi.
if (PHP_SAPI !== 'cli' && isset($_SERVER['REQUEST_METHOD'])) {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../src/bootstrap.php';

use App\Db;
use App\Installer;
use App\Services\Recordings;
use App\Services\Telegram;
use App\Services\TelegramError;

function rec_say(string $text, bool $error = false): void
{
    if ($error && defined('STDERR')) {
        fwrite(STDERR, $text . "\n");
    } else {
        echo $text . "\n";
    }
}

$options = getopt('', ['test', 'status', 'retry', 'budget::']);
Installer::ensureSchema();

if (isset($options['test'])) {
    if (!Telegram::configured()) {
        rec_say("Telegram sozlanmagan: config/config.php ga 'telegram' => ['bot_token' => '…', 'chat_id' => '…'] yozing.", true);
        exit(1);
    }
    $s = Telegram::settings();
    $exit = 0;
    foreach (array_values(array_unique(array_filter([$s['chat'], $s['speaking_chat']]))) as $chat) {
        try {
            Telegram::sendMessage($chat, '✅ Multilevel Mock: Telegram ulanishi ishlayapti. ' . date('d.m.Y H:i'));
            rec_say("✓ {$chat}: xabar yuborildi.");
        } catch (TelegramError $e) {
            rec_say("✗ {$chat}: " . $e->getMessage(), true);
            $exit = 1;
        }
    }
    exit($exit);
}

if (isset($options['retry'])) {
    $n = Db::exec("UPDATE recordings SET status = 'ready', tg_tries = 0, tg_next_at = 0 WHERE status IN ('failed', 'ready') AND file_deleted = 0");
    rec_say("{$n} ta yozuv qayta navbatga qo'yildi.");
    exit(0);
}

if (isset($options['status'])) {
    $summary = Recordings::summary();
    rec_say('Telegram: ' . ($summary['telegram']['configured'] ? 'sozlangan (' . $summary['telegram']['api_host'] . ')' : 'sozlanmagan'));
    foreach ($summary['counts'] as $status => $c) {
        rec_say(sprintf('  %-10s %5d ta, serverda %.1f MB', $status, $c['count'], $c['bytes'] / 1048576));
    }
    if (!empty($summary['queue']['last_error'])) {
        rec_say('Oxirgi xato: ' . $summary['queue']['last_error'] . ' (' . date('d.m H:i', (int) $summary['queue']['last_error_at']) . ')');
    }
    exit(0);
}

$budget = isset($options['budget']) && is_numeric($options['budget']) ? max(5.0, min(600.0, (float) $options['budget'])) : 50.0;
@set_time_limit((int) $budget + 60);
$result = Recordings::process($budget);
if (!empty($result['busy'])) {
    exit(0); // Boshqa jarayon ishlayapti.
}
if ($result['sent'] || $result['failed'] || $result['closed'] || $result['expired'] || !empty($result['error'])) {
    rec_say(sprintf(
        '%s yuborildi: %d, xato: %d, yopildi: %d, o\'chirildi: %d%s',
        date('H:i:s'),
        $result['sent'],
        $result['failed'],
        $result['closed'],
        $result['expired'],
        !empty($result['error']) ? ' — ' . $result['error'] : ''
    ));
}
exit(0);
