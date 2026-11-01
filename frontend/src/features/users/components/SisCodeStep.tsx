import { AlertTriangle, CheckCircle, Info, Loader2 } from 'lucide-react'
import { Link } from 'react-router-dom'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import type { CodeCheck } from '../hooks/useSisCodeCheck'
import type { RegistrationDraft } from '../lib/registrationDraft'
import { FormField } from './FormField'

export type GuestCodeStatus = 'loading' | 'ready' | 'error'

interface SisCodeStepProps {
  draft: RegistrationDraft
  check: CodeCheck
  /** Rechazo del servidor sobre el código al registrar (formato o duplicado); se borra al editarlo. */
  serverError: string | null
  guestCodeStatus: GuestCodeStatus
  onCodeChange: (code: string) => void
  onGuestChange: (isGuest: boolean) => void
  onRetryGuestCode: () => void
}

export const SIS_UNAVAILABLE_NOTICE = 'No se pudo consultar el SIS; complete los datos manualmente.'

/**
 * Paso 1 del registro: código SIS de la persona o, si no lo tiene, el código INV que se le asignará.
 *
 * El código se revisa contra las cuentas de SCIEM (¿ya existe?) y contra el formato; el SIS solo
 * ayuda a precargar los nombres.
 */
export function SisCodeStep({
  draft,
  check,
  serverError,
  guestCodeStatus,
  onCodeChange,
  onGuestChange,
  onRetryGuestCode,
}: SisCodeStepProps) {
  const shownError = draft.isGuest ? null : (serverError ?? (check.status === 'idle' ? check.formatError : null))

  return (
    <div className="grid gap-5">
      <FormField id="registro-cod-sis" label="Código SIS" required error={shownError ?? undefined}>
        <Input
          id="registro-cod-sis"
          value={draft.isGuest ? draft.guestCode : draft.codSis}
          maxLength={15}
          autoComplete="off"
          disabled={draft.isGuest}
          placeholder={draft.isGuest && guestCodeStatus === 'loading' ? 'Obteniendo código…' : undefined}
          aria-required="true"
          aria-invalid={Boolean(shownError) || undefined}
          aria-describedby="registro-cod-sis-ayuda"
          onChange={(event) => onCodeChange(event.target.value)}
          className="h-11 font-mono sm:max-w-sm"
        />
      </FormField>

      {draft.isGuest && guestCodeStatus === 'ready' && (
        <p className="-mt-3 text-sm text-muted-foreground">
          Este código de acceso se asignará a la cuenta. Se confirma otra vez al registrarla.
        </p>
      )}

      {draft.isGuest && guestCodeStatus === 'error' && (
        <div className="-mt-3 flex flex-wrap items-center gap-3 text-sm text-danger" role="alert">
          No se pudo obtener un código para el docente invitado.
          <Button type="button" variant="outline" size="sm" onClick={onRetryGuestCode}>
            Reintentar
          </Button>
        </div>
      )}

      {!draft.isGuest && <CheckResult check={check} />}

      <label className="flex items-start gap-3 rounded-lg border border-border-soft bg-sunken p-3 text-sm">
        <input
          type="checkbox"
          checked={draft.isGuest}
          onChange={(event) => onGuestChange(event.target.checked)}
          className="mt-0.5 accent-brand"
        />
        <span>
          <span className="font-semibold text-foreground">Registrar sin código SIS (docente invitado)</span>
          <span className="mt-0.5 block text-muted-foreground">
            Recibe un código de acceso que empieza con INV y el rol Docente invitado.
          </span>
        </span>
      </label>
    </div>
  )
}

function CheckResult({ check }: { check: CodeCheck }) {
  if (check.status === 'checking') {
    return (
      <p role="status" className="-mt-3 flex items-center gap-1.5 text-sm text-muted-foreground">
        <Loader2 aria-hidden="true" className="size-4 animate-spin" />
        Verificando el código…
      </p>
    )
  }

  if (check.status === 'exists') {
    return (
      <Alert variant="destructive" className="-mt-1">
        <AlertTriangle aria-hidden="true" />
        <AlertDescription className="flex flex-wrap items-center gap-x-3 gap-y-1">
          <span>{check.message}</span>
          {check.accountId && (
            <Link to={`/cuentas/${check.accountId}`} className="font-semibold underline underline-offset-2">
              Ver la cuenta existente
            </Link>
          )}
        </AlertDescription>
      </Alert>
    )
  }

  if (check.status === 'error') {
    return (
      <p role="alert" className="-mt-3 text-sm font-medium text-danger">
        {check.message}
      </p>
    )
  }

  if (check.status === 'sis-unavailable' || (check.status === 'available' && check.person === null)) {
    return (
      <p role="status" className="-mt-3 flex items-start gap-1.5 text-sm text-warn-fg">
        <Info aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
        {SIS_UNAVAILABLE_NOTICE}
      </p>
    )
  }

  if (check.status === 'available') {
    return (
      <p role="status" className="-mt-3 flex items-start gap-1.5 text-sm text-ok-fg">
        <CheckCircle aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
        El código está disponible. Se precargaron los nombres desde el SIS: puede corregirlos en el siguiente paso.
      </p>
    )
  }

  return null
}
