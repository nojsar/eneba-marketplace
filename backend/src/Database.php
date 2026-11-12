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

    /**
     * Runs $work inside a transaction: everything it wrote is committed if it returns,
     * and undone if it throws. Returns whatever $work returns.
     */
    public function transaction(callable $work): mixed
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $result = $work();
            $pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
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

        $pdo = new PDO($dsn, urldecode($parts['user'] ?? ''), urldecode($parts['pass'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,      // a failed query throws instead of returning false
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // rows come back as ['column' => value]
        ]);

        // Send each query and its parameters together instead of creating a named prepared
        // statement first. Neon's pooler (PgBouncer) renames named statements, so PDO's
        // clean-up "DEALLOCATE pdo_stmt_…" fails and silently aborts any open transaction:
        // COMMIT then quietly rolls back. Values are still sent separately from the SQL,
        // so this is just as safe against SQL injection. See docs/DECISIONS.md #12.
        $pdo->setAttribute(PDO::PGSQL_ATTR_DISABLE_PREPARES, true);

        return $pdo;
    }
}
