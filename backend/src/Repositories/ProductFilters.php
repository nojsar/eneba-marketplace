<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Http\HttpException;

/**
 * What the store is asking for: search text, filters, sort and page.
 * Built from the query string and validated once, so the repository can trust it.
 *
 * Example: ?q=witcher&platform=steam,gog&region=global&min_price=10&max_price=30&sort=price_asc&page=2
 */
final class ProductFilters
{
    public const SORTS = ['bestselling', 'price_asc', 'price_desc', 'newest'];
    public const PAGE_SIZE = 24;

    /**
     * @param list<string> $platforms platform slugs; empty means "any platform"
     * @param list<string> $regions   region slugs; empty means "any region"
     */
    public function __construct(
        public readonly ?string $search = null,
        public readonly array $platforms = [],
        public readonly array $regions = [],
        public readonly ?int $minPriceCents = null,
        public readonly ?int $maxPriceCents = null,
        public readonly string $sort = 'bestselling',
        public readonly int $page = 1,
    ) {
    }

    public static function fromQuery(array $query): self
    {
        // Not `?:` for defaults: PHP treats the string "0" as false, so "?page=0" would quietly become page 1.
        $sort = self::string($query, 'sort');
        $sort = $sort === '' ? 'bestselling' : $sort;
        if (!in_array($sort, self::SORTS, true)) {
            throw new HttpException(400, 'sort must be one of: ' . implode(', ', self::SORTS));
        }

        $min = self::euros($query, 'min_price');
        $max = self::euros($query, 'max_price');
        if ($min !== null && $max !== null && $min > $max) {
            throw new HttpException(400, 'min_price cannot be higher than max_price');
        }

        $page = self::string($query, 'page');
        $page = $page === '' ? '1' : $page;
        if (!ctype_digit($page) || (int) $page < 1) {
            throw new HttpException(400, 'page must be a whole number from 1');
        }

        $search = trim(self::string($query, 'q'));

        return new self(
            search: $search === '' ? null : mb_substr($search, 0, 100),
            platforms: self::list($query, 'platform'),
            regions: self::list($query, 'region'),
            minPriceCents: $min,
            maxPriceCents: $max,
            sort: $sort,
            page: (int) $page,
        );
    }

    /** A query value as a string. "?platform[]=x" would make PHP give us an array, which we reject. */
    private static function string(array $query, string $key): string
    {
        $value = $query[$key] ?? '';
        if (!is_string($value)) {
            throw new HttpException(400, "$key must be a single value");
        }

        return $value;
    }

    /** "steam,gog" → ['steam', 'gog'] */
    private static function list(array $query, string $key): array
    {
        $items = array_map('trim', explode(',', self::string($query, $key)));

        return array_values(array_filter($items, fn (string $item) => $item !== ''));
    }

    /** A price in euros ("10" or "9.99") converted to cents, or null if not given. */
    private static function euros(array $query, string $key): ?int
    {
        $value = self::string($query, $key);
        if ($value === '') {
            return null;
        }
        if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $value)) {
            throw new HttpException(400, "$key must be a price in euros, e.g. 9.99");
        }

        return (int) round((float) $value * 100);
    }
}
