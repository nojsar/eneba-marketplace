<?php

declare(strict_types=1);

namespace App\Http;

/**
 * A JSON response. Controllers build one and return it; only index.php calls send().
 */
final class Response
{
    public function __construct(
        public readonly int $status,
        public readonly array $body,
    ) {
    }

    public static function json(array $body, int $status = 200): self
    {
        return new self($status, $body);
    }

    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
