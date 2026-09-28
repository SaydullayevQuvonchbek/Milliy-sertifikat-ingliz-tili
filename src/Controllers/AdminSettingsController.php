<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Audit;
use App\Auth;
use App\Http\Request;
use App\Settings;
use App\Util;

final class AdminSettingsController
{
    public static function get(Request $r): array
    {
        Auth::require('admin');
        return ['settings' => Settings::all()];
    }

    public static function update(Request $r): array
    {
        $admin = Auth::require('admin');
        if ($r->input('site_name') !== null) {
            $name = trim(Util::cleanText($r->str('site_name'), 80));
            if ($name !== '') {
                Settings::set('site_name', $name);
            }
        }
        if ($r->input('registration_open') !== null) {
            Settings::set('registration_open', (bool) $r->input('registration_open'));
        }
        if ($r->input('max_attempts_cap') !== null) {
            Settings::set('max_attempts_cap', max(1, min(2, $r->int('max_attempts_cap', 2))));
        }
        Audit::log((int) $admin['id'], 'settings_update');
        return ['settings' => Settings::all()];
    }
}
