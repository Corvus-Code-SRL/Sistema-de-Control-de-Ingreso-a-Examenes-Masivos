<?php

namespace Tests\Feature\EntryControl;

use App\Http\Middleware\UseDevelopmentEntryUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\Concerns\SeedsExamCatalog;
use Tests\TestCase;

class DevelopmentEntryUserTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsExamCatalog;

    public function test_usa_el_docente_configurado_solo_en_local(): void
    {
        $this->seedExamCatalog();
        config()->set('sciem.docente_fijo_id', $this->docenteId);
        $request = Request::create('/api/control-ingreso/examenes');

        app()->detectEnvironment(static function (): string {
            return 'local';
        });

        try {
            $response = app(UseDevelopmentEntryUser::class)->handle(
                $request,
                static function (Request $request) {
                    return response()->json(['id_usuario' => $request->user()->id_usuario]);
                }
            );
        } finally {
            app()->detectEnvironment(static function (): string {
                return 'testing';
            });
        }

        $this->assertSame(200, $response->status());
        $this->assertSame($this->docenteId, $response->getData(true)['id_usuario']);

        $denied = app(UseDevelopmentEntryUser::class)->handle(
            Request::create('/api/control-ingreso/examenes'),
            static function () {
                return response()->json(['unexpected' => true]);
            }
        );
        $this->assertSame(403, $denied->status());
    }
}
