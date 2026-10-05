<?php

namespace Tests\Unit\Support;

use App\Models\User;
use App\Support\CurrentUser;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CurrentUserTest extends TestCase
{
    private const ACCOUNT_ID = '00000000-0000-4000-8000-0000000000b2';

    public function test_sin_sesion_no_hay_usuario_actual(): void
    {
        $resolver = $this->app->make(CurrentUser::class);

        $this->assertNull($resolver->id());
        $this->assertSame('', $resolver->teacherId());
    }

    public function test_sin_sesion_no_recurre_a_la_cuenta_de_sistema(): void
    {
        config(['sciem.usuario_prueba' => '00000000-0000-4000-8000-0000000000a1']);

        $resolver = $this->app->make(CurrentUser::class);

        $this->assertNull($resolver->id());
        $this->assertSame('', $resolver->teacherId());
    }

    /**
     * Un .env anterior a RNF-02 todavía puede traer SCIEM_DOCENTE_FIJO_ID: la aplicación arranca
     * igual, la variable ya no figura en la configuración y nadie actúa en nombre de ese docente.
     */
    public function test_una_variable_heredada_sciem_docente_fijo_id_no_rompe_ni_se_lee(): void
    {
        $variable = 'SCIEM_DOCENTE_FIJO_ID';
        $legacyTeacher = '00000000-0000-4000-8000-000000000011';

        putenv("{$variable}={$legacyTeacher}");
        $_ENV[$variable] = $_SERVER[$variable] = $legacyTeacher;

        try {
            $this->refreshApplication();

            $resolver = $this->app->make(CurrentUser::class);

            $this->assertArrayNotHasKey('docente_fijo_id', config('sciem'));
            $this->assertNull($resolver->id());
            $this->assertSame('', $resolver->teacherId());
        } finally {
            putenv($variable);
            unset($_ENV[$variable], $_SERVER[$variable]);
        }
    }

    public function test_con_sesion_devuelve_la_cuenta_autenticada(): void
    {
        Sanctum::actingAs(new User(['id_usuario' => self::ACCOUNT_ID]));

        $resolver = $this->app->make(CurrentUser::class);

        $this->assertSame(self::ACCOUNT_ID, $resolver->id());
        $this->assertSame(self::ACCOUNT_ID, $resolver->teacherId());
    }
}
