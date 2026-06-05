<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * Use the `TRUSTED_PROXIES` env var (comma-separated) or `*` to trust all proxies.
     *
     * @var array|string|null
     */
    /** @var array<int, string>|string|null */
    protected $proxies;

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;

    public function __construct()
    {
        $proxies = config('app.trusted_proxies');

        if ($proxies === '*') {
            $this->proxies = '*';
        } elseif ($proxies) {
            $this->proxies = array_map('trim', explode(',', $proxies));
        } else {
            $this->proxies = null;
        }
    }
}
