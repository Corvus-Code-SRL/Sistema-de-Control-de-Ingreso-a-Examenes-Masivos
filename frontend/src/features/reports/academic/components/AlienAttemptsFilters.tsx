import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { FormSelect } from '@/components/common/FormSelect'
import type { Faculty } from '../types/alienAttempts.types'

const ALL_FACULTIES = 'todas'

interface AlienAttemptsFiltersProps {
  desde: string
  hasta: string
  faculty: number | null
  faculties: Faculty[]
  facultiesUnavailable: boolean
  rangeError: string | null
  onDesdeChange: (value: string) => void
  onHastaChange: (value: string) => void
  onFacultyChange: (value: number | null) => void
  onGenerate: () => void
  generating: boolean
}

/** Filtros del reporte: rango de fechas, facultad y botón «Generar». */
export function AlienAttemptsFilters({
  desde,
  hasta,
  faculty,
  faculties,
  facultiesUnavailable,
  rangeError,
  onDesdeChange,
  onHastaChange,
  onFacultyChange,
  onGenerate,
  generating,
}: AlienAttemptsFiltersProps) {
  return (
    <div className="flex flex-col gap-3 lg:flex-row lg:items-end">
      <div className="flex flex-col gap-1.5">
        <Label htmlFor="desde">Desde</Label>
        <Input
          id="desde"
          type="date"
          value={desde}
          max={hasta}
          onChange={(event) => onDesdeChange(event.target.value)}
          aria-invalid={rangeError !== null}
          className="h-10 w-full sm:w-44"
        />
      </div>

      <div className="flex flex-col gap-1.5">
        <Label htmlFor="hasta">Hasta</Label>
        <Input
          id="hasta"
          type="date"
          value={hasta}
          min={desde}
          onChange={(event) => onHastaChange(event.target.value)}
          aria-invalid={rangeError !== null}
          className="h-10 w-full sm:w-44"
        />
      </div>

      <div className="flex flex-col gap-1.5 sm:w-56">
        <Label htmlFor="facultad">Facultad</Label>
        <FormSelect
          id="facultad"
          ariaLabel="Facultad"
          className="!h-10"
          value={faculty === null ? ALL_FACULTIES : String(faculty)}
          placeholder={facultiesUnavailable ? 'Facultades no disponibles' : 'Todas'}
          disabled={facultiesUnavailable}
          options={[
            { value: ALL_FACULTIES, label: 'Todas' },
            ...faculties.map((f) => ({ value: String(f.id_facultad), label: f.nombre })),
          ]}
          onValueChange={(value) => onFacultyChange(value === ALL_FACULTIES ? null : Number(value))}
        />
      </div>

      <div className="flex flex-col gap-1.5">
        <span aria-hidden="true" className="text-sm font-medium text-transparent select-none">
          Generar
        </span>
        <Button
          onClick={onGenerate}
          disabled={rangeError !== null || generating}
          className="h-10"
        >
          Generar
        </Button>
      </div>

      {rangeError && (
        <p
          role="alert"
          className="text-sm font-medium text-[color:var(--danger-fg)] lg:ml-2 lg:self-end lg:pb-2.5"
        >
          {rangeError}
        </p>
      )}
    </div>
  )
}