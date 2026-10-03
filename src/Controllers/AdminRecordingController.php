<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Audit;
use App\Auth;
use App\Db;
use App\Http\FileResponse;
use App\Http\HttpError;
use App\Http\Request;
use App\Services\Recordings;
use App\Services\Telegram;
use App\Services\TelegramError;
use App\Settings;

/** Video nazorat yozuvlari va Telegram navbati (administrator). */
final class AdminRecordingController
{
    public static function status(Request $r): array
    {
        Auth::require('admin');
        return Recordings::summary();
    }

    /** Telegram ulanishini tekshirish: kanal(lar)ga sinov xabari. */
    public static function test(Request $r): array
    {
        $admin = Auth::require('admin');
        if (!Telegram::configured()) {
            throw new HttpError(422, 'tg_not_configured', "Telegram sozlanmagan: config/config.php ga 'telegram' => ['bot_token' => …, 'chat_id' => …] yozing.");
        }
        $s = Telegram::settings();
        $chats = array_values(array_unique(array_filter([$s['chat'], $s['speaking_chat']])));
        $text = '✅ ' . Settings::get('site_name') . ": Telegram ulanishi ishlayapti.\n" . date('d.m.Y H:i');
        $results = [];
        foreach ($chats as $chat) {
            try {
                Telegram::sendMessage($chat, $text);
                $results[] = ['chat' => $chat, 'ok' => true];
            } catch (TelegramError $e) {
                $results[] = ['chat' => $chat, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        Audit::log((int) $admin['id'], 'telegram_test', '', ['ok' => array_column($results, 'ok')]);
        return ['results' => $results];
    }

    /** Navbatni hozir ishlatish (cron sozlanmagan bo'lsa yoki tezlashtirish uchun). */
    public static function run(Request $r): array
    {
        Auth::require('admin');
        @set_time_limit(60);
        $result = Recordings::process(20.0);
        return ['result' => $result] + Recordings::summary();
    }

    /** Xato bo'lgan (yoki bitta) yozuvni qayta navbatga qo'yish. */
    public static function retry(Request $r): array
    {
        $admin = Auth::require('admin');
        $id = (int) $r->param('id');
        $where = $id > 0 ? 'id = ? AND' : '';
        $params = $id > 0 ? [$id] : [];
        $n = Db::exec(
            "UPDATE recordings SET status = 'ready', tg_tries = 0, tg_next_at = 0
             WHERE {$where} status IN ('failed', 'ready') AND file_deleted = 0",
            $params
        );
        Audit::log((int) $admin['id'], 'recording_retry', $id > 0 ? 'recording:' . $id : 'all', ['count' => $n]);
        return ['requeued' => $n];
    }

    /** Videoni ko'rish (HTTP Range — pleyerda surish mumkin). */
    public static function file(Request $r): FileResponse
    {
        Auth::require('admin');
        $row = Db::one('SELECT * FROM recordings WHERE id = ?', [(int) $r->param('id')]);
        if ($row === null || (int) $row['file_deleted'] === 1 || $row['status'] === 'recording') {
            throw new HttpError(404, 'not_found', 'Video serverda yo\'q (Telegram\'ga yuborilgan yoki o\'chirilgan).');
        }
        $info = Db::one('SELECT a.attempt_no, a.anon_code, a.meta_json, u.full_name, m.title FROM attempts a
            JOIN users u ON u.id = a.user_id JOIN mocks m ON m.id = a.mock_id WHERE a.id = ?', [$row['attempt_id']]);
        $download = $r->query['download'] ?? null;
        return new FileResponse(
            Recordings::path((string) $row['file']),
            (string) $row['mime'],
            0,
            $download !== null && $info !== null ? Recordings::fileName($row, $info) : null
        );
    }
}
