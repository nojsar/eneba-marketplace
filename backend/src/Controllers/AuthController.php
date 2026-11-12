<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuthService;
use DateTimeImmutable;

/** Sign up, log in, log out, and "who am I?". The session travels in an httpOnly cookie. */
final class AuthController
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    /** POST /api/auth/register {email, password} — creates the account and logs it in. */
    public function register(Request $request): Response
    {
        [$email, $password] = $this->credentials($request);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
            throw new HttpException(400, 'Enter a valid email address');
        }
        if (strlen($password) < 8) {
            throw new HttpException(400, 'The password must be at least 8 characters');
        }
        if (strlen($password) > 72) {
            // bcrypt only looks at the first 72 bytes; refuse rather than silently ignore the rest.
            throw new HttpException(400, 'The password can be at most 72 characters');
        }

        $user = $this->auth->register($email, $password);

        return $this->loggedIn($user, 201);
    }

    /** POST /api/auth/login {email, password} */
    public function login(Request $request): Response
    {
        [$email, $password] = $this->credentials($request);

        $user = $this->auth->checkCredentials($email, $password);
        if ($user === null) {
            // One message for both cases, so the form does not reveal which emails have accounts.
            throw new HttpException(401, 'Wrong email or password');
        }

        return $this->loggedIn($user, 200);
    }

    /** POST /api/auth/logout — deletes the session in the database and the cookie in the browser. */
    public function logout(Request $request): Response
    {
        $this->auth->endSession($request);

        return Response::json(['user' => null])->withCookie(AuthService::COOKIE, '', 0);
    }

    /** GET /api/auth/me — the logged-in user, or null. Asking "who am I?" as a visitor is not an error. */
    public function me(Request $request): Response
    {
        $user = $this->auth->currentUser($request);

        return Response::json(['user' => $user === null ? null : self::publicUser($user)]);
    }

    /** A fresh session for every login, so an old or planted token is never reused. */
    private function loggedIn(array $user, int $status): Response
    {
        $token = $this->auth->startSession($user['id']);

        return Response::json(['user' => self::publicUser($user)], $status)
            ->withCookie(AuthService::COOKIE, $token, AuthService::SESSION_DAYS * 86400);
    }

    /** @return array{0: string, 1: string} email (trimmed, lower-case) and password */
    private function credentials(Request $request): array
    {
        $data = $request->json();
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        if (!is_string($email) || !is_string($password) || $email === '' || $password === '') {
            throw new HttpException(400, 'Email and password are required');
        }

        return [mb_strtolower(trim($email)), $password];
    }

    /** Only what the frontend needs: never the password hash. */
    private static function publicUser(array $user): array
    {
        return [
            'id' => $user['id'],
            'email' => $user['email'],
            // Postgres prints "2026-11-12 18:14:09.17+00"; ISO 8601 parses the same in every browser.
            'createdAt' => (new DateTimeImmutable($user['created_at']))->format(DATE_ATOM),
        ];
    }
}
