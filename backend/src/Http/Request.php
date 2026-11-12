<?php

declare(strict_types=1);

namespace App\Http;

/**
 * The parts of an HTTP request the API uses, copied out of PHP's globals once
 * so the rest of the code never touches $_SERVER / $_GET / $_COOKIE directly.
 */
final class Request
{
    /**
     * @param array<string, mixed>  $query   query string, e.g. ?page=2 → ['page' => '2']
     * @param array<string, string> $params  values captured by the route, e.g. {slug}
     * @param array<string, mixed>  $cookies cookies the browser sent
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $params = [],
        public readonly array $cookies = [],
        public readonly string $body = '',
        public readonly string $contentType = '',
    ) {
    }

    public static function fromGlobals(): self
    {
        return new self(
            method: $_SERVER['REQUEST_METHOD'] ?? 'GET',
            path: parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/',
            query: $_GET,
            cookies: $_COOKIE,
            body: file_get_contents('php://input') ?: '',
            contentType: $_SERVER['CONTENT_TYPE'] ?? '',
        );
    }

    /** Requests are immutable, so the router hands the controller a copy with the route params filled in. */
    public function withParams(array $params): self
    {
        return new self($this->method, $this->path, $this->query, $params, $this->cookies, $this->body, $this->contentType);
    }

    /**
     * The body as a decoded JSON object.
     *
     * Only application/json is accepted. A form on another website can only send form
     * encodings or text/plain; to send JSON it would need the browser to ask our API
     * for permission first (a CORS preflight), which we never grant. Together with the
     * SameSite cookie this keeps other sites from posting as a logged-in user (CSRF).
     */
    public function json(): array
    {
        if (!str_starts_with(strtolower($this->contentType), 'application/json')) {
            throw new HttpException(415, 'Send the request body as application/json');
        }

        $data = json_decode($this->body, true);
        if (!is_array($data)) {
            throw new HttpException(400, 'The request body must be a JSON object');
        }

        return $data;
    }
}
