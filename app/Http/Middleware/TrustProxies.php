<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;

    /**
     * Railway / reverse proxy: tin cấp HTTPS từ X-Forwarded-Proto.
     * Đặt TRUSTED_PROXIES= trong .env (rỗng) nếu chạy local không qua proxy.
     */
    public function __construct()
    {
        $raw = env('TRUSTED_PROXIES');
        if ($raw === null) {
            $this->proxies = '*';
        } elseif (trim((string) $raw) === '') {
            $this->proxies = null;
        } else {
            $this->proxies = trim((string) $raw) === '*' ? '*' : array_map('trim', explode(',', (string) $raw));
        }
    }

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
}
