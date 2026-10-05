<?php

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * La API no tiene pantalla de login que redirigir (la sirve la SPA): sin sesión siempre
     * responde 401 en JSON, aunque el cliente no mande `Accept: application/json`. El
     * comportamiento por defecto redirige a route('login'), que no existe, y termina en un 500.
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        return response()->json(['message' => $exception->getMessage()], 401);
    }

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // El cuerpo superó post_max_size (16 MB): PHP lo descarta y Laravel responde sin mensaje.
        $this->renderable(function (PostTooLargeException $e, Request $request) {
            if ($request->is('api/grupos/*/nomina/*')) {
                return ApiResponse::error('La nómina no puede superar los 10 MB.', 413);
            }
        });
    }
}
