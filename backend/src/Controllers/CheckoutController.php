<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuthService;
use App\Services\CheckoutService;
use App\Services\StripeGateway;

final class CheckoutController
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly AuthService $auth,
        private readonly StripeGateway $stripe,
    ) {
    }

    /**
     * GET /api/cart?items=12:1,15:2 — current prices, stock and total for the cart.
     * A GET because it only reads; no login needed, visitors can fill a cart too.
     */
    public function cart(Request $request): Response
    {
        $quote = $this->checkout->quote(self::itemsFromQuery($request->query['items'] ?? ''));

        return Response::json([
            'lines' => array_map(fn (array $line) => [
                'offerId' => $line['offer']['offer_id'],
                'quantity' => $line['quantity'],
                'unitPriceCents' => $line['offer']['price_cents'],
                'lineTotalCents' => $line['offer']['price_cents'] * $line['quantity'],
                'stock' => $line['offer']['stock'],
                'problem' => $line['problem'],
                'seller' => $line['offer']['seller_name'],
                'product' => [
                    'slug' => $line['offer']['slug'],
                    'name' => $line['offer']['name'],
                    'imageUrl' => $line['offer']['image_url'],
                    'platform' => $line['offer']['platform_name'],
                    'region' => $line['offer']['region_name'],
                ],
            ], $quote['lines']),
            'totalCents' => $quote['totalCents'],
            'canCheckout' => $quote['canCheckout'],
        ]);
    }

    /** POST /api/checkout {items: [{offerId, quantity}]} — signed-in users only. Returns Stripe's payment page URL. */
    public function checkout(Request $request): Response
    {
        $user = $this->auth->requireUser($request);
        $items = self::itemsFromJson($request->json()['items'] ?? null);
        if ($items === []) {
            throw new HttpException(400, 'Your cart is empty');
        }

        return Response::json(['url' => $this->checkout->checkout($user, $items)]);
    }

    /**
     * POST /api/stripe/webhook — Stripe reports that a checkout completed or expired.
     * Stripe calls Render directly (server to server). Any 2xx tells Stripe "received";
     * anything else makes it retry later, which is safe because fulfilment is idempotent.
     */
    public function webhook(Request $request): Response
    {
        $event = $this->stripe->parseWebhook($request->body, $request->headers['stripe-signature'] ?? '');
        $this->checkout->handleStripeEvent($event);

        return Response::json(['received' => true]);
    }

    /** "12:1,15:2" → [12 => 1, 15 => 2] */
    private static function itemsFromQuery(mixed $value): array
    {
        if (!is_string($value)) {
            throw new HttpException(400, 'items must look like 12:1,15:2');
        }

        $pairs = [];
        foreach (array_filter(explode(',', $value)) as $part) {
            $pairs[] = array_pad(explode(':', $part, 2), 2, null);
        }

        return self::validItems($pairs);
    }

    /** [{"offerId": 12, "quantity": 1}] → [12 => 1] */
    private static function itemsFromJson(mixed $value): array
    {
        if (!is_array($value)) {
            throw new HttpException(400, 'items must be a list');
        }

        $pairs = [];
        foreach ($value as $item) {
            $pairs[] = is_array($item) ? [$item['offerId'] ?? null, $item['quantity'] ?? null] : [null, null];
        }

        return self::validItems($pairs);
    }

    /** Every offer id must be a positive whole number and every quantity 1–10. */
    private static function validItems(array $pairs): array
    {
        if (count($pairs) > CheckoutService::MAX_LINES) {
            throw new HttpException(400, 'Too many different items in the cart');
        }

        $items = [];
        foreach ($pairs as [$offerId, $quantity]) {
            $offerId = filter_var($offerId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $quantity = filter_var($quantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => CheckoutService::MAX_QUANTITY]]);
            if ($offerId === false || $quantity === false) {
                throw new HttpException(400, 'Each cart item needs an offer id and a quantity from 1 to ' . CheckoutService::MAX_QUANTITY);
            }
            $items[$offerId] = $quantity;
        }

        return $items;
    }
}
