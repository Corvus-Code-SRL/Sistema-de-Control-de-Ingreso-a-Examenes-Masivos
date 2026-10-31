<?php

namespace App\Http\Requests\EntryControl;

use Illuminate\Foundation\Http\FormRequest;

class SearchStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required_without_all:sis,ci', 'prohibits:sis,ci', 'string', 'min:2', 'max:80'],
            'sis' => ['required_without_all:nombre,ci', 'prohibits:nombre,ci', 'string', 'regex:/^[0-9]{3,9}$/'],
            'ci' => ['required_without_all:nombre,sis', 'prohibits:nombre,sis', 'string', 'regex:/^[0-9]{4,10}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'sis.regex' => 'Ingrese entre 3 y 9 dígitos del código SIS.',
            'ci.regex' => 'Ingrese entre 4 y 10 dígitos del CI.',
            'nombre.required_without_all' => 'Ingrese un nombre, un SIS o un CI.',
            'sis.required_without_all' => 'Ingrese un nombre, un SIS o un CI.',
            'ci.required_without_all' => 'Ingrese un nombre, un SIS o un CI.',
            'nombre.prohibits' => 'Busque por nombre, SIS o CI, uno a la vez.',
            'sis.prohibits' => 'Busque por nombre, SIS o CI, uno a la vez.',
            'ci.prohibits' => 'Busque por nombre, SIS o CI, uno a la vez.',
        ];
    }
}
