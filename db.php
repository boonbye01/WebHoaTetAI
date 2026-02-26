<?php
require_once __DIR__ . '/config.php';

function db() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = "mysql:host={$GLOBALS['db_host']};dbname={$GLOBALS['db_name']};charset={$GLOBALS['db_charset']}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    try {
        $pdo = new PDO($dsn, $GLOBALS['db_user'], $GLOBALS['db_pass'], $options);
    } catch (PDOException $e) {
        http_response_code(500);
        echo 'Kết nối cơ sở dữ liệu thất bại.';
        exit;
    }

    return $pdo;
}
