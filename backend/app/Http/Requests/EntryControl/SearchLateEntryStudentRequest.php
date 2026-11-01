<?php

namespace App\Http\Requests\EntryControl;

/** Búsqueda por nombre o por prefijo de SIS o CI, con las mismas reglas del control de ingreso (HU-032). */
class SearchLateEntryStudentRequest extends LateEntryManagementRequest
{
    public function rules(): array
    {
        return (new SearchStudentRequest())->rules();
    }

    public function messages(): array
    {
        return (new SearchStudentRequest())->messages();
    }
}
