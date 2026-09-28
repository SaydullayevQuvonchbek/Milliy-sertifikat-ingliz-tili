<?php

// Lokal ishlab chiqish serveri uchun marshrutlovchi:
//   php -S 127.0.0.1:8080 -t public bin/dev-router.php

declare(strict_types=1);

$uri = urldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

if (preg_match('#^/api/(.*)$#', $uri, $m)) {
    $_GET['route'] = $m[1];
    require __DIR__ . '/../public/api.php';
    return true;
}

$file = __DIR__ . '/../public' . $uri;
if ($uri !== '/' && is_file($file)) {
    return false;
}
if (is_dir($file) && is_file(rtrim($file, '/') . '/index.html')) {
    if (!str_ends_with($uri, '/')) {
        header('Location: ' . $uri . '/');
        return true;
    }
    header('Content-Type: text/html; charset=utf-8');
    readfile(rtrim($file, '/') . '/index.html');
    return true;
}
http_response_code(404);
echo 'Not found';
return true;
