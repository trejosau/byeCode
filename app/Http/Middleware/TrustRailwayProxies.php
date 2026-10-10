<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

class TrustRailwayProxies extends TrustProxies
{
    protected $headers = Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_PORT;

    protected function proxies(): ?string
    {
        // Only Railway ingress can reach the production service. Never trust forwarded hosts.
        return app()->isProduction() ? '*' : null;
    }
}
