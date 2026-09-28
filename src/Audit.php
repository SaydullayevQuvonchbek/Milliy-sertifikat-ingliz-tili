<?php

declare(strict_types=1);

namespace App;

/** Administrator amallari jurnali (muzlatish, urinishni bekor qilish va h.k.). */
final class Audit
{
    public static function log(?int $userId, string $action, string $target = '', array|string $detail = ''): void
    {
        Db::insert('audit_log', [
            'user_id' => $userId,
            'action' => $action,
            'target' => mb_substr($target, 0, 190),
            'detail' => is_array($detail) ? Util::json($detail) : $detail,
            'created_at' => time(),
        ]);
    }
}
