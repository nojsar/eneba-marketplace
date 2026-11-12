<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;

/** SQL for carts, orders and key reservation. */
final class OrderRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** Offers with what the cart shows and how many keys each has available, keyed by offer id. */
    public function offersByIds(array $offerIds): array
    {
        if ($offerIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($offerIds), '?'));
        $stmt = $this->db->pdo()->prepare(
            "SELECT o.id AS offer_id, o.price_cents, s.name AS seller_name,
                    p.slug, p.name, p.image_url, pl.name AS platform_name, r.name AS region_name,
                    (SELECT count(*) FROM product_keys k WHERE k.offer_id = o.id AND k.status = 'available') AS stock
               FROM offers o
               JOIN sellers s ON s.id = o.seller_id
               JOIN products p ON p.id = o.product_id
               JOIN platforms pl ON pl.slug = p.platform
               JOIN regions r ON r.slug = p.region
              WHERE o.id IN ($placeholders)"
        );
        $stmt->execute(array_values($offerIds));

        $offers = [];
        foreach ($stmt->fetchAll() as $row) {
            $offers[$row['offer_id']] = $row;
        }

        return $offers;
    }

    public function createOrder(int $userId, int $totalCents): int
    {
        $stmt = $this->db->pdo()->prepare(
            "INSERT INTO orders (user_id, status, total_cents) VALUES (:user_id, 'pending', :total) RETURNING id"
        );
        $stmt->execute(['user_id' => $userId, 'total' => $totalCents]);

        return $stmt->fetchColumn();
    }

    public function addItem(int $orderId, int $offerId, int $quantity, int $unitPriceCents): int
    {
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO order_items (order_id, offer_id, quantity, unit_price_cents)
             VALUES (:order_id, :offer_id, :quantity, :price) RETURNING id'
        );
        $stmt->execute(['order_id' => $orderId, 'offer_id' => $offerId, 'quantity' => $quantity, 'price' => $unitPriceCents]);

        return $stmt->fetchColumn();
    }

    /**
     * Reserves up to $quantity available keys for an order line; returns how many it got.
     *
     * FOR UPDATE locks the chosen keys until the transaction ends. SKIP LOCKED makes a
     * checkout running at the same moment skip keys another checkout is reserving instead
     * of waiting for them. So one key can never end up in two orders, and when only one
     * key is left, exactly one of two buyers gets it.
     */
    public function reserveKeys(int $orderItemId, int $offerId, int $quantity): int
    {
        $stmt = $this->db->pdo()->prepare(
            "UPDATE product_keys SET status = 'reserved', order_item_id = :item_id
              WHERE id IN (SELECT id FROM product_keys
                            WHERE offer_id = :offer_id AND status = 'available'
                            ORDER BY id
                            LIMIT :quantity
                            FOR UPDATE SKIP LOCKED)"
        );
        $stmt->execute(['item_id' => $orderItemId, 'offer_id' => $offerId, 'quantity' => $quantity]);

        return $stmt->rowCount();
    }

    public function attachStripeSession(int $orderId, string $sessionId): void
    {
        $this->db->pdo()
            ->prepare('UPDATE orders SET stripe_session_id = :session_id WHERE id = :id')
            ->execute(['session_id' => $sessionId, 'id' => $orderId]);
    }

    /**
     * pending → paid. Returns false if the order was not pending (already paid or cancelled),
     * which makes fulfilment safe to run twice: only the first call gets `true`.
     * If two calls race, the second waits for the first's row lock, then finds the order no
     * longer pending.
     */
    public function markPaid(int $orderId): bool
    {
        $stmt = $this->db->pdo()->prepare(
            "UPDATE orders SET status = 'paid', paid_at = now() WHERE id = :id AND status = 'pending'"
        );
        $stmt->execute(['id' => $orderId]);

        return $stmt->rowCount() === 1;
    }

    /** pending → cancelled, with the same "only once" guarantee as markPaid(). */
    public function markCancelled(int $orderId): bool
    {
        $stmt = $this->db->pdo()->prepare(
            "UPDATE orders SET status = 'cancelled' WHERE id = :id AND status = 'pending'"
        );
        $stmt->execute(['id' => $orderId]);

        return $stmt->rowCount() === 1;
    }

    public function sellReservedKeys(int $orderId): void
    {
        $this->db->pdo()->prepare(
            "UPDATE product_keys SET status = 'sold'
              WHERE status = 'reserved'
                AND order_item_id IN (SELECT id FROM order_items WHERE order_id = :order_id)"
        )->execute(['order_id' => $orderId]);
    }

    /** Puts an unpaid order's keys back on sale. */
    public function releaseReservedKeys(int $orderId): void
    {
        $this->db->pdo()->prepare(
            "UPDATE product_keys SET status = 'available', order_item_id = NULL
              WHERE status = 'reserved'
                AND order_item_id IN (SELECT id FROM order_items WHERE order_id = :order_id)"
        )->execute(['order_id' => $orderId]);
    }

    /** All of a user's orders with their lines, newest first. */
    public function listForUser(int $userId): array
    {
        return $this->ordersWithLines('o.user_id = :user_id', ['user_id' => $userId]);
    }

    /** One order with its lines, or null if it does not exist or belongs to someone else. */
    public function findForUser(int $orderId, int $userId): ?array
    {
        return $this->ordersWithLines('o.id = :id AND o.user_id = :user_id', ['id' => $orderId, 'user_id' => $userId])[0] ?? null;
    }

    /** The sold keys of an order, grouped by order line: [order_item_id => ['KEY-1', ...]]. */
    public function keysByItem(int $orderId): array
    {
        $stmt = $this->db->pdo()->prepare(
            "SELECT k.order_item_id, k.code
               FROM product_keys k
               JOIN order_items oi ON oi.id = k.order_item_id
              WHERE oi.order_id = :order_id AND k.status = 'sold'
              ORDER BY k.id"
        );
        $stmt->execute(['order_id' => $orderId]);

        $keys = [];
        foreach ($stmt->fetchAll() as $row) {
            $keys[$row['order_item_id']][] = $row['code'];
        }

        return $keys;
    }

    /** One query for orders and their lines; rows are then grouped into orders in PHP. */
    private function ordersWithLines(string $where, array $params): array
    {
        $stmt = $this->db->pdo()->prepare(
            "SELECT o.id, o.status, o.total_cents, o.stripe_session_id, o.created_at, o.paid_at,
                    oi.id AS item_id, oi.offer_id, oi.quantity, oi.unit_price_cents,
                    p.slug, p.name, p.image_url, pl.name AS platform_name, r.name AS region_name, s.name AS seller_name
               FROM orders o
               JOIN order_items oi ON oi.order_id = o.id
               JOIN offers f ON f.id = oi.offer_id
               JOIN products p ON p.id = f.product_id
               JOIN platforms pl ON pl.slug = p.platform
               JOIN regions r ON r.slug = p.region
               JOIN sellers s ON s.id = f.seller_id
              WHERE $where
              ORDER BY o.created_at DESC, oi.id"
        );
        $stmt->execute($params);

        $orders = [];
        foreach ($stmt->fetchAll() as $row) {
            $orders[$row['id']] ??= [
                'id' => $row['id'],
                'status' => $row['status'],
                'total_cents' => $row['total_cents'],
                'stripe_session_id' => $row['stripe_session_id'],
                'created_at' => $row['created_at'],
                'paid_at' => $row['paid_at'],
                'items' => [],
            ];
            $orders[$row['id']]['items'][] = $row;
        }

        return array_values($orders);
    }
}
