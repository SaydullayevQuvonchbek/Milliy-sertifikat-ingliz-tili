<?php

// Lokal ishlab chiqish serveri uchun marshrutlovchi:
//   php -S 127.0.0.1:8080 -t public bin/dev-router.php

declare(strict_types=1);

// Faqat PHP'ning o'rnatilgan serveri (php -S) uchun.
if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../src/Http/Security.php';

/** HTML sahifani xavfsizlik sarlavhalari bilan berish (Apache'da buni .htaccess bajaradi). */
function serve_html(string $path): bool
{
    foreach (App\Http\Security::htmlHeaders() as $name => $value) {
        header($name . ': ' . $value);
    }
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache');
    readfile($path);
    return true;
}

$uri = urldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

if (preg_match('#^/api/(.*)$#', $uri, $m)) {
    $_GET['route'] = $m[1];
    require __DIR__ . '/../public/api.php';
    return true;
}

$file = __DIR__ . '/../public' . $uri;
if ($uri !== '/' && is_file($file)) {
    return str_ends_with($file, '.html') ? serve_html($file) : false;
}
if (is_dir($file) && is_file(rtrim($file, '/') . '/index.html')) {
    if (!str_ends_with($uri, '/')) {
        header('Location: ' . $uri . '/');
        return true;
    }
    return serve_html(rtrim($file, '/') . '/index.html');
}
http_response_code(404);
echo 'Not found';
return true;
