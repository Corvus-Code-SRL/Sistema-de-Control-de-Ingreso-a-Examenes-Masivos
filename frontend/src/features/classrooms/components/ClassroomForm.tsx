import { FormEvent, useState } from 'react'
import { Loader2, Plus } from 'lucide-react'
import { ApiError } from '@/lib/api-client'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { createClassroom } from '../services/classroomsService'
import type {
  Classroom,
  ClassroomFormState,
  ClassroomValidationErrors
} from '../types/classroom.types'

interface ClassroomFormProps {
  onCreated: (classroom: Classroom) => void
}

const INITIAL_FORM: ClassroomFormState = {
  nro_aula: '',
  capacidad: '',
  ubicacion: ''
}

export function ClassroomForm({ onCreated }: ClassroomFormProps) {
  const [form, setForm] = useState<ClassroomFormState>(INITIAL_FORM)
  const [errors, setErrors] = useState<ClassroomValidationErrors>({})
  const [generalError, setGeneralError] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  function updateField(field: keyof ClassroomFormState, value: string) {
    setForm((current) => ({
      ...current,
      [field]: value
    }))

    setErrors((current) => ({
      ...current,
      [field]: undefined
    }))

    setGeneralError(null)
  }

  function validate(): boolean {
    const newErrors: ClassroomValidationErrors = {}

    if (!form.nro_aula.trim()) {
      newErrors.nro_aula = ['El nombre del ambiente es obligatorio.']
    }

    if (!form.capacidad.trim()) {
      newErrors.capacidad = ['La capacidad es obligatoria.']
    } else {
      const capacity = Number(form.capacidad)

      if (!Number.isInteger(capacity) || capacity <= 0) {
        newErrors.capacidad = ['La capacidad debe ser un número entero mayor a cero.']
      }
    }

    if (!form.ubicacion.trim()) {
      newErrors.ubicacion = ['La ubicación es obligatoria.']
    }

    setErrors(newErrors)

    return Object.keys(newErrors).length === 0
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()

    if (!validate()) {
      return
    }

    setIsSubmitting(true)
    setGeneralError(null)

    try {
      const classroom = await createClassroom(form)

      setForm(INITIAL_FORM)
      setErrors({})
      onCreated(classroom)
    } catch (error) {
      if (error instanceof ApiError && error.isValidation) {
        setErrors(error.errors as ClassroomValidationErrors)
      } else if (error instanceof ApiError) {
        setGeneralError(error.message)
      } else {
        setGeneralError('No se pudo registrar el ambiente. Intente nuevamente.')
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-5">
      <div className="space-y-2">
        <Label htmlFor="nro_aula">
          Nombre del ambiente <span aria-hidden="true">*</span>
        </Label>

        <Input
          id="nro_aula"
          name="nro_aula"
          type="text"
          value={form.nro_aula}
          onChange={(event) => updateField('nro_aula', event.target.value)}
          placeholder="Ej. Aula 101"
          maxLength={50}
          disabled={isSubmitting}
          aria-invalid={Boolean(errors.nro_aula)}
        />

        {errors.nro_aula?.[0] && <p className="text-sm text-destructive">{errors.nro_aula[0]}</p>}
      </div>

      <div className="space-y-2">
        <Label htmlFor="capacidad">
          Capacidad <span aria-hidden="true">*</span>
        </Label>

        <Input
          id="capacidad"
          name="capacidad"
          type="number"
          min="1"
          step="1"
          value={form.capacidad}
          onChange={(event) => updateField('capacidad', event.target.value)}
          placeholder="Ej. 30"
          disabled={isSubmitting}
          aria-invalid={Boolean(errors.capacidad)}
        />

        {errors.capacidad?.[0] && <p className="text-sm text-destructive">{errors.capacidad[0]}</p>}
      </div>

      <div className="space-y-2">
        <Label htmlFor="ubicacion">
          Ubicación <span aria-hidden="true">*</span>
        </Label>

        <Input
          id="ubicacion"
          name="ubicacion"
          type="text"
          value={form.ubicacion}
          onChange={(event) => updateField('ubicacion', event.target.value)}
          placeholder="Ej. Módulo A, primer piso"
          maxLength={255}
          disabled={isSubmitting}
          aria-invalid={Boolean(errors.ubicacion)}
        />

        {errors.ubicacion?.[0] && <p className="text-sm text-destructive">{errors.ubicacion[0]}</p>}
      </div>

      {generalError && (
        <div
          role="alert"
          className="rounded-md border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
        >
          {generalError}
        </div>
      )}

      <Button type="submit" disabled={isSubmitting}>
        {isSubmitting ? (
          <>
            <Loader2 className="animate-spin" />
            Registrando...
          </>
        ) : (
          <>
            <Plus />
            Registrar ambiente
          </>
        )}
      </Button>
    </form>
  )
}
