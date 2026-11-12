<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;

/**
 * All SQL for browsing the catalogue. Reads from the product_listing view
 * (see db/migrations/001), which already joins prices, stock and sales.
 */
final class ProductRepository
{
    /** Sort keys map to fixed SQL; user input never reaches the ORDER BY. `id` makes the order stable across pages. */
    private const ORDER_BY = [
        'bestselling' => 'units_sold DESC, name, id',
        'price_asc' => 'lowest_price_cents ASC NULLS LAST, name, id',
        'price_desc' => 'lowest_price_cents DESC NULLS LAST, name, id',
        'newest' => 'release_date DESC, name, id',
    ];

    public function __construct(private readonly Database $db)
    {
    }

    /** One page of products matching the filters. */
    public function list(ProductFilters $filters): array
    {
        [$where, $params] = $this->where($filters);
        $params['limit'] = ProductFilters::PAGE_SIZE;
        $params['offset'] = ($filters->page - 1) * ProductFilters::PAGE_SIZE;

        $stmt = $this->db->pdo()->prepare(
            "SELECT * FROM product_listing
              WHERE $where
              ORDER BY " . self::ORDER_BY[$filters->sort] . '
              LIMIT :limit OFFSET :offset'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** How many products match in total (for "48 results" and the page count). */
    public function count(ProductFilters $filters): int
    {
        [$where, $params] = $this->where($filters);
        $stmt = $this->db->pdo()->prepare("SELECT count(*) FROM product_listing WHERE $where");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** Every platform with the number of matching products, e.g. Steam (31). */
    public function platformCounts(ProductFilters $filters): array
    {
        return $this->counts('platforms', 'platform', $filters);
    }

    /** Every region with the number of matching products. */
    public function regionCounts(ProductFilters $filters): array
    {
        return $this->counts('regions', 'region', $filters);
    }

    /**
     * Counts for one filter group apply every filter EXCEPT that group's own.
     * Otherwise ticking "Steam" would make every other platform show 0, and the
     * buyer could not see what adding "GOG" to the selection would give them.
     */
    private function counts(string $table, string $column, ProductFilters $filters): array
    {
        [$where, $params] = $this->where($filters, ignore: $column);

        // $table and $column come from the two methods above, never from the request.
        $stmt = $this->db->pdo()->prepare(
            "SELECT t.slug, t.name, coalesce(c.count, 0) AS count
               FROM $table t
               LEFT JOIN (
                    SELECT $column AS slug, count(*) AS count
                      FROM product_listing
                     WHERE $where
                     GROUP BY $column
               ) c ON c.slug = t.slug
              ORDER BY t.name"
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Builds the WHERE clause and its parameters from the filters.
     * Values always travel as bound parameters, never pasted into the SQL.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function where(ProductFilters $filters, ?string $ignore = null): array
    {
        $conditions = ['true'];
        $params = [];

        if ($filters->search !== null) {
            // Partial match anywhere in the name. % and _ typed by the user are escaped, so they match literally.
            $conditions[] = 'name ILIKE :search';
            $params['search'] = '%' . addcslashes($filters->search, '%_\\') . '%';
        }
        if ($filters->platforms !== [] && $ignore !== 'platform') {
            $conditions[] = 'platform IN (' . $this->placeholders('platform', $filters->platforms, $params) . ')';
        }
        if ($filters->regions !== [] && $ignore !== 'region') {
            $conditions[] = 'region IN (' . $this->placeholders('region', $filters->regions, $params) . ')';
        }
        // Out-of-stock products have no price (NULL), so a price filter leaves them out.
        if ($filters->minPriceCents !== null) {
            $conditions[] = 'lowest_price_cents >= :min_price';
            $params['min_price'] = $filters->minPriceCents;
        }
        if ($filters->maxPriceCents !== null) {
            $conditions[] = 'lowest_price_cents <= :max_price';
            $params['max_price'] = $filters->maxPriceCents;
        }

        return [implode(' AND ', $conditions), $params];
    }

    /** ['steam', 'gog'] → ":platform0, :platform1", adding both values to $params. PDO cannot bind a whole list. */
    private function placeholders(string $name, array $values, array &$params): string
    {
        $names = [];
        foreach (array_values($values) as $i => $value) {
            $params[$name . $i] = $value;
            $names[] = ':' . $name . $i;
        }

        return implode(', ', $names);
    }
}
