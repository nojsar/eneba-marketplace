<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\ProductFilters;
use App\Repositories\ProductRepository;

final class ProductController
{
    public function __construct(private readonly ProductRepository $products)
    {
    }

    /** GET /api/products — the store: one page of products plus the filter options with counts. */
    public function index(Request $request): Response
    {
        $filters = ProductFilters::fromQuery($request->query);
        $total = $this->products->count($filters);

        return Response::json([
            'items' => array_map(self::summary(...), $this->products->list($filters)),
            'total' => $total,
            'page' => $filters->page,
            'pageSize' => ProductFilters::PAGE_SIZE,
            'pageCount' => (int) ceil($total / ProductFilters::PAGE_SIZE),
            'facets' => [
                'platforms' => $this->products->platformCounts($filters),
                'regions' => $this->products->regionCounts($filters),
            ],
        ]);
    }

    /**
     * A product_listing row in the shape the frontend uses. Field names are chosen
     * here on purpose, so renaming a database column cannot silently change the API.
     */
    public static function summary(array $row): array
    {
        $list = $row['list_price_cents'];
        $lowest = $row['lowest_price_cents']; // null when no seller has stock

        return [
            'id' => $row['id'],
            'slug' => $row['slug'],
            'name' => $row['name'],
            'imageUrl' => $row['image_url'],
            'platform' => ['slug' => $row['platform'], 'name' => $row['platform_name']],
            'region' => ['slug' => $row['region'], 'name' => $row['region_name']],
            'listPriceCents' => $list,
            'lowestPriceCents' => $lowest,
            'discountPercent' => $lowest !== null && $lowest < $list ? (int) round(100 * ($list - $lowest) / $list) : null,
            'offerCount' => $row['offer_count'],
        ];
    }
}
