<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\HttpException;
use App\Http\Request;
use App\Repositories\UserRepository;
use PDOException;

/**
 * Accounts and sessions: password hashing, session tokens, and "who is asking?".
 * Controllers call requireUser() to protect an endpoint.
 */
final class AuthService
{
    public const COOKIE = 'session';
    public const SESSION_DAYS = 30;

    /**
     * A real bcrypt hash of a random password nobody knows. When a login names an unknown
     * email, the password is checked against this instead, so the answer takes as long as
     * for a wrong password and the response time does not reveal which emails have accounts.
     */
    private const DUMMY_HASH = '$2y$10$csmvCHqPV3aP3OZWakMTZuc71MxX3mGRqLER/LtYqGAlH.5RHoCIq';

    public function __construct(private readonly UserRepository $users)
    {
    }

    /** Creates an account. password_hash() picks the algorithm (bcrypt) and a random salt. */
    public function register(string $email, string $password): array
    {
        try {
            return $this->users->create($email, password_hash($password, PASSWORD_DEFAULT));
        } catch (PDOException $e) {
            // 23505 = unique_violation: the database, not a check-then-insert, guarantees one account per email,
            // so two sign-ups racing with the same email cannot both succeed.
            if ($e->getCode() === '23505') {
                throw new HttpException(409, 'An account with this email already exists');
            }
            throw $e;
        }
    }

    /** The user if the email and password match, otherwise null. */
    public function checkCredentials(string $email, string $password): ?array
    {
        $user = $this->users->findByEmail($email);
        $valid = password_verify($password, $user['password_hash'] ?? self::DUMMY_HASH);

        return $user !== null && $valid ? $user : null;
    }

    /**
     * Starts a session and returns the token for the cookie. The token is 32 random bytes;
     * only its SHA-256 hash is stored, so the sessions table alone cannot log anyone in.
     */
    public function startSession(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $this->users->createSession($userId, hash('sha256', $token), self::SESSION_DAYS);

        return $token;
    }

    /** The logged-in user, or null for visitors and expired or unknown sessions. */
    public function currentUser(Request $request): ?array
    {
        $token = $request->cookies[self::COOKIE] ?? null;
        if (!is_string($token) || $token === '') {
            return null;
        }

        return $this->users->findBySession(hash('sha256', $token));
    }

    /** For endpoints only signed-in users may use: the user, or a 401 error. */
    public function requireUser(Request $request): array
    {
        return $this->currentUser($request) ?? throw new HttpException(401, 'Please log in first');
    }

    public function endSession(Request $request): void
    {
        $token = $request->cookies[self::COOKIE] ?? null;
        if (is_string($token) && $token !== '') {
            $this->users->deleteSession(hash('sha256', $token));
        }
    }
}
