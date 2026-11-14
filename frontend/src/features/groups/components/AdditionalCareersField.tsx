import { AlertOctagon } from 'lucide-react'
import type { GroupCareer } from '../types/group.types'

interface AdditionalCareersFieldProps {
  /** Carreras que se pueden sumar al grupo: ofertan la misma materia en el período. */
  options: GroupCareer[]
  selected: number[]
  onChange: (selected: number[]) => void
  /** Nombre de la carrera principal, que siempre está y no se puede quitar. */
  principalName: string
  disabled?: boolean
  error?: string
}

/**
 * Carreras adicionales de un grupo (HU-021, multicarrera): un grupo atiende a su carrera principal
 * y, si se marcan, a otras que ofertan la misma materia. Sin opciones no se dibuja nada.
 */
export function AdditionalCareersField({
  options,
  selected,
  onChange,
  principalName,
  disabled = false,
  error,
}: AdditionalCareersFieldProps) {
  if (options.length === 0) {
    return null
  }

  function toggle(careerId: number, checked: boolean): void {
    onChange(
      checked ? [...selected, careerId] : selected.filter((current) => current !== careerId)
    )
  }

  return (
    <fieldset className="flex flex-col gap-1.5" disabled={disabled}>
      <legend className="text-xs font-medium text-foreground">Carreras que atiende el grupo</legend>

      <p className="text-xs text-muted-foreground leading-normal">
        {principalName} siempre está incluida. Marque las otras carreras cuyos estudiantes cursan en
        este grupo.
      </p>

      <div className="flex flex-col gap-1.5 pt-0.5">
        {options.map((career) => {
          const checkboxId = `carrera-adicional-${career.id_carrera}`

          return (
            <label
              key={career.id_carrera}
              htmlFor={checkboxId}
              className="flex cursor-pointer items-center gap-2 rounded-md border border-input bg-card px-3 py-2 text-xs text-foreground"
            >
              <input
                id={checkboxId}
                type="checkbox"
                className="size-4 accent-brand"
                checked={selected.includes(career.id_carrera)}
                onChange={(event) => toggle(career.id_carrera, event.target.checked)}
              />
              <span>{career.nombre}</span>
            </label>
          )
        })}
      </div>

      {error && (
        <p className="flex items-center gap-1 text-xs text-danger-fg mt-0.5">
          <AlertOctagon className="size-3.5 shrink-0" aria-hidden="true" />
          <span>{error}</span>
        </p>
      )}
    </fieldset>
  )
}
