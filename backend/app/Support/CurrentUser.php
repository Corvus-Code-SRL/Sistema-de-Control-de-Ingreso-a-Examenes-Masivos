<?php

namespace App\Support;

/**
 * Único punto que responde "quién ejecuta la operación": la cuenta autenticada por el token
 * de Sanctum. No hay respaldo ni usuario por defecto: sin sesión devuelve null (o ''), y como
 * todas las rutas de la API pasan por `auth:sanctum`, un Controller solo lo ve vacío si alguien
 * sacó la ruta del grupo autenticado (lo vigila RoutesRequireAuthenticationTest).
 *
 * REGLA: los Services NO leen este resolver ni auth(). El Controller lo consulta y entrega el
 * usuario al Service como argumento explícito (`$service->create($data, $currentUser->teacherId())`).
 * Así una prueba decide quién actúa pasando un id, sin depender de la sesión. Los Form Requests y
 * las Policies, que son capa HTTP, también pueden usarlo para autorizar.
 *
 * Los seeders y comandos no tienen sesión: usan App\Support\SystemActor, nunca este resolver.
 *
 * Las pruebas deciden quién actúa con Sanctum::actingAs (ver TestCase::actAsUserId()).
 */
class CurrentUser
{
    /** Id de la cuenta autenticada; null si no hay sesión. */
    public function id(): ?string
    {
        $id = auth()->id();

        return $id === null ? null : (string) $id;
    }

    /**
     * Id de la cuenta que actúa como docente en Academic y Exams; '' si no hay sesión.
     *
     * Es la misma cuenta que id(): que sea Docente lo comprueba cada Form Request con
     * UserRoleService::isTeacher(), no este resolver.
     */
    public function teacherId(): string
    {
        return (string) $this->id();
    }
}
