<?php

declare(strict_types=1);

namespace App\Home;

use App\Controllers\ProductController;
use App\Database;

/** The biggest discounts: cheapest in-stock price compared with the publisher's price. */
final class DealsBlock implements BlockProvider
{
    public function __construct(private readonly Database $db)
    {
    }

    public function component(): string
    {
        return 'product_rail';
    }

    public function content(): array
    {
        // price / list price: 0.40 means 60 % off, so the smallest ratio is the biggest deal.
        // ::numeric avoids integer division, which would round every ratio down to 0.
        $rows = $this->db->pdo()->query(
            'SELECT *
               FROM product_listing
              WHERE lowest_price_cents IS NOT NULL
              ORDER BY lowest_price_cents::numeric / list_price_cents, name, id
              LIMIT 8'
        )->fetchAll();

        return [
            'items' => array_map(ProductController::summary(...), $rows),
            'seeAllUrl' => null, // the store has no "biggest discount" sort
        ];
    }
}
