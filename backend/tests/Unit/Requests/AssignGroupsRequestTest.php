<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\Exams\AssignGroupsRequest;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Validator;
use Tests\Concerns\SeedsAcademicCatalog;
use Tests\TestCase;

class AssignGroupsRequestTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsAcademicCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAcademicCatalog();
    }

    public function test_acepta_una_lista_de_grupos_existentes_y_sin_duplicados(): void
    {
        $validator = $this->validator(['grupos' => [$this->grupoPropioId]]);

        $this->assertFalse($validator->fails());
    }

    public function test_rechaza_una_lista_vacia(): void
    {
        $validator = $this->validator(['grupos' => []]);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('grupos'));
    }

    public function test_rechaza_grupos_duplicados(): void
    {
        $validator = $this->validator([
            'grupos' => [$this->grupoPropioId, $this->grupoPropioId],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('grupos.1'));
    }

    public function test_rechaza_un_grupo_inexistente(): void
    {
        $validator = $this->validator(['grupos' => [999999]]);

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('grupos.0'));
    }

    public function test_autoriza_la_solicitud(): void
    {
        $this->assertTrue((new AssignGroupsRequest())->authorize());
    }

    private function validator(array $data): \Illuminate\Validation\Validator
    {
        $request = new AssignGroupsRequest();
        $request->merge($data);

        return Validator::make(
            $request->all(),
            $request->rules(),
            $request->messages(),
            $request->attributes()
        );
    }
}
