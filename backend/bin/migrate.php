<?php

declare(strict_types=1);

// Applies every db/migrations/*.sql file that has not run yet, in filename order.
// Usage: php bin/migrate.php   (runs on every container start, see docker/start.sh)

use App\Database;
use App\Env;

require __DIR__ . '/../vendor/autoload.php';

Env::load(__DIR__ . '/../.env');
$pdo = (new Database(Env::get('DATABASE_URL')))->pdo();

// Remembers which files have already been applied.
$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
    version    text PRIMARY KEY,
    applied_at timestamptz NOT NULL DEFAULT now()
)');
$applied = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);

$files = glob(__DIR__ . '/../db/migrations/*.sql');
sort($files);

foreach ($files as $file) {
    $version = basename($file, '.sql');
    if (in_array($version, $applied, true)) {
        continue;
    }

    // Postgres can roll back schema changes too, so a failing migration
    // leaves the database exactly as it was, never half-changed.
    $pdo->beginTransaction();
    try {
        $pdo->exec(file_get_contents($file));
        $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute([$version]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        fwrite(STDERR, "Migration $version failed: {$e->getMessage()}\n");
        exit(1);
    }

    echo "Applied $version\n";
}

echo "Database schema is up to date\n";
