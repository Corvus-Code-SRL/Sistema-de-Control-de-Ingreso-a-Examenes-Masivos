import { CircleAlert, TriangleAlert, UserRound, WifiOff } from 'lucide-react'
import { PasswordInput } from '@/components/common/PasswordInput'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group'
import { Label } from '@/components/ui/label'
import { Spinner } from '@/components/ui/spinner'
import { cn } from '@/lib/utils'
import { useAuth } from '../hooks/useAuth'
import { LOGIN_FIELD_IDS, type UseLoginFormResult } from '../hooks/useLoginForm'
import { supportMailto } from '../lib/support'
import { LINK_CLASSNAME, SupportFooter } from './SupportFooter'
import { ThrottleNotice } from './ThrottleNotice'

const SIS_HELP_ID = 'login-codigo-sis-help'
const SIS_ERROR_ID = 'login-codigo-sis-error'
const PASSWORD_ERROR_ID = 'login-password-error'
const FAILURE_ID = 'login-failure'

/** Aspecto de un campo que no se puede editar: mientras se envía y durante la espera de un 429. */
const READ_ONLY_CLASSNAME = 'border-dis-border bg-dis-bg text-dis-text'

interface LoginFormProps {
  form: UseLoginFormResult
  /** Con el teclado virtual abierto el título se encoge y las ayudas se ocultan. */
  keyboardOpen: boolean
}

/**
 * El formulario en reposo y todos sus estados que no reemplazan la tarjeta: validación, envío,
 * credenciales inválidas (401), espera por intentos (429) y fallo de red o 5xx.
 */
export function LoginForm({ form, keyboardOpen }: LoginFormProps) {
  const { failure, fieldErrors, isReadOnly, isSubmitting, throttleRemaining } = form
  const sessionExpired = useAuth().estado === 'expirada'

  const isThrottled = throttleRemaining > 0
  const credentialsFailed = failure?.kind === 'credenciales'
  const unavailable = failure?.kind === 'indisponible'
  const showSisHelp = !fieldErrors.codSis && !keyboardOpen

  const failureReference = failure && failure.kind !== 'limitado' ? FAILURE_ID : undefined

  return (
    <form
      onSubmit={form.submit}
      noValidate
      aria-busy={isSubmitting}
      className="flex flex-1 flex-col gap-5 md:flex-none md:gap-6"
    >
      <div className="flex flex-col gap-1">
        <h1
          className={cn(
            'font-semibold tracking-tight transition-[font-size]',
            keyboardOpen ? 'text-xl leading-7' : 'text-2xl leading-8 md:text-[28px] md:leading-9'
          )}
        >
          Ingresar
        </h1>
        <p className="text-[15px] leading-[22px] text-muted-foreground md:text-sm md:leading-5">
          Use su código SIS y su contraseña institucional.
        </p>
      </div>

      {sessionExpired && (
        <Alert
          role="status"
          className="border-warn-border bg-warn-soft text-sm text-warn-fg"
          data-notice="sesion-expirada"
        >
          <TriangleAlert aria-hidden="true" />
          <AlertTitle>Su sesión expiró</AlertTitle>
          <AlertDescription className="text-[13px] leading-[19px]">
            Inicie sesión de nuevo para continuar. Volverá a la pantalla en la que estaba.
          </AlertDescription>
        </Alert>
      )}

      {form.bothEmpty && (
        <Alert
          className="border-danger-border bg-danger-soft text-sm text-danger-fg"
          data-failure-kind="validacion"
        >
          <CircleAlert aria-hidden="true" />
          <AlertTitle>Complete los dos campos para continuar</AlertTitle>
        </Alert>
      )}

      {failure && credentialsFailed && (
        <Alert
          id={FAILURE_ID}
          className="border-danger-border bg-danger-soft text-sm text-danger-fg"
          data-failure-kind={failure.kind}
        >
          <CircleAlert aria-hidden="true" />
          <AlertTitle>Código SIS o contraseña incorrectos</AlertTitle>
          <AlertDescription className="text-[13px] leading-[19px]">
            Revise los datos e intente de nuevo. Después de 10 intentos deberá esperar un minuto.
          </AlertDescription>
        </Alert>
      )}

      {failure?.kind === 'limitado' && (
        <Alert
          role="status"
          className="border-warn-border bg-warn-soft text-sm text-warn-fg"
          data-failure-kind={failure.kind}
        >
          <TriangleAlert aria-hidden="true" />
          <AlertTitle>Demasiados intentos</AlertTitle>
          <AlertDescription className="text-[13px] leading-[19px]">
            Por seguridad, el acceso queda en pausa <b className="font-semibold">un minuto</b>. Esto no significa que
            el sistema haya fallado.
          </AlertDescription>
        </Alert>
      )}

      {unavailable && (
        <Alert
          id={FAILURE_ID}
          className="border-border-soft bg-neutral-soft text-sm text-neutral-fg"
          data-failure-kind={failure.kind}
        >
          <WifiOff aria-hidden="true" />
          <AlertTitle>No se pudo conectar con el servidor</AlertTitle>
          <AlertDescription className="text-[13px] leading-[19px]">
            Revise su conexión e intente de nuevo. Sus datos no se enviaron.
          </AlertDescription>
        </Alert>
      )}

      {failure?.kind === 'desconocido' && (
        <Alert
          id={FAILURE_ID}
          className="border-border-soft bg-neutral-soft text-sm text-neutral-fg"
          data-failure-kind={failure.kind}
        >
          <CircleAlert aria-hidden="true" />
          <AlertTitle>No se pudo iniciar sesión</AlertTitle>
          <AlertDescription className="text-[13px] leading-[19px]">{failure.message}</AlertDescription>
        </Alert>
      )}

      <div className="flex flex-col gap-[18px]">
        <div className="flex flex-col gap-1.5">
          <Label htmlFor={LOGIN_FIELD_IDS.codSis} className="leading-5">
            Código SIS <span aria-hidden="true" className="text-danger">*</span>
          </Label>

          <InputGroup
            className={cn('h-12 scroll-mb-32 rounded-lg md:h-10', isReadOnly && READ_ONLY_CLASSNAME)}
          >
            <InputGroupAddon>
              <UserRound aria-hidden="true" />
            </InputGroupAddon>
            <InputGroupInput
              id={LOGIN_FIELD_IDS.codSis}
              name="codigo_sis"
              type="text"
              autoComplete="username"
              autoCapitalize="off"
              autoCorrect="off"
              spellCheck={false}
              placeholder="Su código institucional"
              value={form.codSis}
              onChange={form.onCodSisChange}
              readOnly={isReadOnly}
              aria-required="true"
              aria-invalid={fieldErrors.codSis ? true : undefined}
              aria-describedby={
                [fieldErrors.codSis ? SIS_ERROR_ID : showSisHelp ? SIS_HELP_ID : undefined, failureReference]
                  .filter(Boolean)
                  .join(' ') || undefined
              }
              className="h-full text-base md:text-sm"
            />
          </InputGroup>

          {fieldErrors.codSis && (
            <p
              id={SIS_ERROR_ID}
              className="flex gap-1.5 text-[13px] leading-[18px] font-medium text-danger-fg"
            >
              <CircleAlert aria-hidden="true" className="mt-px size-4 flex-none" />
              {fieldErrors.codSis}
            </p>
          )}

          {showSisHelp && (
            <p id={SIS_HELP_ID} className="text-[13px] leading-[18px] text-muted-foreground">
              Ejemplos: <b className="font-semibold">10452</b> · <b className="font-semibold">201800451</b> ·{' '}
              <b className="font-semibold">ADM0001</b>
            </p>
          )}
        </div>

        <div className="flex flex-col gap-1.5">
          <Label htmlFor={LOGIN_FIELD_IDS.password} className="leading-5">
            Contraseña <span aria-hidden="true" className="text-danger">*</span>
          </Label>

          <PasswordInput
            id={LOGIN_FIELD_IDS.password}
            name="password"
            autoComplete="current-password"
            autoCapitalize="off"
            autoCorrect="off"
            spellCheck={false}
            placeholder="Su contraseña"
            value={form.password}
            onChange={form.onPasswordChange}
            readOnly={isReadOnly}
            aria-required="true"
            aria-invalid={fieldErrors.password ? true : undefined}
            aria-describedby={[fieldErrors.password ? PASSWORD_ERROR_ID : undefined, failureReference]
              .filter(Boolean)
              .join(' ') || undefined}
            groupClassName={cn('scroll-mb-32', isReadOnly && READ_ONLY_CLASSNAME)}
          />

          {fieldErrors.password && (
            <p
              id={PASSWORD_ERROR_ID}
              className="flex gap-1.5 text-[13px] leading-[18px] font-medium text-danger-fg"
            >
              <CircleAlert aria-hidden="true" className="mt-px size-4 flex-none" />
              {fieldErrors.password}
            </p>
          )}
        </div>
      </div>

      {isThrottled && <ThrottleNotice remaining={throttleRemaining} />}

      {/* En móvil la barra del botón se ancla al borde inferior: alcanzable con el teclado abierto. */}
      <div
        className={cn(
          'sticky bottom-0 z-10 -mx-4 mt-auto flex flex-col gap-2 border-t border-border-soft bg-surface px-4 pt-3',
          'pb-[max(1rem,env(safe-area-inset-bottom))] shadow-[0_-4px_12px_color-mix(in_srgb,var(--sciem-brand-deep)_6%,transparent)]',
          'md:static md:mx-0 md:mt-0 md:border-0 md:bg-transparent md:p-0 md:shadow-none'
        )}
      >
        <Button
          type="submit"
          disabled={isSubmitting || isThrottled}
          className={cn(
            'h-12 w-full text-base font-semibold md:h-10 md:text-sm',
            isSubmitting && 'disabled:opacity-70',
            isThrottled && 'disabled:border-dis-border disabled:bg-dis-bg disabled:text-dis-text disabled:opacity-100'
          )}
        >
          {isSubmitting ? (
            <>
              <Spinner aria-hidden="true" role="presentation" />
              Verificando…
            </>
          ) : unavailable ? (
            'Reintentar'
          ) : (
            'Ingresar'
          )}
        </Button>

        {/* El indicador se anuncia con aria-live="polite"; el botón por sí solo no dice que está esperando. */}
        <p className="sr-only" aria-live="polite">
          {isSubmitting ? 'Verificando sus datos' : ''}
        </p>

        {isSubmitting && (
          <p className="text-center text-xs leading-[18px] text-muted-foreground" aria-hidden="true">
            No cierre la aplicación
          </p>
        )}
      </div>

      {isThrottled && (
        <p className="text-center text-xs leading-[18px] text-muted-foreground">
          Si olvidó su contraseña, use el enlace de recuperación en lugar de seguir intentando.
        </p>
      )}

      {/* Mientras hay una petición en curso no se ofrece una salida alternativa. */}
      {!isSubmitting && (
        <p className="text-center">
          <a href={supportMailto(form.codSis.trim() || undefined)} className={cn(LINK_CLASSNAME, 'text-sm')}>
            ¿Olvidó su contraseña?
          </a>
        </p>
      )}

      {!keyboardOpen && !isSubmitting && <SupportFooter className="pb-6 md:pb-0" />}
    </form>
  )
}
