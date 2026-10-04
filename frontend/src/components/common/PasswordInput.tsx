import { useState, type ComponentProps } from 'react'
import { Eye, EyeOff, Lock } from 'lucide-react'
import {
  InputGroup,
  InputGroupAddon,
  InputGroupButton,
  InputGroupInput,
} from '@/components/ui/input-group'
import { cn } from '@/lib/utils'

interface PasswordInputProps extends Omit<ComponentProps<'input'>, 'type'> {
  /** Clases del contenedor del campo (alto, estados de solo lectura…). */
  groupClassName?: string
}

/**
 * Campo de contraseña con ojo para mostrarla u ocultarla.
 *
 * El ojo es un `type="button"` con `aria-pressed`: se activa con Enter o Espacio, no cambia el foco
 * ni la posición del cursor, y queda en el orden de tabulación justo después del campo. Su área
 * táctil es de 44 px en móvil y de 40 px en escritorio, dentro del propio campo.
 */
export function PasswordInput({ groupClassName, className, ...props }: PasswordInputProps) {
  const [visible, setVisible] = useState(false)

  return (
    <InputGroup className={cn('h-12 rounded-lg md:h-10', groupClassName)}>
      <InputGroupAddon>
        <Lock aria-hidden="true" />
      </InputGroupAddon>

      <InputGroupInput
        {...props}
        type={visible ? 'text' : 'password'}
        className={cn('h-full text-base md:text-sm', className)}
      />

      <InputGroupAddon align="inline-end">
        <InputGroupButton
          type="button"
          size="icon-sm"
          aria-label={visible ? 'Ocultar contraseña' : 'Mostrar contraseña'}
          aria-pressed={visible}
          onMouseDown={(event) => event.preventDefault()}
          onClick={() => setVisible((current) => !current)}
          className={cn(
            'size-11 text-muted-foreground md:size-10',
            visible && 'bg-brand-soft text-brand hover:bg-brand-soft'
          )}
        >
          {visible ? <EyeOff aria-hidden="true" /> : <Eye aria-hidden="true" />}
        </InputGroupButton>
      </InputGroupAddon>
    </InputGroup>
  )
}
