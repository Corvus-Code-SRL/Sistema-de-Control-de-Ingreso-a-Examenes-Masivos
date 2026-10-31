<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\Exams\AssignAssistantClassroomRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Validator;
use Tests\Concerns\SeedsSecurityAccounts;
use Tests\TestCase;

class AssignAssistantClassroomRequestTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsSecurityAccounts;

    private const INVALID_MESSAGE = 'El ambiente seleccionado no es válido.';

    public function test_acepta_un_identificador_de_ambiente(): void
    {
        $this->assertFalse($this->validator(['id_ambiente' => 15])->fails());
    }

    public function test_exige_el_ambiente(): void
    {
        foreach ([[], ['id_ambiente' => null]] as $data) {
            $validator = $this->validator($data);

            $this->assertTrue($validator->fails());
            $this->assertSame('Debe seleccionar un ambiente.', $validator->errors()->first('id_ambiente'));
        }
    }

    public function test_rechaza_con_mensaje_claro_lo_que_no_es_un_identificador_valido(): void
    {
        // Un valor por regla: integer, min y max.
        foreach (['abc', 0, -3, 2147483648] as $value) {
            $validator = $this->validator(['id_ambiente' => $value]);

            $this->assertTrue($validator->fails(), 'Debió rechazar: ' . var_export($value, true));
            $this->assertSame(self::INVALID_MESSAGE, $validator->errors()->first('id_ambiente'));
        }
    }

    public function test_autoriza_solo_a_un_docente_activo(): void
    {
        $this->seedSecurityAccounts();
        $teacher = User::factory()->create();
        $this->giveRole($teacher, Role::DOCENTE);

        $this->actAs($teacher);
        $this->assertTrue(app()->call([new AssignAssistantClassroomRequest(), 'authorize']));

        $this->actAsUserId($this->administratorId);
        $this->assertFalse(app()->call([new AssignAssistantClassroomRequest(), 'authorize']));
    }

    private function validator(array $data): \Illuminate\Validation\Validator
    {
        $request = new AssignAssistantClassroomRequest();
        $request->merge($data);

        return Validator::make(
            $request->all(),
            $request->rules(),
            $request->messages(),
            $request->attributes()
        );
    }
}
