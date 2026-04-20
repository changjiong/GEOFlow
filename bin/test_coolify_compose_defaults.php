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

$expected = 'DB_HOST: "${DB_HOST:-geoflow-postgres}"';
$occurrences = substr_count($compose, $expected);

if ($occurrences !== 3) {
    fail("expected 3 geoflow-postgres DB_HOST defaults, got {$occurrences}");
}

if (str_contains($compose, 'DB_HOST: "${DB_HOST:-postgres}"')) {
    fail('found ambiguous postgres DB_HOST default');
}

foreach (['scheduler', 'worker'] as $service) {
    $pattern = '/^  ' . preg_quote($service, '/') . ":\n(?P<body>(?:^(?!  [a-z]).*\n?)*)/m";
    if (!preg_match($pattern, $compose, $matches)) {
        fail("unable to find {$service} service");
    }

    $serviceBlock = $matches[0];

    if (!str_contains($serviceBlock, "    networks:\n      - default")) {
        fail("expected {$service} to join the default network");
    }
}

fwrite(STDOUT, "PASS\n");
