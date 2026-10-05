<?php

namespace Tests\Unit\Support;

use App\Support\CurrentUser;
use Tests\Support\FakeCurrentUser;
use Tests\TestCase;

class CurrentUserTest extends TestCase
{
    public function test_devuelve_los_usuarios_fijos_de_configuracion(): void
    {
        config([
            'sciem.usuario_prueba' => '00000000-0000-4000-8000-0000000000a1',
            'sciem.docente_fijo_id' => '00000000-0000-4000-8000-0000000000b2',
        ]);

        $resolver = $this->app->make(CurrentUser::class);

        $this->assertSame('00000000-0000-4000-8000-0000000000a1', $resolver->id());
        $this->assertSame('00000000-0000-4000-8000-0000000000b2', $resolver->teacherId());
    }

    public function test_sin_docente_configurado_devuelve_cadena_vacia(): void
    {
        config(['sciem.docente_fijo_id' => null]);

        $this->assertSame('', $this->app->make(CurrentUser::class)->teacherId());
    }

    public function test_las_pruebas_pueden_fijar_quien_actua_sin_tocar_la_configuracion(): void
    {
        config(['sciem.docente_fijo_id' => 'configurado']);

        $this->actAsTeacher('docente-de-la-prueba');
        $this->actAsUserId('cuenta-de-la-prueba');

        $resolver = $this->app->make(CurrentUser::class);

        $this->assertInstanceOf(FakeCurrentUser::class, $resolver);
        $this->assertSame('docente-de-la-prueba', $resolver->teacherId());
        $this->assertSame('cuenta-de-la-prueba', $resolver->id());
    }
}
