<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\OrderRepository;
use App\Services\AuthService;
use App\Services\CheckoutService;
use DateTimeImmutable;

/** A signed-in user's orders. Someone else's order answers 404, so its existence is not revealed. */
final class OrderController
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly CheckoutService $checkout,
        private readonly AuthService $auth,
    ) {
    }

    /** GET /api/orders — order history, newest first (keys are only on the order page). */
    public function index(Request $request): Response
    {
        $user = $this->auth->requireUser($request);

        return Response::json([
            'orders' => array_map(fn (array $order) => self::present($order, null), $this->orders->listForUser($user['id'])),
        ]);
    }

    /**
     * GET /api/orders/{id} — one order, with its keys once paid. If the order is still
     * pending, Stripe is asked first, so returning from payment shows the keys at once.
     * That makes this GET write in one case, but only "pending → paid/cancelled",
     * which is idempotent: repeating the request changes nothing.
     */
    public function show(Request $request): Response
    {
        $user = $this->auth->requireUser($request);
        $id = filter_var($request->params['id'], FILTER_VALIDATE_INT);
        $order = $id === false ? null : $this->orders->findForUser($id, $user['id']);
        if ($order === null) {
            throw new HttpException(404, 'Order not found');
        }

        if ($order['status'] === 'pending') {
            $this->checkout->syncWithStripe($order);
            $order = $this->orders->findForUser($id, $user['id']);
        }

        $keys = $order['status'] === 'paid' ? $this->orders->keysByItem($id) : null;

        return Response::json(self::present($order, $keys));
    }

    /** @param array<int, list<string>>|null $keys keys per order line, or null to leave keys out */
    private static function present(array $order, ?array $keys): array
    {
        return [
            'id' => $order['id'],
            'status' => $order['status'], // pending | paid | cancelled
            'totalCents' => $order['total_cents'],
            'createdAt' => (new DateTimeImmutable($order['created_at']))->format(DATE_ATOM),
            'items' => array_map(fn (array $item) => [
                'offerId' => $item['offer_id'],
                'quantity' => $item['quantity'],
                'unitPriceCents' => $item['unit_price_cents'],
                'seller' => $item['seller_name'],
                'product' => [
                    'slug' => $item['slug'],
                    'name' => $item['name'],
                    'imageUrl' => $item['image_url'],
                    'platform' => $item['platform_name'],
                    'region' => $item['region_name'],
                ],
            ] + ($keys === null ? [] : ['keys' => $keys[$item['item_id']] ?? []]), $order['items']),
        ];
    }
}
