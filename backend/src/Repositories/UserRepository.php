<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;

/** SQL for users and their login sessions. */
final class UserRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** Inserts a user and returns it. Throws a PDOException with SQLSTATE 23505 if the email is taken. */
    public function create(string $email, string $passwordHash): array
    {
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO users (email, password_hash) VALUES (:email, :hash) RETURNING id, email, created_at'
        );
        $stmt->execute(['email' => $email, 'hash' => $passwordHash]);

        return $stmt->fetch();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->pdo()->prepare('SELECT id, email, password_hash, created_at FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);

        return $stmt->fetch() ?: null;
    }

    public function createSession(int $userId, string $tokenHash, int $days): void
    {
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO sessions (token_hash, user_id, expires_at) VALUES (:hash, :user_id, now() + make_interval(days => :days))'
        );
        $stmt->execute(['hash' => $tokenHash, 'user_id' => $userId, 'days' => $days]);
    }

    /** The user a session belongs to, or null if there is no such session or it has expired. */
    public function findBySession(string $tokenHash): ?array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT u.id, u.email, u.created_at
               FROM sessions s
               JOIN users u ON u.id = s.user_id
              WHERE s.token_hash = :hash AND s.expires_at > now()'
        );
        $stmt->execute(['hash' => $tokenHash]);

        return $stmt->fetch() ?: null;
    }

    public function deleteSession(string $tokenHash): void
    {
        $this->db->pdo()->prepare('DELETE FROM sessions WHERE token_hash = :hash')->execute(['hash' => $tokenHash]);
    }
}
