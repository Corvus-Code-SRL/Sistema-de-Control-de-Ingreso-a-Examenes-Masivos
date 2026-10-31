import { useState, type FormEvent } from 'react'
import { CircleAlert, ShieldAlert } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { PasswordInput } from '@/components/common/PasswordInput'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Spinner } from '@/components/ui/spinner'
import { ApiError } from '@/lib/api-client'
import { cn } from '@/lib/utils'
import { LoginLayout } from '../components/LoginLayout'
import { useAuth } from '../hooks/useAuth'
import { LOGIN_PATH, homeForRole } from '../lib/destination'

/** Largo mínimo que el servidor exige a la contraseña nueva. */
export const MIN_PASSWORD_LENGTH = 8

type Field = 'actual' | 'nueva' | 'confirmacion'
type FieldErrors = Partial<Record<Field, string>>

const FIELD_IDS: Record<Field, string> = {
  actual: 'cambiar-contrasenia-actual',
  nueva: 'cambiar-contrasenia-nueva',
  confirmacion: 'cambiar-contrasenia-confirmacion',
}

const GENERIC_ERROR = 'No se pudo cambiar la contraseña. Intente nuevamente.'

function validate(actual: string, nueva: string, confirmacion: string): FieldErrors {
  const errors: FieldErrors = {}

  if (actual === '') errors.actual = 'Ingrese la contraseña temporal que le entregaron.'

  if (nueva.length < MIN_PASSWORD_LENGTH) {
    errors.nueva = `La contraseña nueva debe tener al menos ${MIN_PASSWORD_LENGTH} caracteres.`
  } else if (nueva === actual) {
    errors.nueva = 'La contraseña nueva debe ser distinta de la temporal.'
  }

  if (confirmacion !== nueva) errors.confirmacion = 'La confirmación no coincide con la contraseña nueva.'

  return errors
}

/** Campos que el servidor rechazó (422) o, si no es de validación, el mensaje general. */
function fromApiError(error: unknown): { fields: FieldErrors; general: string | null } {
  if (!(error instanceof ApiError)) return { fields: {}, general: GENERIC_ERROR }

  if (error.isValidation) {
    const fields: FieldErrors = {
      actual: error.errors.contrasenia_actual?.[0],
      nueva: error.errors.contrasenia_nueva?.[0],
    }

    return Object.values(fields).some(Boolean)
      ? { fields, general: null }
      : { fields: {}, general: error.message }
  }

  if (error.isTooManyRequests) {
    return { fields: {}, general: 'Demasiados intentos. Espere un minuto antes de volver a intentar.' }
  }

  return { fields: {}, general: error.message || GENERIC_ERROR }
}

/**
 * Cambio obligatorio de la contraseña temporal (HU-001).
 *
 * El router solo monta esta pantalla mientras la cuenta tenga el cambio pendiente y no deja
 * navegar a ninguna otra; la única salida alternativa es cerrar la sesión. Al guardarse, la sesión
 * se libera y se va a la entrada de su rol.
 */
export function CambiarContrasenaPage() {
  const { rol, cambiarContrasenia, cerrarSesion } = useAuth()
  const navigate = useNavigate()

  const [actual, setActual] = useState('')
  const [nueva, setNueva] = useState('')
  const [confirmacion, setConfirmacion] = useState('')
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({})
  const [general, setGeneral] = useState<string | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  const clearError = (field: Field) => {
    setGeneral(null)
    setFieldErrors((previous) => ({ ...previous, [field]: undefined }))
  }

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()

    if (isSubmitting) return

    const errors = validate(actual, nueva, confirmacion)

    if (Object.keys(errors).length > 0) {
      setFieldErrors(errors)
      setGeneral(null)

      return
    }

    setFieldErrors({})
    setGeneral(null)
    setIsSubmitting(true)

    try {
      await cambiarContrasenia({
        contrasenia_actual: actual,
        contrasenia_nueva: nueva,
        contrasenia_nueva_confirmation: confirmacion,
      })

      navigate(homeForRole(rol?.nombre_rol), { replace: true })
    } catch (error) {
      const failure = fromApiError(error)

      setFieldErrors(failure.fields)
      setGeneral(failure.general)

      // Solo se vacía lo que el servidor rechazó: un fallo de red no es culpa de lo escrito.
      if (failure.fields.actual) setActual('')

      if (failure.fields.nueva) {
        setNueva('')
        setConfirmacion('')
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  const logout = async () => {
    await cerrarSesion()
    navigate(LOGIN_PATH, { replace: true })
  }

  return (
    <LoginLayout>
      <form
        onSubmit={submit}
        noValidate
        aria-busy={isSubmitting}
        className="flex flex-1 flex-col gap-5 md:flex-none md:gap-6"
      >
        <div className="flex flex-col gap-1">
          <h1 className="text-2xl leading-8 font-semibold tracking-tight md:text-[28px] md:leading-9">
            Cambiar contraseña
          </h1>
          <p className="text-sm leading-5 text-muted-foreground">
            Su cuenta usa una contraseña temporal. Defina una nueva para continuar: no podrá usar SCIEM
            hasta hacerlo.
          </p>
        </div>

        <Alert className="border-warn-border bg-warn-soft text-warn-fg">
          <ShieldAlert aria-hidden="true" />
          <AlertDescription className="text-warn-fg">
            Elija una contraseña de al menos {MIN_PASSWORD_LENGTH} caracteres y distinta de la temporal.
          </AlertDescription>
        </Alert>

        {general && (
          <Alert variant="destructive" role="alert" className="border-danger-border bg-danger-soft text-danger-fg">
            <CircleAlert aria-hidden="true" />
            <AlertDescription className="text-danger-fg">{general}</AlertDescription>
          </Alert>
        )}

        <div className="flex flex-col gap-4">
          <PasswordField
            field="actual"
            label="Contraseña temporal"
            autoComplete="current-password"
            value={actual}
            error={fieldErrors.actual}
            readOnly={isSubmitting}
            onChange={(value) => {
              setActual(value)
              clearError('actual')
            }}
          />
          <PasswordField
            field="nueva"
            label="Contraseña nueva"
            autoComplete="new-password"
            value={nueva}
            error={fieldErrors.nueva}
            readOnly={isSubmitting}
            onChange={(value) => {
              setNueva(value)
              clearError('nueva')
            }}
          />
          <PasswordField
            field="confirmacion"
            label="Confirmar contraseña nueva"
            autoComplete="new-password"
            value={confirmacion}
            error={fieldErrors.confirmacion}
            readOnly={isSubmitting}
            onChange={(value) => {
              setConfirmacion(value)
              clearError('confirmacion')
            }}
          />
        </div>

        <div className="mt-auto flex flex-col gap-2 md:mt-0">
          <Button
            type="submit"
            disabled={isSubmitting}
            className={cn('h-12 w-full text-base font-semibold md:h-10 md:text-sm', isSubmitting && 'disabled:opacity-70')}
          >
            {isSubmitting ? (
              <>
                <Spinner aria-hidden="true" role="presentation" />
                Guardando…
              </>
            ) : (
              'Cambiar contraseña'
            )}
          </Button>

          <Button
            type="button"
            variant="outline"
            onClick={logout}
            disabled={isSubmitting}
            className="h-12 w-full text-base font-semibold md:h-10 md:text-sm"
          >
            Cerrar sesión
          </Button>
        </div>
      </form>
    </LoginLayout>
  )
}

interface PasswordFieldProps {
  field: Field
  label: string
  autoComplete: string
  value: string
  error?: string
  readOnly: boolean
  onChange: (value: string) => void
}

function PasswordField({ field, label, autoComplete, value, error, readOnly, onChange }: PasswordFieldProps) {
  const id = FIELD_IDS[field]
  const errorId = `${id}-error`

  return (
    <div className="flex flex-col gap-1.5">
      <Label htmlFor={id} className="leading-5">
        {label} <span aria-hidden="true" className="text-danger">*</span>
      </Label>

      <PasswordInput
        id={id}
        name={id}
        autoComplete={autoComplete}
        autoCapitalize="off"
        autoCorrect="off"
        spellCheck={false}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        readOnly={readOnly}
        aria-required="true"
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? errorId : undefined}
      />

      {error && (
        <p id={errorId} className="flex gap-1.5 text-[13px] leading-[18px] font-medium text-danger-fg">
          <CircleAlert aria-hidden="true" className="mt-px size-4 flex-none" />
          {error}
        </p>
      )}
    </div>
  )
}
