<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Usuario fijo para probar el control de ingreso antes de implementar el login. */
class UseDevelopmentEntryUser
{
    public function handle(Request $request, Closure $next)
    {
        // El bypass solo se instala en rutas locales; esta segunda guarda evita
        // que se use accidentalmente si alguien aplica el middleware en otro entorno.
        if (! app()->environment('local')) {
            return new JsonResponse(['message' => 'Acceso de desarrollo no disponible.'], 403);
        }

        $userId = config('sciem.docente_fijo_id');
        $user = $userId ? User::query()->find($userId) : null;

        if ($user === null || $user->estado !== User::ESTADO_ACTIVO) {
            return new JsonResponse([
                'message' => 'Configure un docente activo en SCIEM_DOCENTE_FIJO_ID para probar el control de ingreso.',
            ], 503);
        }

        Auth::setUser($user);
        $request->setUserResolver(static function () use ($user): User {
            return $user;
        });

        return $next($request);
    }
}
