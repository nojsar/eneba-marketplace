<?php

declare(strict_types=1);

namespace App\Home;

/**
 * One homepage block's backend. Each block has its own provider with its own data
 * source and its own endpoint (GET /api/blocks/{name}). The frontend learns from
 * GET /api/home which blocks exist and where to fetch each one, so it hardcodes nothing.
 *
 * Each provider runs its own query rather than going through ProductRepository:
 * a block is one self-contained file that could move to a separate service.
 */
interface BlockProvider
{
    /** Which frontend component draws this block, e.g. "hero" or "product_rail". */
    public function component(): string;

    /** The block's content, sent to the frontend as JSON. */
    public function content(): array;
}
