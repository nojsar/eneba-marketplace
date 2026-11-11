<?php

declare(strict_types=1);

namespace App\Http;

/**
 * The parts of an HTTP request the API uses, copied out of PHP's globals once
 * so the rest of the code never touches $_SERVER / $_GET directly.
 */
final class Request
{
    /**
     * @param array<string, mixed>  $query  query string, e.g. ?page=2 → ['page' => '2']
     * @param array<string, string> $params values captured by the route, e.g. {slug}
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $params = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        return new self(
            method: $_SERVER['REQUEST_METHOD'] ?? 'GET',
            path: parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/',
            query: $_GET,
        );
    }

    /** Requests are immutable, so the router hands the controller a copy with the route params filled in. */
    public function withParams(array $params): self
    {
        return new self($this->method, $this->path, $this->query, $params);
    }
}
