<?php

namespace Database\Seeders\TestData;

/**
 * Identificadores fijos de los datos de prueba compartidos.
 *
 * Todos los ids numéricos van en el rango 9000–9999 y todos los uuid comparten el
 * prefijo 00000000-0000-4000-8000-: así no chocan con los registros que crea la
 * aplicación (sus secuencias empiezan en 1) y se pueden ubicar y borrar juntos.
 */
final class TestDataIds
{
    // usuario.id_usuario
    public const ADMINISTRATOR = '00000000-0000-4000-8000-000000000001';
    public const TEACHER_FIXED = '00000000-0000-4000-8000-000000000011';
    public const TEACHER_2 = '00000000-0000-4000-8000-000000000012';
    public const TEACHER_3 = '00000000-0000-4000-8000-000000000013';
    public const ASSISTANT_1 = '00000000-0000-4000-8000-000000000021';
    public const ASSISTANT_2 = '00000000-0000-4000-8000-000000000022';
    public const WITHOUT_ROLE = '00000000-0000-4000-8000-000000000031';
    public const DISABLED = '00000000-0000-4000-8000-000000000041';

    // facultad.id_facultad
    public const FACULTY_SCIENCE = 9001;
    public const FACULTY_ECONOMICS = 9002;

    // carrera.id_carrera
    public const CAREER_SYSTEMS = 9101;
    public const CAREER_INFORMATICS = 9102;
    public const CAREER_ECONOMICS = 9103;
    public const CAREER_BUSINESS = 9104;

    // materia.id_materia
    public const SUBJECT_CALCULUS = 9201;
    public const SUBJECT_ALGEBRA = 9202;
    public const SUBJECT_PROGRAMMING = 9203;
    public const SUBJECT_DATABASES = 9204;
    public const SUBJECT_STATISTICS = 9205;
    public const SUBJECT_MICROECONOMICS = 9206;
    public const SUBJECT_ACCOUNTING = 9207;
    public const SUBJECT_ARCHIVED = 9208;

    // periodo.id_periodo
    public const PERIOD_2025_2 = 9301;
    public const PERIOD_2026_1 = 9302;
    public const PERIOD_ACTIVE = 9303;

    // grupo.id_grupo
    public const GROUP_CALCULUS_SYS_1 = 9401;
    public const GROUP_CALCULUS_SYS_2 = 9402;
    public const GROUP_CALCULUS_ECO_1 = 9403;
    public const GROUP_DATABASES_SYS_1 = 9404;
    public const GROUP_ALGEBRA_INF_1 = 9405;
    public const GROUP_PROGRAMMING_SYS_1 = 9406;
    public const GROUP_PROGRAMMING_SYS_2 = 9407;
    public const GROUP_STATISTICS_ECO_1 = 9408;
    public const GROUP_DATABASES_INF_1 = 9409;
    public const GROUP_CALCULUS_SYS_1_PREVIOUS = 9410;
    public const GROUP_ACCOUNTING_ADM_1 = 9411;

    /** Hash bcrypt fijo de «password»: Hash::make() usa sal aleatoria y rompería el determinismo. */
    public const PASSWORD_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    public static function userIds(): array
    {
        return [
            self::ADMINISTRATOR,
            self::TEACHER_FIXED,
            self::TEACHER_2,
            self::TEACHER_3,
            self::ASSISTANT_1,
            self::ASSISTANT_2,
            self::WITHOUT_ROLE,
            self::DISABLED,
        ];
    }
}
