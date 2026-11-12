<?php

declare(strict_types=1);

// Front controller: every API request enters here.
// In production Apache sends all URLs to this file (see docker/apache.conf);
// locally `composer serve` does the same with PHP's built-in server.

use App\Controllers\AuthController;
use App\Controllers\CheckoutController;
use App\Controllers\HealthController;
use App\Controllers\HomeController;
use App\Controllers\OrderController;
use App\Controllers\ProductController;
use App\Database;
use App\Env;
use App\Home\BestSellersBlock;
use App\Home\DealsBlock;
use App\Home\HeroBannersBlock;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Repositories\HomeBlockRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\CheckoutService;
use App\Services\StripeGateway;

require __DIR__ . '/../vendor/autoload.php';

Env::load(__DIR__ . '/../.env');

try {
    // Wiring: build every object once and hand each one what it needs.
    // Nothing connects to the database until a query actually runs.
    $db = new Database(Env::get('DATABASE_URL'));
    $auth = new AuthService(new UserRepository($db));
    $orders = new OrderRepository($db);
    // Payment settings are optional, so the store keeps working where Stripe is not configured.
    $stripe = new StripeGateway(Env::optional('STRIPE_SECRET_KEY'), Env::optional('STRIPE_WEBHOOK_SECRET'));
    $checkoutService = new CheckoutService($db, $orders, $stripe, Env::optional('APP_URL'));

    $products = new ProductController(new ProductRepository($db));
    $authController = new AuthController($auth);
    $checkout = new CheckoutController($checkoutService, $auth, $stripe);
    $orderController = new OrderController($orders, $checkoutService, $auth);
    $home = new HomeController(new HomeBlockRepository($db), [
        'hero_banners' => new HeroBannersBlock($db),
        'best_sellers' => new BestSellersBlock($db),
        'deals' => new DealsBlock($db),
    ]);

    $router = new Router();
    $router->get('/api/health', [new HealthController($db), 'show']);

    $router->get('/api/products', [$products, 'index']);
    $router->get('/api/products/{slug}', [$products, 'show']);
    $router->get('/api/search/suggestions', [$products, 'suggestions']);

    $router->get('/api/home', [$home, 'layout']);
    $router->get('/api/blocks/{name}', [$home, 'block']);

    $router->post('/api/auth/register', [$authController, 'register']);
    $router->post('/api/auth/login', [$authController, 'login']);
    $router->post('/api/auth/logout', [$authController, 'logout']);
    $router->get('/api/auth/me', [$authController, 'me']);

    $router->get('/api/cart', [$checkout, 'cart']);
    $router->post('/api/checkout', [$checkout, 'checkout']);
    $router->post('/api/stripe/webhook', [$checkout, 'webhook']);
    $router->get('/api/orders', [$orderController, 'index']);
    $router->get('/api/orders/{id}', [$orderController, 'show']);

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
