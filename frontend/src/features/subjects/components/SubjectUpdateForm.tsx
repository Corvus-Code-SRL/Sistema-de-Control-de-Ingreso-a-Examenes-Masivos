import { useState, type FormEvent } from 'react'
import { AlertOctagon } from 'lucide-react'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useUpdateSubject } from '../hooks/useUpdateSubject'
import type {
  AdminSubjectSummary,
  SubjectData,
  UpdateSubjectFormErrors,
} from '../types/subject.types'

interface SubjectUpdateFormProps {
  subject: AdminSubjectSummary
  onCancel: () => void
  onUpdated: (subject: SubjectData) => void
}

/** Formulario de edición de nombre y código de una materia institucional. */
export function SubjectUpdateForm({
  subject,
  onCancel,
  onUpdated,
}: SubjectUpdateFormProps) {
  const { status, error, submit, reset } = useUpdateSubject()

  const [nombre, setNombre] = useState(subject.nombre)
  const [codigo, setCodigo] = useState(subject.codigo)
  const [fieldErrors, setFieldErrors] =
    useState<UpdateSubjectFormErrors>({})

  const isSubmitting = status === 'submitting'

  function validate(): boolean {
    const errors: UpdateSubjectFormErrors = {}

    const normalizedName = nombre.trim()
    const normalizedCode = codigo.trim()

    if (normalizedName.length === 0) {
      errors.nombre = 'Debe indicarse el nombre de la materia.'
    } else if (normalizedName.length > 50) {
      errors.nombre =
        'El nombre de la materia no puede superar los 50 caracteres.'
    }

    if (normalizedCode.length === 0) {
      errors.codigo = 'Debe indicarse el código de la materia.'
    } else if (!/^[0-9]{7}$/.test(normalizedCode)) {
      errors.codigo = 'El código debe contener exactamente 7 dígitos.'
    }

    setFieldErrors(errors)

    return Object.keys(errors).length === 0
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()

    if (!validate()) {
      return
    }

    const result = await submit(subject.id_materia, {
      nombre: nombre.trim(),
      codigo: codigo.trim(),
    })

    if (result !== null) {
      onUpdated(result)
    }
  }

  const backendNombreError = error?.isValidation
    ? error.errors.nombre?.[0]
    : undefined

  const backendCodigoError = error?.isValidation
    ? error.errors.codigo?.[0]
    : undefined

  const nombreError = fieldErrors.nombre ?? backendNombreError
  const codigoError = fieldErrors.codigo ?? backendCodigoError

  const totalErrors = [nombreError, codigoError].filter(Boolean).length
  const hasError =
    totalErrors > 0 || (error !== null && !error.isValidation)

  function handleNombreChange(value: string) {
    setNombre(value)

    if (fieldErrors.nombre) {
      setFieldErrors((current) => ({
        ...current,
        nombre: undefined,
      }))
    }

    if (error !== null) {
      reset()
    }
  }

  function handleCodigoChange(value: string) {
    setCodigo(value)

    if (fieldErrors.codigo) {
      setFieldErrors((current) => ({
        ...current,
        codigo: undefined,
      }))
    }

    if (error !== null) {
      reset()
    }
  }

  return (
    <form
      onSubmit={handleSubmit}
      noValidate
      className="flex flex-col gap-5"
    >
      {hasError ? (
        <Alert className="border-[#A21B12]/20 bg-[#FDE2E1] text-[#A21B12]">
          <AlertOctagon
            className="size-4 shrink-0"
            aria-hidden="true"
          />

          <AlertDescription className="text-sm font-medium text-[#A21B12]">
            {error !== null && !error.isValidation
              ? error.message
              : `Revise ${totalErrors} ${
                  totalErrors === 1 ? 'campo' : 'campos'
                } antes de guardar los cambios.`}
          </AlertDescription>
        </Alert>
      ) : (
        <Alert variant="info">
          <AlertDescription>
            Solo se modificarán el nombre y el código de la materia.
            Sus grupos, exámenes y relaciones existentes se conservarán.
          </AlertDescription>
        </Alert>
      )}

      <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div className="flex flex-col gap-2">
          <Label htmlFor="nombre">
            Nombre de la materia{' '}
            <span className="text-destructive" aria-hidden="true">
              *
            </span>
          </Label>

          <Input
            id="nombre"
            value={nombre}
            maxLength={50}
            disabled={isSubmitting}
            aria-invalid={Boolean(nombreError)}
            onChange={(event) =>
              handleNombreChange(event.target.value)
            }
          />

          {nombreError && (
            <p className="flex items-center gap-1 text-xs text-[#A21B12]">
              <AlertOctagon
                className="size-3.5 shrink-0"
                aria-hidden="true"
              />
              <span>{nombreError}</span>
            </p>
          )}
        </div>

        <div className="flex flex-col gap-2">
          <Label htmlFor="codigo">
            Código{' '}
            <span className="text-destructive" aria-hidden="true">
              *
            </span>
          </Label>

          <Input
            id="codigo"
            value={codigo}
            inputMode="numeric"
            maxLength={7}
            disabled={isSubmitting}
            aria-invalid={Boolean(codigoError)}
            onChange={(event) =>
              handleCodigoChange(event.target.value)
            }
          />

          {codigoError ? (
            <p className="flex items-center gap-1 text-xs text-[#A21B12]">
              <AlertOctagon
                className="size-3.5 shrink-0"
                aria-hidden="true"
              />
              <span>{codigoError}</span>
            </p>
          ) : (
            <p className="text-xs text-muted-foreground">
              Debe contener exactamente 7 dígitos y ser único en el catálogo.
            </p>
          )}
        </div>
      </div>

      <div className="flex justify-end gap-2 border-t pt-4">
        <Button
          type="button"
          variant="secondary"
          disabled={isSubmitting}
          onClick={onCancel}
        >
          Cancelar
        </Button>

        <Button
          type="submit"
          disabled={isSubmitting}
        >
          {isSubmitting ? 'Guardando…' : 'Guardar cambios'}
        </Button>
      </div>
    </form>
  )
}