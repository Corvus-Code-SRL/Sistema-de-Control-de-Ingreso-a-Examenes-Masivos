<?php

namespace App\Support\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Guarda la imagen que el docente adjunta al rechazar una solicitud de rectificación (HU-062).
 *
 * Queda en el disco privado bajo evidencias/rectificaciones, con la ruta relativa que {@see EvidenceStorage}
 * ubica y sirve igual que la evidencia de los reportes.
 */
class RectificationEvidenceStorage
{
    public const DIRECTORY = EvidenceStorage::DIRECTORY . '/rectificaciones';

    public function store(UploadedFile $file): string
    {
        $path = Storage::disk('local')->putFile(self::DIRECTORY, $file, ['visibility' => 'private']);

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
