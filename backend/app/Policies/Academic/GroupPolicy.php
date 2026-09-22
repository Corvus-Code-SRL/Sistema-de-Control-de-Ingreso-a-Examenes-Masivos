<?php

namespace App\Policies\Academic;

use App\Models\Group;
use App\Models\User;
use App\Services\Academic\SubjectCatalogService;
use Illuminate\Auth\Access\Response;

/**
 * Acceso al detalle de un grupo académico: solo lo abre el docente que lo dicta.
 *
 * El listado de grupos del par no pasa por aquí: sigue abierto a todos los docentes.
 */
class GroupPolicy
{
    private SubjectCatalogService $subjectCatalog;

    public function __construct(SubjectCatalogService $subjectCatalog)
    {
        $this->subjectCatalog = $subjectCatalog;
    }

    /**
     * Sin autenticación real no hay usuario en sesión, así que el parámetro es opcional
     * y el docente se resuelve desde configuración, igual que en el resto del módulo.
     */
    public function view(?User $user, Group $group): Response
    {
        if ((string) $group->id_usuario_docente === $this->subjectCatalog->teacherId()) {
            return Response::allow();
        }

        return Response::deny('Solo el docente que dicta el grupo puede ver su detalle.');
    }

    public function update(?User $user, Group $group): Response
    {
        return $this->view($user, $group);
    }
}
