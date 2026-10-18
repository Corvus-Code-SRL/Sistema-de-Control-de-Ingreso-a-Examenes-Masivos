<?php

namespace App\Http\Requests\RiskCenter\Academic;

/** Listado paginado de la Central de Riesgo, con búsqueda por estudiante y filtro por tipo de falta. */
class IndexRiskCenterRecordRequest extends RiskCenterRequest
{
    public const DEFAULT_PER_PAGE = 10;

    private const MAX_PER_PAGE = 50;

    // Un OFFSET desbordado haría fallar la consulta: se acota la página que se puede pedir.
    private const MAX_PAGE = 100000;

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:50'],
            'tipo' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_ID],
            'page' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_PAGE],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_PER_PAGE],
        ];
    }

    public function attributes(): array
    {
        return [
            'q' => 'búsqueda',
            'tipo' => 'tipo de falta',
            'page' => 'página',
            'per_page' => 'registros por página',
        ];
    }

    public function search(): ?string
    {
        return $this->validated()['q'] ?? null;
    }

    public function faultTypeId(): ?int
    {
        $type = $this->validated()['tipo'] ?? null;

        return $type === null ? null : (int) $type;
    }

    public function page(): int
    {
        return (int) ($this->validated()['page'] ?? 1);
    }

    public function perPage(): int
    {
        return (int) ($this->validated()['per_page'] ?? self::DEFAULT_PER_PAGE);
    }
}
