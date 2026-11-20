<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Tests\TestCase;

/** Proxies confiables desde SCIEM_PROXIES_CONFIABLES (despliegue detrás de un proxy inverso). */
class TrustProxiesTest extends TestCase
{
    private const CLIENT = '200.87.10.20';

    protected function tearDown(): void
    {
        // La lista de proxies de Symfony es estática: no debe pasar de una prueba a otra.
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);

        parent::tearDown();
    }

    /** @return array{ip: string, https: bool} */
    private function through(string $trusted, string $remote): array
    {
        config(['sciem.proxies_confiables' => $trusted]);

        $request = Request::create('/api/examenes', 'GET', [], [], [], [
            'REMOTE_ADDR' => $remote,
            'HTTP_X_FORWARDED_FOR' => self::CLIENT,
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        $seen = [];
        app(TrustProxies::class)->handle($request, function (Request $request) use (&$seen) {
            $seen = ['ip' => $request->ip(), 'https' => $request->isSecure()];

            return response('ok');
        });

        return $seen;
    }

    public function test_por_defecto_no_confia_en_ningun_proxy(): void
    {
        $this->assertSame('', config('sciem.proxies_confiables'));
        $this->assertSame(['ip' => '10.0.0.5', 'https' => false], $this->through('', '10.0.0.5'));
    }

    public function test_con_asterisco_toma_la_ip_y_el_esquema_del_proxy(): void
    {
        $this->assertSame(['ip' => self::CLIENT, 'https' => true], $this->through('*', '10.0.0.5'));
    }

    public function test_con_una_lista_solo_confia_en_esos_proxies(): void
    {
        $trusted = ' 10.0.0.5 , 172.16.0.0/12 ';

        $this->assertSame(['ip' => self::CLIENT, 'https' => true], $this->through($trusted, '10.0.0.5'));
        $this->assertSame(['ip' => self::CLIENT, 'https' => true], $this->through($trusted, '172.18.0.3'));
        $this->assertSame(['ip' => '192.168.1.9', 'https' => false], $this->through($trusted, '192.168.1.9'));
    }
}
