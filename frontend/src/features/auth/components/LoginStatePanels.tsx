import { CircleCheck, Lock, UserCog } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Spinner } from '@/components/ui/spinner'
import { StatePanel } from '@/components/common/StatePanel'
import { inactiveAccountMailto, roleRequestMailto } from '../lib/support'
import { SupportFooter } from './SupportFooter'

const ACTION_CLASSNAME = 'h-12 w-full text-base font-semibold md:h-10 md:text-sm'

interface AccountPanelProps {
  /** El código SIS con el que se intentó entrar. */
  sis: string
  /** Vuelve al formulario en reposo, con los campos vacíos. */
  onReset: () => void
}

/**
 * 403 · `cuenta_inactiva`. El formulario desaparece: reintentar daría exactamente el mismo resultado.
 * Gris y candado, no rojo: no es un error de la persona sino un estado de su cuenta. «Usar otra cuenta»
 * devuelve el formulario vacío, el caso real de un teléfono prestado entre auxiliares.
 */
export function InactiveAccountPanel({ sis, onReset }: AccountPanelProps) {
  return (
    <>
      <h1 className="text-2xl leading-8 font-semibold tracking-tight md:text-[28px] md:leading-9">
        No puede ingresar
      </h1>

      <StatePanel tone="neutral" icon={Lock} title="Su cuenta está inactiva">
        <p>
          La cuenta de <b className="font-semibold tabular-nums">{sis}</b> fue dada de baja, así que no puede usar
          SCIEM.
        </p>
        <p>Esto no se resuelve desde aquí: el Administrador de su facultad es quien puede reactivarla.</p>
      </StatePanel>

      <div className="flex flex-col gap-3">
        <Button asChild variant="outline" className={ACTION_CLASSNAME}>
          <a href={inactiveAccountMailto(sis)}>Escribir al Administrador</a>
        </Button>
        <Button type="button" variant="ghost" onClick={onReset} className={`${ACTION_CLASSNAME} text-brand`}>
          Usar otra cuenta
        </Button>
      </div>

      <SupportFooter sis={sis} />
    </>
  )
}

/**
 * 403 · `sin_rol_vigente`. Es el único de los cuatro rechazos con una acción concreta, así que su botón
 * es el primario. Separa dos cosas que la persona confunde: las credenciales estaban bien, lo que falta
 * es un paso administrativo. Azul informativo: no hay nada que corregir en lo que escribió.
 */
export function RoleMissingPanel({ sis, onReset }: AccountPanelProps) {
  return (
    <>
      <h1 className="text-2xl leading-8 font-semibold tracking-tight md:text-[28px] md:leading-9">
        Falta un paso
      </h1>

      <StatePanel tone="info" icon={UserCog} title="Su cuenta todavía no tiene un rol">
        <p>
          La cuenta de <b className="font-semibold tabular-nums">{sis}</b> existe y la contraseña es correcta, pero el
          Administrador aún no le asignó un rol. Sin rol, SCIEM no sabe qué debe mostrarle.
        </p>
      </StatePanel>

      <div className="flex flex-col gap-3">
        <Button asChild className={ACTION_CLASSNAME}>
          <a href={roleRequestMailto(sis, new Date())}>Solicitar la asignación de rol</a>
        </Button>
        <Button type="button" variant="ghost" onClick={onReset} className={`${ACTION_CLASSNAME} text-brand`}>
          Volver
        </Button>
      </div>

      <p className="text-center text-xs leading-[18px] text-muted-foreground">
        Si ya lo solicitó, vuelva a intentar en unos minutos.
      </p>
    </>
  )
}

/**
 * Ingreso correcto. Dura lo que tarda GET /api/auth/yo, pero hace falta: en una red lenta de campus ese
 * salto tarda un par de segundos y, sin una señal, la persona vuelve a pulsar «Ingresar».
 */
export function LoginSuccessPanel() {
  return (
    <>
      <h1 className="text-2xl leading-8 font-semibold tracking-tight md:text-[28px] md:leading-9">Bienvenido</h1>

      <StatePanel tone="ok" icon={CircleCheck} title="Ingreso correcto" role="status">
        <p>Abriendo su inicio…</p>
        <p className="flex items-center justify-center gap-2 text-[13px]">
          <Spinner aria-hidden="true" role="presentation" className="size-4" />
          Cargando su rol y su navegación
        </p>
      </StatePanel>
    </>
  )
}
