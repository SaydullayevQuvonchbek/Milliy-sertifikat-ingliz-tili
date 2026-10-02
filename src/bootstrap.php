<?php

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$configFile = getenv('MOCK_CONFIG') ?: APP_ROOT . '/config/config.php';
if (!is_file($configFile)) {
    $testing = getenv('MOCK_TESTING') !== false;
    if (!$testing && isset($_SERVER['REQUEST_METHOD']) && !in_array(PHP_SAPI, ['cli', 'cli-server'], true)) {
        // Serverda sozlama fayli bo'lmasa, namunaviy sozlama (SQLite) bilan indamay ishlab ketmaymiz: aks holda baza
        // kutilmagan joyda yaratiladi va xatoning sababi ko'rinmaydi.
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['error' => [
            'code' => 'config_missing',
            'message' => "Sozlama fayli topilmadi: config/config.php. Uni config/config.example.php dan nusxa olib yarating.",
        ]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    if (!$testing && defined('STDERR')) {
        fwrite(STDERR, "Diqqat: config/config.php topilmadi — namunaviy sozlama ishlatilmoqda (config.example.php: SQLite, storage/database.sqlite).\n");
    }
    $configFile = APP_ROOT . '/config/config.example.php';
}
App\Config::load(require $configFile);

date_default_timezone_set((string) App\Config::get('timezone', 'Asia/Tashkent'));
mb_internal_encoding('UTF-8');
