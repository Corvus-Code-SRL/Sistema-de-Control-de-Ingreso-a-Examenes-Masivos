<?php

namespace App\Support\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Guarda evidencia privada con la ruta relativa que consulta la Central de Riesgo. */
class IncidentEvidenceStorage
{
    public function store(UploadedFile $file): string
    {
        $path = Storage::disk('local')->putFile('evidencias/reportes', $file, ['visibility' => 'private']);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('No se pudo guardar la evidencia.');
        }

        return $path;
    }

    public function delete(string $path): void
    {
        Storage::disk('local')->delete($path);
    }
}
