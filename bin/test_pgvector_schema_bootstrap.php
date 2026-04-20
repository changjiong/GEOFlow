<?php

declare(strict_types=1);

define('FEISHU_TREASURE', true);

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
$testDb = 'pgvector_bootstrap_' . bin2hex(random_bytes(4));
$logFile = sys_get_temp_dir() . '/geoflow-pgvector-bootstrap-' . bin2hex(random_bytes(4)) . '.log';

ini_set('log_errors', '1');
ini_set('error_log', $logFile);

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
    putenv('DB_DRIVER=pgsql');
    putenv("DB_HOST={$host}");
    putenv("DB_PORT={$port}");
    putenv("DB_NAME={$testDb}");
    putenv("DB_USER={$user}");
    putenv("DB_PASSWORD={$password}");
    putenv('APP_SECRET_KEY=test-secret-key-for-pgvector-bootstrap');
    putenv('SITE_URL=http://localhost');
    putenv('TZ=Asia/Shanghai');

    require_once __DIR__ . '/../includes/db_support.php';
    require_once __DIR__ . '/../includes/database_admin.php';

    $pdo = new PDO(
        "pgsql:host={$host};port={$port};dbname={$testDb}",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $refClass = new ReflectionClass(DatabaseAdmin::class);
    $instanceProp = $refClass->getProperty('instance');
    $instanceProp->setAccessible(true);
    $instanceProp->setValue(null, null);

    DatabaseAdmin::getInstance();

    assertTrue(db_column_exists($pdo, 'knowledge_chunks', 'embedding_vector'), 'expected knowledge_chunks.embedding_vector to exist');

    $logContent = file_exists($logFile) ? (string) file_get_contents($logFile) : '';
    assertTrue(!str_contains($logContent, 'pgvector 向量列或索引初始化失败'), 'expected pgvector bootstrap to avoid index initialization errors');
    assertTrue(!str_contains($logContent, 'column cannot have more than 2000 dimensions for hnsw index'), 'expected no HNSW dimension error');
} finally {
    if (isset($pdo)) {
        $pdo = null;
    }

    if (file_exists($logFile)) {
        unlink($logFile);
    }

    $adminPdo->exec("SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '{$testDb}'");
    $adminPdo->exec("DROP DATABASE IF EXISTS {$testDb}");
}

fwrite(STDOUT, "PASS\n");
