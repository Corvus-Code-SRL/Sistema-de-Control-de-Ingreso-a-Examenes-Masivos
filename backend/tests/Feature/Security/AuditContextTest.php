<?php

namespace Tests\Feature\Security;

use App\Models\Session;
use App\Models\User;
use App\Support\Audit\AuditContext;
use Database\Seeders\ActionSeeder;
use Database\Seeders\AdministratorAccountSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TestData\AccountTestDataSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * AuditContext y el middleware session.track: cada petición con token propio sabe desde qué sesión
 * (dispositivo) se hace, sin que ningún Service resuelva al usuario.
 */
class AuditContextTest extends TestCase
{
    use DatabaseTransactions;

    private const TEACHER_SIS = '10452';

    private const PHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148';

    private const DESKTOP = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            UserSeeder::class,
            RoleSeeder::class,
            ActionSeeder::class,
            AdministratorAccountSeeder::class,
            AccountTestDataSeeder::class,
        ]);
    }

    private function login(string $agent = self::DESKTOP): string
    {
        $this->app['auth']->forgetGuards();

        return (string) $this->withHeaders(['User-Agent' => $agent])
            ->postJson('/api/auth/login', ['cod_sis' => self::TEACHER_SIS, 'password' => 'password'])
            ->assertOk()
            ->json('data.token');
    }

    private function asToken(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function teacher(): User
    {
        return User::where('cod_sis', self::TEACHER_SIS)->firstOrFail();
    }

    public function test_la_sesion_de_la_peticion_es_la_del_token_usado(): void
    {
        $phone = $this->login(self::PHONE);
        $desktop = $this->login(self::DESKTOP);

        $this->app['router']->get('/_prueba/sesion-actual', function () {
            return ['id_sesion' => app(AuditContext::class)->sessionId()];
        })->middleware(['auth:sanctum', 'session.track']);

        $this->asToken($phone)->getJson('/_prueba/sesion-actual')
            ->assertJsonPath('id_sesion', Session::where('agente', self::PHONE)->value('id_sesion'));
        $this->asToken($desktop)->getJson('/_prueba/sesion-actual')
            ->assertJsonPath('id_sesion', Session::where('agente', self::DESKTOP)->value('id_sesion'));
    }

    public function test_sin_token_propio_no_hay_sesion_actual(): void
    {
        $this->assertNull(app(AuditContext::class)->sessionId());

        $this->app['router']->get('/_prueba/sesion-actual', function () {
            return ['id_sesion' => app(AuditContext::class)->sessionId()];
        })->middleware(['auth:sanctum', 'session.track']);

        Sanctum::actingAs($this->teacher());
        $this->getJson('/_prueba/sesion-actual')->assertOk()->assertJsonPath('id_sesion', null);
    }

    public function test_al_terminar_la_peticion_se_olvida_la_sesion(): void
    {
        $token = $this->login();

        $this->asToken($token)->getJson('/api/auth/yo')->assertOk();

        $this->assertNull(app(AuditContext::class)->sessionId());
    }

    public function test_una_sesion_fijada_a_mano_no_hace_consultas(): void
    {
        $context = app(AuditContext::class);
        $context->forSession(41);

        $this->assertSame(41, $context->sessionId());

        $context->reset();
        $this->assertNull($context->sessionId());
    }
}
