<?php

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$file = realpath(
    __DIR__ . rawurldecode(is_string($path) ? $path : '/')
);

if (
    $file !== false
    && str_starts_with($file, __DIR__ . DIRECTORY_SEPARATOR)
    && is_file($file)
    && strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php'
) {
    return false;
}