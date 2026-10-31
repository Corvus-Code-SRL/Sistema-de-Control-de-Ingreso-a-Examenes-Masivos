<?php

namespace App\Http\Requests\Incidents;

/** Reutiliza las reglas de falta, descripción y evidencia; el estudiante procede de la ruta. */
class StoreInProgressIncidentRequest extends StoreIncidentReportRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'examen' => $this->route('examen'),
            'estudiante' => $this->route('estudiante'),
            'descripcion' => is_string($this->input('descripcion'))
                ? trim($this->input('descripcion')) : $this->input('descripcion'),
        ]);
    }

    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['intento']);

        return array_merge($rules, [
            'estudiante' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'id_ambiente' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'retirar' => ['required', 'boolean'],
            'origen' => ['prohibited'],
            'retiro_examen' => ['prohibited'],
            'id_ambiente_hecho' => ['prohibited'],
            'fecha_reporte' => ['prohibited'],
            'expulsado_en' => ['prohibited'],
        ]);
    }
}