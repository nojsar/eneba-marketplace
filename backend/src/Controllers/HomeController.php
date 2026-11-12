<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Home\BlockProvider;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\HomeBlockRepository;

final class HomeController
{
    /** @param array<string, BlockProvider> $providers keyed by the name stored in home_blocks.provider */
    public function __construct(
        private readonly HomeBlockRepository $blocks,
        private readonly array $providers,
    ) {
    }

    /**
     * GET /api/home — which blocks the homepage shows, in order, and where each one
     * fetches its content. The frontend draws exactly this list.
     */
    public function layout(Request $request): Response
    {
        $layout = [];
        foreach ($this->blocks->active() as $block) {
            $provider = $this->providers[$block['provider']] ?? null;
            if ($provider === null) {
                // A typo in the table must not break the whole homepage: skip the block and log it.
                error_log("home_blocks {$block['id']}: unknown provider '{$block['provider']}'");
                continue;
            }

            $layout[] = [
                'id' => $block['id'],
                'title' => $block['title'],
                'component' => $provider->component(),
                'source' => '/blocks/' . $block['provider'], // path under /api
            ];
        }

        return Response::json(['blocks' => $layout]);
    }

    /** GET /api/blocks/{name} — one block's content, from that block's own provider. */
    public function block(Request $request): Response
    {
        $provider = $this->providers[$request->params['name']] ?? throw new HttpException(404, 'Unknown block');

        return Response::json($provider->content());
    }
}
