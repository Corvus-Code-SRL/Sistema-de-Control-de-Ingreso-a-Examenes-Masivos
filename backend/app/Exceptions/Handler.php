<?php

namespace App\Exceptions;

use App\Exceptions\Academic\GroupNotEditableException;
use App\Exceptions\Academic\StudentRosterFileException;
use App\Exceptions\Academic\StudentRosterGroupAccessException;
use App\Exceptions\Academic\StudentRosterPreviewUnavailableException;
use App\Exceptions\EntryControl\EntryOpeningException;
use App\Exceptions\Exams\ExamAssistantNotFoundException;
use App\Exceptions\Exams\ExamOwnershipException;
use App\Exceptions\Exams\ExamStateException;
use App\Exceptions\Exams\ExamWarningsException;
use App\Exceptions\Security\DuplicateAccountException;
use App\Exceptions\Security\InactiveAccountException;
use App\Exceptions\Security\InvalidCredentialsException;
use App\Exceptions\Security\NoCurrentRoleException;
use App\Exceptions\Security\PasswordChangeRequiredException;
use App\Exceptions\Security\SelfRoleAssignmentException;
use App\Exceptions\Security\GuestCodeTakenException;
use App\Exceptions\Security\GuestCodesExhaustedException;
use App\Exceptions\Security\RoleNotAllowedForAccountException;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use App\Exceptions\Incidents\IncidentAlreadyReviewedException;
use App\Exceptions\Incidents\IncidentOwnershipException;
use App\Exceptions\Incidents\IncidentReportNotFoundException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * Excepciones de negocio: son resultados esperados que ya responden con su propio render(), no
     * fallos. Reportarlas llena el log de trazas (algunas con nombres de personas) y tapa los
     * errores reales. QueryException, TypeError y demás siguen reportándose.
     *
     * Quedan reportadas a propósito ActivePeriodNotConfiguredException (error de despliegue) y
     * SisNoDisponibleException (el SIS caído es una señal operativa). Toda clase de app/Exceptions
     * debe figurar aquí o en ErrorReportingTest::REPORTED.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        GroupNotEditableException::class,
        StudentRosterFileException::class,
        StudentRosterGroupAccessException::class,
        StudentRosterPreviewUnavailableException::class,
        EntryOpeningException::class,
        ExamAssistantNotFoundException::class,
        ExamOwnershipException::class,
        ExamStateException::class,
        ExamWarningsException::class,
        DuplicateAccountException::class,
        InactiveAccountException::class,
        InvalidCredentialsException::class,
        NoCurrentRoleException::class,
        PasswordChangeRequiredException::class,
        SelfRoleAssignmentException::class,
        GuestCodeTakenException::class,
        GuestCodesExhaustedException::class,
        RoleNotAllowedForAccountException::class,
        IncidentAlreadyReviewedException::class,
        IncidentOwnershipException::class,
        IncidentReportNotFoundException::class,
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
        'contrasenia_temporal',
        'contrasenia_actual',
        'contrasenia_nueva',
        'contrasenia_nueva_confirmation',
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
