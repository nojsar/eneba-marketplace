<?php

declare(strict_types=1);

namespace App\Home;

use App\Controllers\ProductController;
use App\Database;

/** Products that sold the most keys (paid orders), counting only what is in stock now. */
final class BestSellersBlock implements BlockProvider
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
        $rows = $this->db->pdo()->query(
            'SELECT *
               FROM product_listing
              WHERE lowest_price_cents IS NOT NULL
              ORDER BY units_sold DESC, name, id
              LIMIT 8'
        )->fetchAll();

        return [
            'items' => array_map(ProductController::summary(...), $rows),
            'seeAllUrl' => '/store', // the store's default sort is best-selling
        ];
    }
}
