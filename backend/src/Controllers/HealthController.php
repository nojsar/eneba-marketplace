<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Http\Request;
use App\Http\Response;
use PDOException;

/**
 * GET /api/health — answers "is the API up, and can it reach the database?"
 * Used to check each deployment and to wake the server before a demo.
 */
final class HealthController
{
    public function __construct(private readonly Database $db)
    {
    }

    public function show(Request $request): Response
    {
        try {
            $this->db->pdo()->query('SELECT 1');
        } catch (PDOException $e) {
            error_log('Health check: database unreachable: ' . $e->getMessage());

            return Response::json(['api' => 'up', 'database' => 'down'], 503);
        }

        return Response::json(['api' => 'up', 'database' => 'up']);
    }
}
