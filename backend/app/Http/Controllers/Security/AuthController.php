<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Http\Requests\Security\ConfirmPasswordRequest;
use App\Http\Requests\Security\LoginRequest;
use App\Http\Resources\Security\AuthSessionResource;
use App\Http\Resources\Security\LogoutResource;
use App\Http\Resources\Security\PasswordConfirmationResource;
use App\Services\Security\AuthService;
use Illuminate\Http\Request;

/**
 * Inicio de sesión, cierre y consulta de la sesión con tokens de Sanctum.
 */
class AuthController extends Controller
{
    private AuthService $auth;

    public function __construct(AuthService $auth)
    {
        $this->auth = $auth;
    }

    /** POST /api/auth/login */
    public function login(LoginRequest $request): AuthSessionResource
    {
        $validated = $request->validated();

        $session = $this->auth->iniciarSesion($validated['cod_sis'], $validated['password'], (string) $request->ip());

        return (new AuthSessionResource($session))->additional(['mensaje' => 'Sesión iniciada correctamente.']);
    }

    /** POST /api/auth/logout — revoca solo el token en uso. */
    public function logout(Request $request): LogoutResource
    {
        $this->auth->cerrarSesion($request->user()->currentAccessToken());

        return (new LogoutResource(null))->additional(['mensaje' => 'Sesión cerrada correctamente.']);
    }

    /** GET /api/auth/yo */
    public function me(Request $request): AuthSessionResource
    {
        $user = $request->user();

        return new AuthSessionResource($this->auth->sesionActual($user, $user->currentAccessToken()));
    }

    /** POST /api/auth/confirmar-password */
    public function confirmPassword(ConfirmPasswordRequest $request): PasswordConfirmationResource
    {
        $user = $request->user();

        $confirmedAt = $this->auth->confirmarPassword(
            $user,
            $request->validated()['password'],
            $user->currentAccessToken()
        );

        return (new PasswordConfirmationResource($confirmedAt))->additional(['mensaje' => 'Contraseña confirmada.']);
    }
}
