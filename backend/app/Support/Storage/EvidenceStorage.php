<?php

namespace App\Support\Storage;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Acceso de lectura a las evidencias de los reportes (reporte_estudiante.evidencia_public_id).
 *
 * La ruta guardada es relativa y siempre cuelga de evidencias/. El archivo se busca en los discos
 * de sciem.evidencia_discos, en orden, y se sirve leyéndolo del disco: ni storage:link ni APP_URL
 * intervienen. Una ruta que salga de evidencias/ se trata como si no hubiera archivo.
 */
class EvidenceStorage
{
    public const DIRECTORY = 'evidencias';

    /** Tipos que el navegador puede mostrar sin riesgo; el resto (SVG, HTML...) se descarga. */
    private const INLINE_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'application/pdf'];

    /** Ruta normalizada si es segura (dentro de evidencias/, sin `..`), o null. */
    public function safePath(?string $path): ?string
    {
        if ($path === null || $path === '' || strlen($path) > 255 || preg_match('/[\x00-\x1F\x7F\\\\]/', $path)) {
            return null;
        }

        if (! str_starts_with($path, self::DIRECTORY . '/')) {
            return null;
        }

        $segments = explode('/', $path);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        return $path;
    }

    /**
     * Dónde está el archivo de la ruta indicada.
     *
     * @return array{disk: string, path: string}|null null si la ruta no es segura o no hay archivo
     */
    public function locate(?string $path): ?array
    {
        $safe = $this->safePath($path);

        if ($safe === null) {
            return null;
        }

        foreach ((array) config('sciem.evidencia_discos') as $disk) {
            try {
                if (Storage::disk($disk)->exists($safe)) {
                    return ['disk' => $disk, 'path' => $safe];
                }
            } catch (Throwable $error) {
                // Un disco mal configurado no debe tumbar la consulta: se prueba con el siguiente.
                report($error);
            }
        }

        return null;
    }

    /**
     * Nombre y tamaño del archivo que declara la ruta.
     *
     * @return array{nombre_archivo: string, tamano_bytes: int|null, disponible: bool}|null
     *         null cuando no hay evidencia declarada; tamano_bytes es null si el archivo no se encuentra
     */
    public function describe(?string $path): ?array
    {
        if ($path === null || $path === '') {
            return null;
        }

        $location = $this->locate($path);
        $size = $location === null ? null : (int) Storage::disk($location['disk'])->size($location['path']);

        return [
            'nombre_archivo' => basename($path),
            'tamano_bytes' => $size,
            'disponible' => $location !== null,
        ];
    }

    /**
     * Respuesta que transmite el archivo con su Content-Type y el nombre original en Content-Disposition.
     *
     * @param  array{disk: string, path: string}  $location
     */
    public function response(array $location): StreamedResponse
    {
        $disk = Storage::disk($location['disk']);
        $mime = $disk->mimeType($location['path']) ?: 'application/octet-stream';
        $disposition = in_array($mime, self::INLINE_TYPES, true) ? 'inline' : 'attachment';

        return $disk->response($location['path'], basename($location['path']), [
            'Content-Type' => $mime,
            'Content-Length' => $disk->size($location['path']),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ], $disposition);
    }
}
