<?php

declare(strict_types=1);

// Front controller: every API request enters here.
// In production Apache sends all URLs to this file (see docker/apache.conf);
// locally `composer serve` does the same with PHP's built-in server.

use App\Controllers\HealthController;
use App\Controllers\ProductController;
use App\Database;
use App\Env;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Repositories\ProductRepository;

require __DIR__ . '/../vendor/autoload.php';

Env::load(__DIR__ . '/../.env');

try {
    $db = new Database(Env::get('DATABASE_URL'));

    $products = new ProductController(new ProductRepository($db));

    $router = new Router();
    $router->get('/api/health', [new HealthController($db), 'show']);
    $router->get('/api/products', [$products, 'index']);
    $router->get('/api/products/{slug}', [$products, 'show']);
    $router->get('/api/search/suggestions', [$products, 'suggestions']);

    $response = $router->dispatch(Request::fromGlobals());
} catch (HttpException $e) {
    // Expected errors (404, 400, ...): the message is safe to show the client.
    $response = Response::json(['error' => $e->getMessage()], $e->status);
} catch (Throwable $e) {
    // Unexpected errors: full details go to the server log, never to the client.
    error_log((string) $e);
    $response = Response::json(['error' => 'Internal server error'], 500);
}

$response->send();
