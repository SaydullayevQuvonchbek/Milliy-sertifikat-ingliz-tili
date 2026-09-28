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
    $configFile = APP_ROOT . '/config/config.example.php';
}
App\Config::load(require $configFile);

date_default_timezone_set((string) App\Config::get('timezone', 'Asia/Tashkent'));
mb_internal_encoding('UTF-8');
