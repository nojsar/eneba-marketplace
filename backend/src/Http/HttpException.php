<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

/**
 * An error whose status code and message are meant for the client (404, 400, 401...).
 * index.php turns it into a JSON response; any other exception becomes a generic 500.
 */
final class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}
