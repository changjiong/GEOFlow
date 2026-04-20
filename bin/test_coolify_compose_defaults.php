<?php

declare(strict_types=1);

function fail(string $message): void
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

$composePath = __DIR__ . '/../docker-compose.yaml';
$compose = file_get_contents($composePath);

if ($compose === false) {
    fail("unable to read {$composePath}");
}

$expected = 'DB_HOST: "${DB_HOST:-postgres}"';
$occurrences = substr_count($compose, $expected);

if ($occurrences !== 3) {
    fail("expected 3 postgres DB_HOST defaults, got {$occurrences}");
}

if (str_contains($compose, 'DB_HOST: "${DB_HOST:-geoflow-postgres}"')) {
    fail('found legacy geoflow-postgres DB_HOST default');
}

fwrite(STDOUT, "PASS\n");
