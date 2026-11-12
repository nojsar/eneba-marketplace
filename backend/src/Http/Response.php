<?php

declare(strict_types=1);

namespace App\Http;

/**
 * A JSON response, optionally setting cookies. Controllers build one and return it;
 * only index.php calls send().
 */
final class Response
{
    /** @param list<array{name: string, value: string, maxAge: int}> $cookies */
    public function __construct(
        public readonly int $status,
        public readonly array $body,
        public readonly array $cookies = [],
    ) {
    }

    public static function json(array $body, int $status = 200): self
    {
        return new self($status, $body);
    }

    /** A copy of this response that also sets a cookie. A maxAge of 0 deletes the cookie. */
    public function withCookie(string $name, string $value, int $maxAge): self
    {
        return new self($this->status, $this->body, [...$this->cookies, ['name' => $name, 'value' => $value, 'maxAge' => $maxAge]]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');

        foreach ($this->cookies as $cookie) {
            setcookie($cookie['name'], $cookie['value'], [
                'expires' => $cookie['maxAge'] > 0 ? time() + $cookie['maxAge'] : 1, // 1 = 1970: the browser deletes it
                'path' => '/',
                'secure' => true,      // only sent over HTTPS (browsers make an exception for localhost)
                'httponly' => true,    // JavaScript cannot read it, so an XSS bug cannot steal the session
                'samesite' => 'Lax',   // not sent on requests that other websites start, except plain link clicks
            ]);
        }

        echo json_encode($this->body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
