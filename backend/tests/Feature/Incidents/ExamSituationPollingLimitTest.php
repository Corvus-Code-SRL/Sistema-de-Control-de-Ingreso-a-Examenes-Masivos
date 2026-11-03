<?php

namespace Tests\Feature\Incidents;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\SeedsExamSituations;
use Tests\TestCase;

/**
 * HU-057 / RNF-03: la pantalla «Registrar situación» consulta el contexto cada 5 s, así que esa ruta usa la
 * cuota del limitador `polling` y no la de `api`, igual que En curso y el control de ingreso.
 */
class ExamSituationPollingLimitTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamSituations;

    private const SHARED_IP = '203.0.113.20';
    private const AUTHENTICATED_ROUTE = '/api/user';
    private const CONTEXT_ROUTE_NAME = 'situaciones.context';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedExamSituations();

        config([
            'sciem.rate_limits.api' => 3,
            'sciem.rate_limits.polling' => 2,
        ]);
    }

    private function contextUrl(): string
    {
        return "/api/situaciones/examenes/{$this->situationExam->id_examen}/contexto";
    }

    /** Middleware efectivo de la ruta: grupos expandidos y exclusiones (`withoutMiddleware`) aplicadas. */
    private function effectiveMiddleware(string $routeName): array
    {
        return $this->app['router']->gatherRouteMiddleware(Route::getRoutes()->getByName($routeName));
    }

    public function testContextRouteUsesThePollingLimiterInsteadOfTheActionsOne(): void
    {
        $middleware = $this->effectiveMiddleware(self::CONTEXT_ROUTE_NAME);

        $this->assertContains(ThrottleRequests::class . ':polling', $middleware);
        $this->assertNotContains(ThrottleRequests::class . ':api', $middleware);
    }

    public function testOtherSituationRoutesKeepTheActionsLimiter(): void
    {
        foreach (['situaciones.exams', 'situaciones.index', 'situaciones.store'] as $name) {
            $middleware = $this->effectiveMiddleware($name);

            $this->assertContains(ThrottleRequests::class . ':api', $middleware, $name);
            $this->assertNotContains(ThrottleRequests::class . ':polling', $middleware, $name);
        }
    }

    public function testContextPollingIsLimitedApartFromActions(): void
    {
        $token = $this->tokenForTeacher();

        $this->assertNotSame(429, $this->hit($this->contextUrl(), $token, 2)->getStatusCode());
        $this->hit($this->contextUrl(), $token)->assertStatus(429)->assertHeader('Retry-After');

        // Agotar el sondeo no le quita cuota a las acciones.
        $this->hit(self::AUTHENTICATED_ROUTE, $token, 3)->assertOk();
    }

    public function testActionsDoNotConsumeThePollingQuota(): void
    {
        $token = $this->tokenForTeacher();

        $this->hit(self::AUTHENTICATED_ROUTE, $token, 3)->assertOk();
        $this->hit(self::AUTHENTICATED_ROUTE, $token)->assertStatus(429);

        $this->assertNotSame(429, $this->hit($this->contextUrl(), $token, 2)->getStatusCode());
    }

    private function tokenForTeacher(): string
    {
        return User::query()->findOrFail($this->docenteId)->createToken('prueba')->plainTextToken;
    }

    /** Repite la petición `$times` veces y devuelve la última respuesta. */
    private function hit(string $uri, string $token, int $times = 1)
    {
        $response = null;

        for ($attempt = 0; $attempt < $times; $attempt++) {
            // Cada petición debe resolver la sesión desde cero, como en producción.
            $this->app['auth']->forgetGuards();

            $response = $this->withServerVariables(['REMOTE_ADDR' => self::SHARED_IP])
                ->withHeaders(['Accept' => 'application/json'])
                ->withToken($token)
                ->getJson($uri);
        }

        return $response;
    }
}
