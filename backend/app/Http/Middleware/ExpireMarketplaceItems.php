<?php

namespace App\Http\Middleware;

use App\Services\MarketplaceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExpireMarketplaceItems
{
    public function __construct(
        private MarketplaceService $marketplaceService
    ) {
    }

    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $this->marketplaceService->expireDueItems();

        return $next($request);
    }
}
