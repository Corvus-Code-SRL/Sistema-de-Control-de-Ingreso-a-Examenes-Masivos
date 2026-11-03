<?php

namespace App\Support\Audit;

use Illuminate\Support\Str;

/**
 * Decide qué campos de la bitácora nunca salen de la API (contraseñas, tokens, claves, rutas de evidencia).
 *
 * La lista vive en config('sciem.bitacora.campos_ocultos'). Los nombres se comparan sin distinguir
 * mayúsculas, tildes ni separadores, de modo que `rememberToken`, `remember_token` y `REMEMBER-TOKEN` son
 * el mismo campo. Solo se aplica al leer: no cambia lo que AuditLogService escribe.
 */
final class AuditLogSanitizer
{
    public const HIDDEN = '(oculto)';

    /**
     * ¿El campo debe ocultarse? Las excepciones son indicadores booleanos (p. ej. debe_cambiar_contrasenia):
     * solo se muestran si el valor es un booleano o nulo; con cualquier otro valor se ocultan igual.
     *
     * @param  mixed  $value
     */
    public function isHidden(string $field, $value = null): bool
    {
        $name = $this->normalize($field);

        if ($name === '') {
            return false;
        }

        if (in_array($name, $this->names('excepciones'), true)) {
            return ! (is_bool($value) || $value === null);
        }

        if (in_array($name, $this->names('exactos'), true)) {
            return true;
        }

        foreach ($this->names('fragmentos') as $fragment) {
            if ($fragment !== '' && strpos($name, $fragment) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reemplaza por «(oculto)» el valor de cada campo oculto, a cualquier profundidad.
     *
     * @param  mixed  $value
     * @return mixed
     */
    public function redact($value)
    {
        if (! is_array($value)) {
            return $value;
        }

        $result = [];

        foreach ($value as $key => $item) {
            $result[$key] = is_string($key) && $this->isHidden($key, $item)
                ? self::HIDDEN
                : $this->redact($item);
        }

        return $result;
    }

    private function normalize(string $field): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', strtolower(Str::ascii($field)));
    }

    /** @return array<int, string> */
    private function names(string $list): array
    {
        $names = (array) config('sciem.bitacora.campos_ocultos.' . $list, []);

        return array_values(array_unique(array_map(
            fn ($name) => $this->normalize((string) $name),
            $names
        )));
    }
}
