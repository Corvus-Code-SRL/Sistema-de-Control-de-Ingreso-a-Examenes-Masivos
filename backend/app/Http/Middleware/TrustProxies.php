<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * Los proxies confiables salen de config('sciem.proxies_confiables') (SCIEM_PROXIES_CONFIABLES): ver
     * proxies().
     *
     * @var array<int, string>|string|null
     */
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

    /**
     * Vacío: ninguno (acceso directo). '*': cualquiera. Si no, una lista de IP o rangos separada por comas.
     *
     * @return array<int, string>|string|null
     */
    protected function proxies()
    {
        $value = trim((string) config('sciem.proxies_confiables', ''));

        if ($value === '') {
            return null;
        }

        if ($value === '*') {
            return '*';
        }

        return array_values(array_filter(array_map('trim', explode(',', $value)), 'strlen'));
    }
}
