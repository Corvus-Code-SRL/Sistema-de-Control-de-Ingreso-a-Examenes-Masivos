<?php

declare(strict_types=1);

// Utilidad de primera instalación: vive fuera del código del backend.
use App\Models\Role;
use App\Models\User;
use App\Services\Security\UserService;
use App\Support\SisCode;
use App\Support\SisCodeFormat;
use App\Support\SystemAccounts;
use App\Support\SystemActor;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Question\Question;

$fromEnv = in_array('--from-env', $argv, true);
if (PHP_SAPI !== 'cli' || (! $fromEnv && ! stream_isatty(STDIN))) {
    fwrite(STDERR, "Esta utilidad requiere una terminal interactiva o --from-env.\n");
    exit(1);
}

$basePath = '/var/www/sciem/backend';
require $basePath.'/vendor/autoload.php';
$app = require $basePath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$input = new ArgvInput();
$output = new ConsoleOutput();
$questions = new QuestionHelper();
$ask = static function (string $label, bool $hidden = false) use ($input, $output, $questions): string {
    $question = new Question($label.': ');
    if ($hidden) {
        $question->setHidden(true);
        $question->setHiddenFallback(false);
    }

    return trim((string) $questions->ask($input, $output, $question));
};

$actorId = $app->make(SystemActor::class)->id();
$role = Role::where('nombre_rol', Role::ADMINISTRADOR)->first();
if ($actorId === null || ! User::whereKey($actorId)->exists() || $role === null) {
    $output->writeln('<error>Ejecute primero las migraciones y los seeders.</error>');
    exit(1);
}

$humanAdministratorExists = static function () use ($actorId): bool {
    return User::activos()
        ->whereHas('activeRoles', static fn ($query) => $query->where('nombre_rol', Role::ADMINISTRADOR))
        ->get(['id_usuario', 'cod_sis'])
        ->contains(static fn (User $user): bool =>
            $user->id_usuario !== $actorId && ! SystemAccounts::isSystemCode($user->cod_sis));
};
if ($humanAdministratorExists()) {
    if ($fromEnv) {
        $output->writeln('<info>Ya existe un Administrador humano activo; se omite el alta inicial.</info>');
        exit(0);
    }

    $output->writeln('<error>Ya existe un Administrador humano activo.</error>');
    exit(1);
}

if ($fromEnv) {
    $code = SisCode::normalize((string) getenv('BOOTSTRAP_ADMIN_CODE'));
    $name = trim((string) getenv('BOOTSTRAP_ADMIN_NAME'));
    $lastName = trim((string) getenv('BOOTSTRAP_ADMIN_LAST_NAME'));
    $secondLastName = trim((string) getenv('BOOTSTRAP_ADMIN_MATERNAL_LAST_NAME'));
    $email = trim((string) getenv('BOOTSTRAP_ADMIN_EMAIL'));
    $password = (string) getenv('BOOTSTRAP_ADMIN_PASSWORD');
    $confirmation = $password;
} else {
    $code = SisCode::normalize($ask('Código SIS del Administrador (ej. ADM0001)'));
    $name = $ask('Nombres');
    $lastName = $ask('Apellido paterno');
    $secondLastName = $ask('Apellido materno (opcional)');
    $email = $ask('Correo institucional');
    $password = $ask('Contraseña temporal', true);
    $confirmation = $ask('Repita la contraseña temporal', true);
}
$data = [
    'cod_sis' => $code,
    'nombre' => $name,
    'apellido_paterno' => $lastName,
    'apellido_materno' => $secondLastName,
    'correo' => $email,
    'contrasenia_temporal' => $password,
    'id_rol' => (int) $role->id_rol,
];
if ($data['apellido_materno'] === '') {
    $data['apellido_materno'] = null;
}

$validator = Validator::make($data, [
    'cod_sis' => ['required', 'string', 'max:15'],
    'nombre' => ['required', 'string', 'max:50'],
    'apellido_paterno' => ['required', 'string', 'max:60'],
    'apellido_materno' => ['nullable', 'string', 'max:60'],
    'correo' => ['required', 'email', 'max:100', Rule::unique('usuario', 'correo')],
    'contrasenia_temporal' => ['required', 'string', 'min:8', 'max:72'],
]);
if ($validator->fails()) {
    foreach ($validator->errors()->all() as $message) {
        $output->writeln('<error>'.htmlspecialchars($message, ENT_QUOTES, 'UTF-8').'</error>');
    }
    exit(1);
}
if (! SisCodeFormat::isValid($code, Role::ADMINISTRADOR)
    || SystemAccounts::isSystemCode($code)
    || User::whereRaw('upper(cod_sis) = ?', [$code])->exists()) {
    $output->writeln('<error>El código SIS es inválido, pertenece al sistema o ya existe.</error>');
    exit(1);
}
if ($password !== $confirmation) {
    $output->writeln('<error>Las contraseñas no coinciden.</error>');
    exit(1);
}

DB::transaction(static function () use ($app, $actorId, $data, $humanAdministratorExists, $output): void {
    User::whereKey($actorId)->lockForUpdate()->firstOrFail();
    if ($humanAdministratorExists()) {
        throw new RuntimeException('Otro operador ya creó el primer Administrador.');
    }

    $user = $app->make(UserService::class)->registrar($data, $actorId);
    $output->writeln('<info>Administrador creado: '.$user->cod_sis.'. Debe cambiar su contraseña al ingresar.</info>');
});
