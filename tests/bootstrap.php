<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'KBMS\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $path = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_readable($path)) {
        require_once $path;
    }
});

