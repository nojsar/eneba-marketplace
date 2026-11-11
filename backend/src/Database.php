<?php

declare(strict_types=1);

namespace App;

use PDO;
use RuntimeException;

/**
 * One Postgres connection per request, opened only when a query actually needs it.
 *
 * Takes the single connection URL Neon gives you, e.g.
 * postgresql://user:password@ep-example.eu-central-1.aws.neon.tech/neondb?sslmode=require
 * and turns it into the DSN + username + password that PDO expects.
 */
final class Database
{
    private ?PDO $pdo = null;

    public function __construct(private readonly string $url)
    {
    }

    public function pdo(): PDO
    {
        return $this->pdo ??= $this->connect();
    }

    private function connect(): PDO
    {
        $parts = parse_url($this->url);
        if ($parts === false || !isset($parts['host'], $parts['path'])) {
            throw new RuntimeException('DATABASE_URL is not a valid Postgres URL');
        }
        parse_str($parts['query'] ?? '', $options);

        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
            $parts['host'],
            $parts['port'] ?? 5432,
            ltrim($parts['path'], '/'),
            $options['sslmode'] ?? 'prefer',
        );

        return new PDO($dsn, urldecode($parts['user'] ?? ''), urldecode($parts['pass'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,      // a failed query throws instead of returning false
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // rows come back as ['column' => value]
        ]);
    }
}
