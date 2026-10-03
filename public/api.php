<?php

declare(strict_types=1);

use App\Auth;
use App\Config;
use App\Http\FileResponse;
use App\Http\HttpError;
use App\Http\Request;
use App\Http\TextResponse;
use App\Router;

require dirname(__DIR__) . '/src/bootstrap.php';

header_remove('X-Powered-By');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

function send_status(int $status): void
{
    // Apache + mod_php o'zi bilmaydigan kodlarni (419, 425) brauzerga "500 Internal Server Error" deb yuboradi (jurnalda
    // esa 419 turadi); hosting proksisi 5xx javobni o'z sahifasi bilan almashtirsa, JSON xabari yo'qoladi.
    // Shuning uchun bu kodlar uchun holat qatori aniq yoziladi.
    $reasons = [419 => 'Page Expired', 425 => 'Too Early'];
    if (isset($reasons[$status])) {
        header("HTTP/1.1 {$status} {$reasons[$status]}", true, $status);
    } else {
        http_response_code($status);
    }
}

function send_json(mixed $data, int $status = 200): void
{
    send_status($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
}

try {
    App\Installer::ensureSchema();
    $request = Request::capture();
    Auth::start();
    Auth::user();
    Auth::checkCsrf($request);
    // Sessiya qulfini bo'shatamiz — audio yuklash va saqlash so'rovlari bir-birini kutmasin.
    if (!str_starts_with($request->path, '/auth/')) {
        Auth::release();
    }

    $router = new Router();
    (require dirname(__DIR__) . '/src/routes.php')($router);
    $result = $router->dispatch($request);

    if ($result instanceof FileResponse || $result instanceof TextResponse) {
        $result->send();
    } else {
        send_json($result);
    }
} catch (HttpError $e) {
    send_json(['error' => ['code' => $e->errorCode, 'message' => $e->getMessage()] + $e->extra], $e->status);
} catch (Throwable $e) {
    error_log('[mock] ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $payload = ['error' => ['code' => 'server_error', 'message' => "Serverda xatolik yuz berdi. Birozdan keyin qayta urinib ko'ring."]];
    if (Config::get('debug')) {
        $payload['error']['debug'] = $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine();
    }
    send_json($payload, 500);
}
