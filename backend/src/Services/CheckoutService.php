<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Http\HttpException;
use App\Repositories\OrderRepository;
use Throwable;

/**
 * Cart → order → payment → keys.
 *
 * The browser's cart only holds offer ids and quantities. Every price shown or
 * charged is looked up here, so editing the cart in the browser cannot change a price.
 */
final class CheckoutService
{
    public const MAX_QUANTITY = 10;
    public const MAX_LINES = 20;

    /** How long keys stay reserved while the buyer is on Stripe's page (Stripe's minimum session lifetime). */
    private const HOLD_MINUTES = 30;

    public function __construct(
        private readonly Database $db,
        private readonly OrderRepository $orders,
        private readonly StripeGateway $stripe,
        private readonly ?string $appUrl,
    ) {
    }

    /**
     * Current price and stock for each cart line, plus the total.
     *
     * @param array<int, int> $items offer id => quantity
     * @return array{lines: list<array{offer: array, quantity: int, problem: ?string}>, totalCents: int, canCheckout: bool}
     */
    public function quote(array $items): array
    {
        $offers = $this->orders->offersByIds(array_keys($items));
        $lines = [];
        $total = 0;

        foreach ($items as $offerId => $quantity) {
            $offer = $offers[$offerId] ?? null;
            if ($offer === null) {
                continue; // no such offer (any more): leave it out
            }

            $problem = match (true) {
                $offer['stock'] === 0 => 'Out of stock',
                $offer['stock'] < $quantity => "Only {$offer['stock']} left",
                default => null,
            };
            $lines[] = ['offer' => $offer, 'quantity' => $quantity, 'problem' => $problem];
            $total += $offer['price_cents'] * $quantity;
        }

        $hasProblem = array_filter($lines, fn (array $line) => $line['problem'] !== null) !== [];

        return ['lines' => $lines, 'totalCents' => $total, 'canCheckout' => $lines !== [] && !$hasProblem];
    }

    /**
     * Turns a cart into a pending order with reserved keys, then opens a Stripe Checkout
     * session for it. Returns the URL of Stripe's payment page.
     *
     * @param array<int, int> $items offer id => quantity
     */
    public function checkout(array $user, array $items): string
    {
        $appUrl = $this->appUrl ?? throw new HttpException(503, 'Payments are not configured (APP_URL)');
        $offers = $this->orders->offersByIds(array_keys($items));

        // 1. Order and reservations in ONE transaction: either every key is reserved, or none is.
        $orderId = $this->db->transaction(function () use ($user, $items, $offers): int {
            $total = 0;
            foreach ($items as $offerId => $quantity) {
                $offer = $offers[$offerId] ?? throw new HttpException(409, 'An item in your cart is no longer available');
                $total += $offer['price_cents'] * $quantity;
            }

            $orderId = $this->orders->createOrder($user['id'], $total);
            foreach ($items as $offerId => $quantity) {
                $offer = $offers[$offerId];
                $itemId = $this->orders->addItem($orderId, $offerId, $quantity, $offer['price_cents']);

                if ($this->orders->reserveKeys($itemId, $offerId, $quantity) < $quantity) {
                    // Throwing rolls back everything above, including keys reserved for earlier lines.
                    throw new HttpException(409, "Not enough keys left for {$offer['name']}. Please check your cart.");
                }
            }

            return $orderId;
        });

        // 2. Stripe, after the commit: never hold database locks while waiting on another service.
        try {
            $session = $this->stripe->createCheckoutSession(
                orderId: $orderId,
                email: $user['email'],
                lines: array_map(fn (int $offerId) => [
                    'name' => "{$offers[$offerId]['name']} ({$offers[$offerId]['platform_name']}, {$offers[$offerId]['region_name']}) · sold by {$offers[$offerId]['seller_name']}",
                    'imageUrl' => $offers[$offerId]['image_url'],
                    'unitPriceCents' => $offers[$offerId]['price_cents'],
                    'quantity' => $items[$offerId],
                ], array_keys($items)),
                successUrl: "$appUrl/orders/$orderId",
                cancelUrl: "$appUrl/cart",
                expiresAt: time() + self::HOLD_MINUTES * 60,
            );
        } catch (Throwable $e) {
            $this->release($orderId); // no payment page, so give the keys back straight away
            throw $e;
        }

        $this->orders->attachStripeSession($orderId, $session['id']);

        return $session['url'];
    }

    /**
     * Payment confirmed: the order becomes paid and its reserved keys sold. Safe to call
     * any number of times (Stripe retries webhooks, and the order page checks too):
     * only the call that actually moves the order out of "pending" touches the keys.
     */
    public function fulfil(int $orderId): void
    {
        $this->db->transaction(function () use ($orderId): void {
            if ($this->orders->markPaid($orderId)) {
                $this->orders->sellReservedKeys($orderId);
            }
        });
    }

    /** Payment never happened (checkout expired or failed): cancel the order and put its keys back on sale. Also safe to repeat. */
    public function release(int $orderId): void
    {
        $this->db->transaction(function () use ($orderId): void {
            if ($this->orders->markCancelled($orderId)) {
                $this->orders->releaseReservedKeys($orderId);
            }
        });
    }

    /**
     * For an order still pending, ask Stripe how its checkout ended. The webhook is the
     * main signal, but this keeps the order page right when the webhook is late or cannot
     * reach us (e.g. on a laptop during development).
     */
    public function syncWithStripe(array $order): void
    {
        if ($order['status'] !== 'pending' || $order['stripe_session_id'] === null) {
            return;
        }

        $session = $this->stripe->retrieveSession($order['stripe_session_id']);
        if ($session['status'] === 'complete' && $session['paid']) {
            $this->fulfil($order['id']);
        } elseif ($session['status'] === 'expired') {
            $this->release($order['id']);
        }
    }

    /** A verified Stripe event (see StripeGateway::parseWebhook). Other event types are ignored. */
    public function handleStripeEvent(array $event): void
    {
        if ($event['orderId'] === null) {
            return;
        }

        if ($event['type'] === 'checkout.session.completed' && $event['paid']) {
            $this->fulfil($event['orderId']);
        } elseif ($event['type'] === 'checkout.session.expired') {
            $this->release($event['orderId']);
        }
    }
}
