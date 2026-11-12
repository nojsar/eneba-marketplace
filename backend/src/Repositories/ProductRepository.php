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
        // Best match first: how closely the search term matches part of the name (0–1).
        'relevance' => 'word_similarity(:rank, name) DESC, units_sold DESC, name, id',
        'bestselling' => 'units_sold DESC, name, id',
        'price_asc' => 'lowest_price_cents ASC NULLS LAST, name, id',
        'price_desc' => 'lowest_price_cents DESC NULLS LAST, name, id',
        'newest' => 'release_date DESC, name, id',
    ];

    public function __construct(private readonly Database $db)
    {
    }

    /** One page of products matching the filters. A smaller $limit serves the search suggestions. */
    public function list(ProductFilters $filters, int $limit = ProductFilters::PAGE_SIZE): array
    {
        [$where, $params] = $this->where($filters);
        if ($filters->sort === 'relevance') {
            $params['rank'] = $filters->search;
        }
        $params['limit'] = $limit;
        $params['offset'] = ($filters->page - 1) * $limit;

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

    /** One product with its description, or null if no product has this slug. */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT l.*, p.description
               FROM product_listing l
               JOIN products p ON p.id = l.id
              WHERE l.slug = :slug'
        );
        $stmt->execute(['slug' => $slug]);

        return $stmt->fetch() ?: null;
    }

    /** Every seller's offer for a product with its stock: offers with stock first, then cheapest first. */
    public function offers(int $productId): array
    {
        $stmt = $this->db->pdo()->prepare(
            "SELECT *
               FROM (SELECT o.id, o.price_cents, s.name AS seller_name, s.rating_percent, s.rating_count,
                            (SELECT count(*) FROM product_keys k WHERE k.offer_id = o.id AND k.status = 'available') AS stock
                       FROM offers o
                       JOIN sellers s ON s.id = o.seller_id
                      WHERE o.product_id = :product_id) offers
              ORDER BY stock = 0, price_cents, seller_name" // false sorts before true: sold-out offers go last
        );
        $stmt->execute(['product_id' => $productId]);

        return $stmt->fetchAll();
    }

    /** The same game on other platforms or in other regions, e.g. Elden Ring on PlayStation for the Steam page. */
    public function otherListings(array $product): array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT slug, platform_name, region_name, lowest_price_cents
               FROM product_listing
              WHERE name = :name AND id <> :id
              ORDER BY platform_name, region_name'
        );
        $stmt->execute(['name' => $product['name'], 'id' => $product['id']]);

        return $stmt->fetchAll();
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
            // Exact text first, typo matching only as a fallback (all can use the trigram index, see migration 002):
            //   ILIKE  the typed text appears in the name          "hogwart"  → Hogwarts Legacy
            // and only if NO product name contains the typed text:
            //   <%     a typo inside a longer name (word_similarity ≥ 0.6)   "witchr"  → The Witcher 3: Wild Hunt
            //   %      a typo across a short name  (similarity ≥ 0.3)        "palwrld" → Palworld
            // Without the fallback rule, "elden" would also match "The Elder Scrolls" (one letter apart).
            // % and _ typed by the user are escaped for ILIKE, so they match literally.
            $conditions[] = '(name ILIKE :search_like
                              OR (NOT EXISTS (SELECT 1 FROM products WHERE name ILIKE :search_like_any)
                                  AND (:search_word <% name OR :search_whole % name)))';
            $like = '%' . addcslashes($filters->search, '%_\\') . '%';
            $params['search_like'] = $like;
            $params['search_like_any'] = $like;
            $params['search_word'] = $filters->search;
            $params['search_whole'] = $filters->search;
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
