<?php

declare(strict_types=1);

define('FEISHU_TREASURE', true);

require_once __DIR__ . '/../includes/db_support.php';
require_once __DIR__ . '/../includes/database_admin.php';

function fail(string $message): void
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fail($message);
    }
}

$host = getenv('TEST_DB_HOST') ?: '127.0.0.1';
$port = getenv('TEST_DB_PORT') ?: '5432';
$user = getenv('TEST_DB_USER') ?: 'geo_user';
$password = getenv('TEST_DB_PASSWORD') ?: 'geo_password';
$testDb = 'compat_admin_' . bin2hex(random_bytes(4));

$adminPdo = new PDO(
    "pgsql:host={$host};port={$port};dbname=postgres",
    $user,
    $password,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$adminPdo->exec("CREATE DATABASE {$testDb}");

try {
    $pdo = new PDO(
        "pgsql:host={$host};port={$port};dbname={$testDb}",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    // Simulate a legacy admins table created before role/status/last_login compatibility fields existed.
    $pdo->exec("
        CREATE TABLE admins (
            id BIGSERIAL PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            display_name VARCHAR(100) DEFAULT '',
            email VARCHAR(100) DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    putenv('DB_DRIVER=pgsql');
    putenv("DB_HOST={$host}");
    putenv("DB_PORT={$port}");
    putenv("DB_NAME={$testDb}");
    putenv("DB_USER={$user}");
    putenv("DB_PASSWORD={$password}");
    putenv('APP_SECRET_KEY=test-secret-key-for-compatibility');
    putenv('SITE_URL=http://localhost');
    putenv('TZ=Asia/Shanghai');

    $refClass = new ReflectionClass(DatabaseAdmin::class);
    $instanceProp = $refClass->getProperty('instance');
    $instanceProp->setAccessible(true);
    $instanceProp->setValue(null, null);

    DatabaseAdmin::getInstance();

    assertTrue(db_column_exists($pdo, 'admins', 'role'), 'expected admins.role to exist');
    assertTrue(db_column_exists($pdo, 'admins', 'status'), 'expected admins.status to exist');
    assertTrue(db_column_exists($pdo, 'admins', 'updated_at'), 'expected admins.updated_at to exist');
    assertTrue(db_column_exists($pdo, 'admins', 'last_login'), 'expected admins.last_login to exist');
} finally {
    if (isset($pdo)) {
        $pdo = null;
    }

    $adminPdo->exec("SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '{$testDb}'");
    $adminPdo->exec("DROP DATABASE IF EXISTS {$testDb}");
}

fwrite(STDOUT, "PASS\n");
