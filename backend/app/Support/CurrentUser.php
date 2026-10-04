<?php

namespace App\Support;

/**
 * Único punto que responde "quién ejecuta la operación".
 *
 * REGLA: los Services NO leen este resolver ni auth(). El Controller (o el comando, o el
 * seeder) lo consulta y entrega el usuario al Service como argumento explícito
 * (`$service->create($data, $currentUser->teacherId())`). Así una prueba decide quién actúa
 * pasando un id, sin depender de la sesión ni de la configuración. Los Form Requests y las
 * Policies, que son capa HTTP, también pueden usarlo para autorizar.
 *
 * Mientras no haya autenticación real hay dos actores fijos, porque hoy el sistema simula
 * dos personas distintas: el docente de config('sciem.docente_fijo_id') para Academic y
 * Exams, y la cuenta de config('sciem.usuario_prueba') para Security y la bitácora. Cuando
 * llegue la autenticación, el cambio es el cuerpo de estos dos métodos, y solo ahí.
 *
 * Las pruebas reemplazan este resolver con Tests\Support\FakeCurrentUser.
 */
class CurrentUser
{
    /** Id de la cuenta que actúa como usuario general (Administrador, bitácora). */
    public function id(): ?string
    {
        return auth()->id() ?? config('sciem.usuario_prueba');
    }

    /** Id del docente que actúa en los módulos Academic y Exams; '' si no está configurado. */
    public function teacherId(): string
    {
        return (string) config('sciem.docente_fijo_id');
    }
}
