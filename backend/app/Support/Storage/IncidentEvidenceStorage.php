<?php

namespace App\Support\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/** Almacena evidencia opcional en el disco público definido por el proyecto. */
class IncidentEvidenceStorage
{
    public function store(UploadedFile $file): array
    {
        $disk = Storage::disk('public');
        $path = $disk->putFile('reportes-estudiantes', $file);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('No se pudo guardar la evidencia.');
        }

        try {
            return ['path' => $path, 'url' => $disk->url($path)];
        } catch (Throwable $exception) {
            $disk->delete($path);
            throw $exception;
        }
    }

    public function delete(string $path): void
    {
        Storage::disk('public')->delete($path);
    }
}