<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;

/** The homepage layout stored in home_blocks. */
final class HomeBlockRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** Active blocks in display order. */
    public function active(): array
    {
        return $this->db->pdo()->query(
            'SELECT id, provider, title FROM home_blocks WHERE is_active ORDER BY position'
        )->fetchAll();
    }
}
