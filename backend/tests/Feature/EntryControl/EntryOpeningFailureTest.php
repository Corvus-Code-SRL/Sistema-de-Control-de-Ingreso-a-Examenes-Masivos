<?php

namespace Tests\Feature\EntryControl;

use App\Jobs\OpenEntryControlJob;
use App\Models\Exam;
use App\Models\User;
use App\Services\EntryControl\RoomAssignmentService;
use App\Services\Exams\PresenciaControlService;
use App\Support\Cache\EntryOpeningFailureStore;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

class EntryOpeningFailureTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-12 09:50:00', config('sciem.zona_horaria')));
        $this->seedExamCatalog();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function testMissingRosterIsReportedWithoutOpeningExam(): void
    {
        $exam = $this->scheduledExam(false);
        $this->runOpeningJob();

        $this->assertFailure($exam, 'SIN_NOMINA', 'El examen no tiene estudiantes en su nómina.');
        $this->assertSame(Exam::PROGRAMADO, $exam->fresh()->estado);
        $this->assertFalse(app(RoomAssignmentService::class)->hasPreparedSnapshot($exam->id_examen));
    }

    public function testMissingRoomsIsReportedSeparately(): void
    {
        $exam = $this->scheduledExam(true, false);
        $this->runOpeningJob();

        $this->assertFailure($exam, 'SIN_AMBIENTES', 'El examen no tiene ambientes asignados.');
        $this->assertSame(Exam::PROGRAMADO, $exam->fresh()->estado);
    }

    public function testInsufficientCapacityIncludesActualCapacityAndRoster(): void
    {
        $exam = $this->scheduledExam();
        $this->setCapacity(1);
        $this->runOpeningJob();

        $this->assertFailure($exam, 'CAPACIDAD_INSUFICIENTE', 'Capacidad 1 menor que la nómina de 2.');
        $this->assertSame(Exam::PROGRAMADO, $exam->fresh()->estado);
    }

    public function testWaitingBeforeOpeningWindowHasNoFailure(): void
    {
        $exam = $this->scheduledExam();
        Carbon::setTestNow(Carbon::parse('2026-10-12 08:00:00', config('sciem.zona_horaria')));
        $this->runOpeningJob();

        $this->getJson($this->statusUrl($exam))->assertOk()->assertExactJson([
            'data' => ['estado' => 'PROGRAMADO', 'motivo_apertura' => null],
        ]);
        $this->assertSame(Exam::PROGRAMADO, $exam->fresh()->estado);
    }

    public function testUnauthorizedUsersCannotReadTheFailureOrRegisterPresence(): void
    {
        $exam = $this->scheduledExam(false);
        $this->runOpeningJob();
        $this->actingAs(User::query()->findOrFail($this->otroDocenteId));
        $this->getJson($this->statusUrl($exam))->assertForbidden()->assertJsonMissing([
            'codigo' => 'SIN_NOMINA',
        ]);
        $this->actAsGuest();
        $this->getJson($this->statusUrl($exam))->assertUnauthorized();

        $this->assertSame([], app(PresenciaControlService::class)->getConnectedUsers($exam->id_examen));
    }

    public function testCorrectingCapacityClearsFailureAndPreservesStatusVersioning(): void
    {
        $exam = $this->scheduledExam();
        $this->setCapacity(1);
        $this->runOpeningJob();
        $this->assertNotNull(app(EntryOpeningFailureStore::class)->get($exam->id_examen));

        $this->setCapacity(2);
        $this->runOpeningJob();
        $this->assertSame(Exam::EN_INGRESO, $exam->fresh()->estado);
        $this->assertNull(app(EntryOpeningFailureStore::class)->get($exam->id_examen));
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $status = $this->getJson($this->statusUrl($exam))->assertOk()
            ->assertJsonPath('data.ingresados', 0)
            ->assertJsonPath('data.pendientes', 2)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.ultimos_ingresos', [])
            ->json('data');
        $this->assertArrayNotHasKey('motivo_apertura', $status);
        $this->assertCount(1, $status['conectados']);

        $unchanged = $this->getJson($this->statusUrl($exam) . '?desde=' . $status['version'])
            ->assertOk()->assertJsonPath('data.sin_cambios', true)
            ->assertJsonPath('data.version', $status['version'])->json('data');
        $this->assertArrayNotHasKey('ingresados', $unchanged);
        $this->assertArrayNotHasKey('motivo_apertura', $unchanged);
        $this->assertSame([], $queries);
    }

    public function testNextAttemptUpdatesFailureAndDoesNotMixExams(): void
    {
        $exam = $this->scheduledExam(true, false);
        $other = $this->scheduledExam(false);
        $this->runOpeningJob();
        $this->assertFailure($exam, 'SIN_AMBIENTES', 'El examen no tiene ambientes asignados.');

        $exam->classrooms()->attach($this->aulaId);
        $this->setCapacity(1);
        $this->runOpeningJob();
        $this->assertFailure($exam, 'CAPACIDAD_INSUFICIENTE', 'Capacidad 1 menor que la nómina de 2.');
        $this->assertFailure($other, 'SIN_NOMINA', 'El examen no tiene estudiantes en su nómina.');

        $this->setCapacity(2);
        $this->runOpeningJob();
        $this->assertSame(Exam::EN_INGRESO, $exam->fresh()->estado);
        $this->assertFailure($other, 'SIN_NOMINA', 'El examen no tiene estudiantes en su nómina.');
    }

    public function testFailureExpiresWhenControlTimeEnds(): void
    {
        $exam = $this->scheduledExam(false);
        $this->runOpeningJob();
        $this->assertNotNull(app(EntryOpeningFailureStore::class)->get($exam->id_examen));
        Carbon::setTestNow(Carbon::parse('2026-10-12 11:30:00', config('sciem.zona_horaria')));

        $this->getJson($this->statusUrl($exam))->assertStatus(409);
        // ArrayStore de Laravel 8 caduca al superar el segundo límite.
        Carbon::setTestNow(Carbon::now()->addSecond());
        $this->assertNull(app(EntryOpeningFailureStore::class)->get($exam->id_examen));
    }

    public function testTechnicalErrorsAreNotPublishedAsOpeningReasons(): void
    {
        $exam = $this->scheduledExam();
        $assignments = Mockery::mock(RoomAssignmentService::class);
        $assignments->shouldReceive('prepare')->once()->with($exam->id_examen)
            ->andThrow(new RuntimeException('SQLSTATE: detalle interno que no debe publicarse'));
        app(OpenEntryControlJob::class)->handle($assignments, app(EntryOpeningFailureStore::class));

        $this->getJson($this->statusUrl($exam))->assertOk()->assertExactJson([
            'data' => ['estado' => 'PROGRAMADO', 'motivo_apertura' => null],
        ]);
    }

    public function testInvalidVersionIsRejectedByFormRequest(): void
    {
        $exam = $this->scheduledExam(false);
        $this->getJson($this->statusUrl($exam) . '?desde[]=1')
            ->assertStatus(422)->assertJsonValidationErrors('desde');
    }

    private function scheduledExam(bool $withRoster = true, bool $withRooms = true): Exam
    {
        $exam = $this->createExam([
            'fecha' => '2026-10-12',
            'hora_inicio' => '10:00',
            'duracion' => 90,
            'minutos_apertura' => 30,
        ], $withRooms ? [$this->aulaId] : []);
        if ($withRoster) {
            DB::table('grupo_examen')->insert([
                'id_examen' => $exam->id_examen,
                'id_grupo' => $this->grupoPropioId,
            ]);
        }

        return $exam;
    }

    private function assertFailure(Exam $exam, string $code, string $message): void
    {
        $this->getJson($this->statusUrl($exam))->assertOk()->assertExactJson([
            'data' => [
                'estado' => 'PROGRAMADO',
                'motivo_apertura' => ['codigo' => $code, 'mensaje' => $message],
            ],
        ]);
    }

    private function runOpeningJob(): void
    {
        app()->call([app(OpenEntryControlJob::class), 'handle']);
    }

    private function statusUrl(Exam $exam): string
    {
        return "/api/control-ingreso/examenes/{$exam->id_examen}/estado";
    }

    private function setCapacity(int $capacity): void
    {
        DB::table('ambiente')->where('id_ambiente', $this->aulaId)->update(['capacidad' => $capacity]);
    }
}
