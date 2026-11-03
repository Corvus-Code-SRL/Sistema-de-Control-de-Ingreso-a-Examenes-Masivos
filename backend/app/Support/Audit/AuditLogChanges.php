<?php

namespace App\Support\Audit;

/**
 * Compara el antes y el después de una operación de la bitácora campo por campo.
 *
 * - Con antes y después: solo los campos que cambiaron.
 * - Sin antes (alta): los campos creados, con «anterior» vacío.
 * - Sin después (baja): los campos eliminados, con «nuevo» vacío.
 *
 * Un campo oculto sale como {campo, etiqueta, oculto: true} y nunca lleva valores; si un campo visible
 * contiene otros ocultos anidados, se reemplazan por «(oculto)».
 */
final class AuditLogChanges
{
    private AuditLogSanitizer $sanitizer;

    public function __construct(AuditLogSanitizer $sanitizer)
    {
        $this->sanitizer = $sanitizer;
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @return array<int, array<string, mixed>>
     */
    public function between(?array $before, ?array $after): array
    {
        $before = $before ?: [];
        $after = $after ?: [];
        $comparing = $before !== [] && $after !== [];

        $changes = [];

        foreach ($this->fields($before, $after) as $field) {
            $old = $before[$field] ?? null;
            $new = $after[$field] ?? null;

            if ($comparing && $old === $new) {
                continue;
            }

            if ($this->sanitizer->isHidden($field, $old) || $this->sanitizer->isHidden($field, $new)) {
                $changes[] = ['campo' => $field, 'etiqueta' => $this->label($field), 'oculto' => true];

                continue;
            }

            $changes[] = [
                'campo' => $field,
                'etiqueta' => $this->label($field),
                'oculto' => false,
                'anterior' => $this->sanitizer->redact($old),
                'nuevo' => $this->sanitizer->redact($new),
            ];
        }

        return $changes;
    }

    /** @return array<int, string> */
    private function fields(array $before, array $after): array
    {
        return array_map('strval', array_values(array_unique(array_merge(array_keys($before), array_keys($after)))));
    }

    private function label(string $field): string
    {
        $labels = (array) config('sciem.bitacora.etiquetas_campo', []);

        if (isset($labels[$field])) {
            return (string) $labels[$field];
        }

        $text = strpos($field, 'id_') === 0
            ? 'identificador de ' . substr($field, 3)
            : $field;

        return ucfirst(str_replace('_', ' ', $text));
    }
}
