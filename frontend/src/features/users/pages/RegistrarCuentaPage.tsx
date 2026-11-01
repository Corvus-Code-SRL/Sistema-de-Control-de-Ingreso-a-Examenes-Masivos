import { useEffect, useState } from 'react'
import { AlertTriangle, ArrowLeft, ArrowRight, Check, Info } from 'lucide-react'
import { useLocation, useNavigate } from 'react-router-dom'
import { PageHeader } from '@/components/common/PageHeader'
import { AppShell } from '@/components/layout/AppShell'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { ApiError } from '@/lib/api-client'
import { PersonalStep } from '../components/PersonalStep'
import { RegistrationSummary } from '../components/RegistrationSummary'
import { RoleOptions } from '../components/RoleOptions'
import { SisCodeStep, type GuestCodeStatus } from '../components/SisCodeStep'
import { WizardSteps } from '../components/WizardSteps'
import { useRoles } from '../hooks/useRoles'
import { useSisCodeCheck } from '../hooks/useSisCodeCheck'
import { rolesAllowedFor } from '../lib/accountRoles'
import {
  EMPTY_DRAFT,
  isPersonalValid,
  toPayload,
  type DraftErrors,
  type DraftField,
  type RegistrationDraft,
} from '../lib/registrationDraft'
import { roleCodeMismatch } from '../lib/sisCode'
import { getGuestCode, registerUser } from '../services/usersService'
import { GUEST_ROLE, type Role, type SisPerson, type UserAccount } from '../types/users.types'

type Step = 1 | 2 | 3 | 'done'

const FIELD_NAMES: DraftField[] = [
  'cod_sis',
  'nombre',
  'apellido_paterno',
  'apellido_materno',
  'correo',
  'contrasenia_temporal',
  'id_rol',
]

const PERSONAL_FIELDS: DraftField[] = [
  'nombre',
  'apellido_paterno',
  'apellido_materno',
  'correo',
  'contrasenia_temporal',
]

const GUEST_STEPS = ['Código', 'Datos personales'] as const
const SIS_STEPS = ['Código SIS', 'Datos personales', 'Rol'] as const

const STEP_TITLES: Record<1 | 2 | 3, string> = {
  1: 'Código SIS',
  2: 'Datos personales',
  3: 'Rol de la cuenta',
}

/** Errores que el servidor rechazó, repartidos por campo; el resto cae en el aviso general. */
function serverErrors(cause: ApiError): DraftErrors {
  const errors: DraftErrors = {}

  FIELD_NAMES.forEach((field) => {
    const message = cause.errors[field]?.[0]

    if (message) errors[field] = message
  })

  return errors
}

/**
 * Registrar cuenta nueva (HU-001): asistente de 3 pasos (2 para un docente invitado) y resumen.
 *
 * 1. Código SIS (o código INV de un docente invitado), revisado contra las cuentas existentes.
 * 2. Datos personales y contraseña temporal.
 * 3. Rol, solo para cuentas con código SIS; también puede quedar sin rol.
 *
 * La cuenta se crea con su rol en una sola operación del servidor. El rol de una cuenta que ya existe
 * se cambia desde «Modificar rol».
 */
export function RegistrarCuentaPage() {
  const navigate = useNavigate()
  const location = useLocation()
  const roles = useRoles()

  const [step, setStep] = useState<Step>(1)
  const [draft, setDraft] = useState<RegistrationDraft>(EMPTY_DRAFT)
  const [selectedRoleId, setSelectedRoleId] = useState<number | null>(null)
  const [codeServerError, setCodeServerError] = useState<string | null>(null)
  const [personalErrors, setPersonalErrors] = useState<DraftErrors>({})
  const [roleError, setRoleError] = useState<string | null>(null)
  const [generalError, setGeneralError] = useState<string | null>(null)
  const [guestNotice, setGuestNotice] = useState<string | null>(null)
  const [guestLoadFailed, setGuestLoadFailed] = useState(false)
  const [guestAttempt, setGuestAttempt] = useState(0)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [created, setCreated] = useState<UserAccount | null>(null)

  const catalog = roles.data ?? []
  const guestRole = catalog.find((role) => role.nombre_rol === GUEST_ROLE)

  const update = (changes: Partial<RegistrationDraft>) =>
    setDraft((current) => ({ ...current, ...changes }))

  const prefill = (person: SisPerson) =>
    update({ nombre: person.nombre, apellidoPaterno: person.paterno, apellidoMaterno: person.materno ?? '' })

  const check = useSisCodeCheck(draft.codSis, !draft.isGuest, prefill)

  const needsGuestCode = draft.isGuest && draft.guestCode === ''

  useEffect(() => {
    if (!needsGuestCode) return

    const controller = new AbortController()

    getGuestCode(controller.signal)
      .then((code) => setDraft((current) => ({ ...current, guestCode: code })))
      .catch(() => {
        if (!controller.signal.aborted) setGuestLoadFailed(true)
      })

    return () => controller.abort()
  }, [needsGuestCode, guestAttempt])

  const guestCodeStatus: GuestCodeStatus =
    draft.guestCode !== '' ? 'ready' : guestLoadFailed ? 'error' : 'loading'

  const stepLabels = draft.isGuest ? GUEST_STEPS : SIS_STEPS
  const lastStep: 2 | 3 = draft.isGuest ? 2 : 3

  const assignableRoles: Role[] = rolesAllowedFor(catalog, { cod_sis: draft.codSis, es_invitado: false })
  const unavailableReason = (role: Role) => roleCodeMismatch(draft.codSis, role.nombre_rol)
  const chosenRoleId =
    assignableRoles.some((role) => role.id_rol === selectedRoleId && unavailableReason(role) === null)
      ? selectedRoleId
      : null

  const canContinueFromCode = draft.isGuest
    ? guestCodeStatus === 'ready'
    : (check.status === 'available' || check.status === 'sis-unavailable') && codeServerError === null

  const changeCode = (code: string) => {
    update({ codSis: code })
    setCodeServerError(null)
  }

  const toggleGuest = (isGuest: boolean) => {
    update({ isGuest })
    setSelectedRoleId(null)
    setCodeServerError(null)
    setGuestNotice(null)
    setGeneralError(null)
    setGuestLoadFailed(false)
  }

  const retryGuestCode = () => {
    setGuestLoadFailed(false)
    setGuestAttempt((attempt) => attempt + 1)
  }

  const clearPersonalError = (field: DraftField) =>
    setPersonalErrors((current) => ({ ...current, [field]: undefined }))

  const goBack = () => {
    setGeneralError(null)

    if (step === 1) {
      // Sin historial previo (la página se abrió directo), «Volver» lleva a la lista de cuentas.
      if (location.key === 'default') navigate('/cuentas/roles')
      else navigate(-1)

      return
    }

    if (step === 2 || step === 3) setStep(step === 3 ? 2 : 1)
  }

  const reject = (cause: unknown) => {
    if (!(cause instanceof ApiError)) {
      setGeneralError('No se pudo registrar la cuenta. Intente nuevamente.')

      return
    }

    if (cause.status === 409 && cause.codigoSugerido) {
      const taken = draft.guestCode

      update({ guestCode: cause.codigoSugerido })
      setGuestNotice(
        `El código ${taken} ya fue asignado a otra cuenta. Se usará ${cause.codigoSugerido}: revíselo y registre la cuenta.`
      )

      return
    }

    if (!cause.isValidation) {
      setGeneralError(cause.message)

      return
    }

    const rejected = serverErrors(cause)
    const personal = PERSONAL_FIELDS.filter((field) => rejected[field])

    if (rejected.cod_sis && !draft.isGuest) {
      // El código en sí no sirve (formato o ya tiene cuenta): se corrige en el paso 1.
      setCodeServerError(rejected.cod_sis)
      setStep(1)
    } else if (personal.length > 0) {
      setPersonalErrors(Object.fromEntries(personal.map((field) => [field, rejected[field]])))
      setGeneralError('Corrija los campos marcados para registrar la cuenta.')
      setStep(2)
    } else if (rejected.id_rol && !draft.isGuest) {
      // La combinación código-rol no sirve: se elige otro rol aquí mismo, sin tocar el código.
      setRoleError(rejected.id_rol)
      setStep(3)
    } else {
      setGeneralError(rejected.id_rol ?? rejected.cod_sis ?? cause.message)
    }
  }

  const submit = async (roleId: number | null) => {
    if (draft.isGuest && !guestRole) {
      setGeneralError('No se encontró el rol de docente invitado. Recargue la página e intente nuevamente.')

      return
    }

    setIsSubmitting(true)
    setGeneralError(null)
    setGuestNotice(null)
    setRoleError(null)
    setPersonalErrors({})

    try {
      setCreated(await registerUser(toPayload(draft, catalog, roleId)))
      setStep('done')
    } catch (cause) {
      reject(cause)
    } finally {
      setIsSubmitting(false)
    }
  }

  const registerAnother = () => {
    setDraft(EMPTY_DRAFT)
    setSelectedRoleId(null)
    setCodeServerError(null)
    setPersonalErrors({})
    setRoleError(null)
    setGeneralError(null)
    setGuestNotice(null)
    setGuestLoadFailed(false)
    setCreated(null)
    setStep(1)
  }

  return (
    <AppShell
      mobileTitle="Registrar cuenta"
      breadcrumbs={[{ label: 'Administración' }, { label: 'Registrar cuenta nueva' }]}
    >
      <PageHeader
        title="Registrar cuenta nueva"
        subtitle="Cree la cuenta de una persona con su contraseña temporal y su rol."
      />

      <Card>
        <CardContent className="flex flex-col gap-5">
          {step !== 'done' && (
            <>
              <WizardSteps steps={stepLabels} current={step} />

              <h2 className="sciem-h2">{STEP_TITLES[step]}</h2>

              {generalError && (
                <Alert variant="destructive">
                  <AlertTriangle aria-hidden="true" />
                  <AlertDescription>{generalError}</AlertDescription>
                </Alert>
              )}

              {guestNotice && (
                <Alert variant="info">
                  <Info aria-hidden="true" />
                  <AlertDescription>{guestNotice}</AlertDescription>
                </Alert>
              )}
            </>
          )}

          {step === 1 && (
            <SisCodeStep
              draft={draft}
              check={check}
              serverError={codeServerError}
              guestCodeStatus={guestCodeStatus}
              onCodeChange={changeCode}
              onGuestChange={toggleGuest}
              onRetryGuestCode={retryGuestCode}
            />
          )}

          {step === 2 && (
            <PersonalStep
              draft={draft}
              serverErrors={personalErrors}
              onChange={update}
              onClearServerError={clearPersonalError}
            />
          )}

          {step === 3 && (
            <div className="grid gap-3">
              <RoleOptions
                roles={{ ...roles, data: roles.data ? assignableRoles : null }}
                legend="Rol de la cuenta"
                selectedRoleId={chosenRoleId}
                disabled={isSubmitting}
                unavailableReason={unavailableReason}
                onSelect={(roleId) => {
                  setSelectedRoleId(roleId)
                  setRoleError(null)
                }}
              />

              {roleError && (
                <p role="alert" className="text-sm font-medium text-danger">
                  {roleError}
                </p>
              )}
            </div>
          )}

          {step !== 'done' && (
            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
              <Button variant="outline" onClick={goBack} disabled={isSubmitting}>
                <ArrowLeft aria-hidden="true" />
                Volver
              </Button>

              <div className="flex flex-col-reverse gap-2 sm:flex-row">
                {step === 3 && (
                  <Button variant="outline" onClick={() => submit(null)} disabled={isSubmitting}>
                    Continuar sin rol
                  </Button>
                )}

                {step === lastStep ? (
                  <Button
                    onClick={() => submit(chosenRoleId)}
                    disabled={
                      isSubmitting ||
                      (step === 2 && !isPersonalValid(draft)) ||
                      (step === 3 && chosenRoleId === null)
                    }
                  >
                    <Check aria-hidden="true" />
                    {isSubmitting ? 'Registrando…' : 'Registrar cuenta'}
                  </Button>
                ) : (
                  <Button
                    onClick={() => setStep(step === 1 ? 2 : 3)}
                    disabled={step === 1 ? !canContinueFromCode : !isPersonalValid(draft)}
                  >
                    Siguiente
                    <ArrowRight aria-hidden="true" />
                  </Button>
                )}
              </div>
            </div>
          )}

          {step === 'done' && created && (
            <RegistrationSummary account={created} onRegisterAnother={registerAnother} />
          )}
        </CardContent>
      </Card>
    </AppShell>
  )
}
