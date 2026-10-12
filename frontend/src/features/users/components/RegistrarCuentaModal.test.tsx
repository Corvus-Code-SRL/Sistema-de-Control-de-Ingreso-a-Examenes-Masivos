import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { RegistrarCuentaModal } from './RegistrarCuentaModal'
import { mockApi } from '@/test/http'
import { makeAccount, roles, sisPerson, userMatchers } from '@/test/userFixtures'

const COD_SIS = '202312345'
const TEMPORARY_PASSWORD = 'Temporal2026'
const REMINDER = 'Entregue la contraseña temporal al usuario; deberá cambiarla en su primer ingreso.'

function renderModal() {
  const onClose = vi.fn()
  const onFinished = vi.fn()
  const view = render(<RegistrarCuentaModal isOpen onClose={onClose} onFinished={onFinished} />)

  /** El modal sigue montado al cerrarse: reabrirlo muestra si descartó lo escrito. */
  const reopen = () => {
    view.rerender(<RegistrarCuentaModal isOpen={false} onClose={onClose} onFinished={onFinished} />)
    view.rerender(<RegistrarCuentaModal isOpen onClose={onClose} onFinished={onFinished} />)
  }

  return { onClose, onFinished, reopen }
}

/** Peticiones hechas a un endpoint, con su método y cuerpo ya leídos. */
function requestsTo(matcher: (url: string) => boolean) {
  return vi
    .mocked(fetch)
    .mock.calls.filter(([input]) => matcher(String(input)))
    .map(([, init]) => ({ method: init?.method, body: init?.body }))
}

const registrations = () =>
  requestsTo(userMatchers.accounts).filter((request) => request.method === 'POST')

async function verifyCode(code = COD_SIS) {
  await userEvent.type(screen.getByLabelText(/código sis/i), code)
  await userEvent.click(screen.getByRole('button', { name: /verificar en el sis/i }))
}

async function goToAccountData() {
  await verifyCode()
  await screen.findByText('Persona reconocida por el SIS')
  await userEvent.click(screen.getByRole('button', { name: /continuar/i }))
}

/** Completa lo que pide el paso «Datos» y registra la cuenta. */
async function fillAndRegister(password = TEMPORARY_PASSWORD) {
  await userEvent.type(screen.getByLabelText(/correo institucional/i), 'l.mendoza@umss.edu')
  await userEvent.type(screen.getByLabelText(/contraseña temporal/i), password)
  await userEvent.click(screen.getByRole('button', { name: 'Registrar cuenta' }))
}

const registeredAccount = makeAccount({ cod_sis: COD_SIS, correo: 'laura.mendoza@umss.edu' })

const registrationApi = [
  { matches: userMatchers.sisVerification, body: { data: sisPerson } },
  { matches: userMatchers.assignRole, body: { data: roles[1] } },
  { matches: userMatchers.roles, body: { data: roles } },
  {
    matches: userMatchers.accounts,
    status: 201,
    body: { data: registeredAccount, mensaje: 'Cuenta creada correctamente.' },
  },
]

const roleAssignments = () =>
  requestsTo(userMatchers.assignRole).filter((request) => request.method === 'POST')

const roleCatalogRequests = () => requestsTo(userMatchers.roles)

describe('RegistrarCuentaModal', () => {
  describe('campos obligatorios visibles', () => {
    it('marca el código SIS como obligatorio antes de enviar nada', () => {
      mockApi([])
      renderModal()

      expect(screen.getByText(/código sis/i, { selector: 'label' })).toHaveTextContent('*')
      expect(screen.getByLabelText(/código sis/i)).toHaveAttribute('aria-required', 'true')
      expect(screen.getByText(/son obligatorios/i)).toBeInTheDocument()
    })

    it('marca los obligatorios de los datos de la cuenta y deja sin marca los opcionales', async () => {
      mockApi([{ matches: userMatchers.sisVerification, body: { data: sisPerson } }])
      renderModal()

      await goToAccountData()

      for (const label of [/^nombre/i, /^apellido paterno/i, /^correo institucional/i]) {
        expect(screen.getByText(label, { selector: 'label' })).toHaveTextContent('*')
      }
      expect(screen.getByText(/^apellido materno/i, { selector: 'label' })).not.toHaveTextContent('*')
      expect(screen.getByLabelText(/correo institucional/i)).toHaveAttribute('aria-required', 'true')
    })

    it('no pide teléfono ni tipo institucional: la cuenta no los guarda', async () => {
      mockApi([{ matches: userMatchers.sisVerification, body: { data: sisPerson } }])
      renderModal()

      await goToAccountData()

      expect(screen.queryByLabelText(/teléfono/i)).not.toBeInTheDocument()
      expect(screen.queryByText(/teléfono/i)).not.toBeInTheDocument()
      expect(screen.queryByText('Tipo institucional', { selector: 'label' })).not.toBeInTheDocument()
      // Los únicos datos que se escriben son los que la cuenta guarda: solo el correo es editable.
      expect(screen.getAllByRole('textbox')).toHaveLength(1)
    })

    it('pide la contraseña temporal como obligatoria y la oculta por defecto', async () => {
      mockApi([{ matches: userMatchers.sisVerification, body: { data: sisPerson } }])
      renderModal()

      await goToAccountData()

      const field = screen.getByLabelText(/contraseña temporal/i)

      expect(screen.getByText(/^contraseña temporal/i, { selector: 'label' })).toHaveTextContent('*')
      expect(field).toHaveAttribute('aria-required', 'true')
      expect(field).toHaveAttribute('type', 'password')
      expect(field).toHaveAttribute('autocomplete', 'new-password')
    })

    it('permite mostrar y ocultar la contraseña temporal', async () => {
      mockApi([{ matches: userMatchers.sisVerification, body: { data: sisPerson } }])
      renderModal()

      await goToAccountData()
      await userEvent.type(screen.getByLabelText(/contraseña temporal/i), TEMPORARY_PASSWORD)

      await userEvent.click(screen.getByRole('button', { name: 'Mostrar contraseña' }))
      expect(screen.getByLabelText(/contraseña temporal/i)).toHaveAttribute('type', 'text')
      expect(screen.getByLabelText(/contraseña temporal/i)).toHaveValue(TEMPORARY_PASSWORD)

      await userEvent.click(screen.getByRole('button', { name: 'Ocultar contraseña' }))
      expect(screen.getByLabelText(/contraseña temporal/i)).toHaveAttribute('type', 'password')
    })

    it('rechaza una contraseña temporal de menos de 8 caracteres sin llamar al servidor', async () => {
      mockApi([{ matches: userMatchers.sisVerification, body: { data: sisPerson } }])
      renderModal()

      await goToAccountData()
      await fillAndRegister('Corta1')

      expect(
        screen.getByText('La contraseña temporal debe tener al menos 8 caracteres.')
      ).toBeInTheDocument()
      expect(screen.getByLabelText(/contraseña temporal/i)).toHaveAttribute('aria-invalid', 'true')
      expect(registrations()).toHaveLength(0)
    })

    it('la tarjeta del SIS conserva tipo y facultad como información de solo lectura', async () => {
      mockApi([{ matches: userMatchers.sisVerification, body: { data: sisPerson } }])
      renderModal()

      await verifyCode()
      await screen.findByText('Persona reconocida por el SIS')

      expect(screen.getByText(`Tipo institucional: ${sisPerson.tipo}`)).toBeInTheDocument()
      expect(screen.getByText(sisPerson.facultad)).toBeInTheDocument()
      expect(screen.queryByRole('textbox', { name: /facultad|tipo/i })).not.toBeInTheDocument()
    })

    it('no deja verificar sin código SIS', () => {
      mockApi([])
      renderModal()

      expect(screen.getByRole('button', { name: /verificar en el sis/i })).toBeDisabled()
      expect(screen.getByRole('button', { name: /continuar/i })).toBeDisabled()
      expect(fetch).not.toHaveBeenCalled()
    })

    it('bloquea el registro con el correo vacío, sin llamar al servidor', async () => {
      mockApi([{ matches: userMatchers.sisVerification, body: { data: sisPerson } }])
      renderModal()

      await goToAccountData()
      await userEvent.click(screen.getByRole('button', { name: 'Registrar cuenta' }))

      expect(screen.getByText(/ingrese un correo válido/i)).toBeInTheDocument()
      expect(screen.getByLabelText(/correo institucional/i)).toHaveAttribute('aria-invalid', 'true')
      expect(registrations()).toHaveLength(0)
    })
  })

  describe('cancelación de registro', () => {
    it('cancela antes de verificar sin enviar ninguna petición y descarta lo escrito', async () => {
      mockApi([])
      const { onClose, reopen } = renderModal()

      await userEvent.type(screen.getByLabelText(/código sis/i), COD_SIS)
      await userEvent.click(screen.getByRole('button', { name: 'Cancelar' }))

      expect(onClose).toHaveBeenCalledTimes(1)
      expect(fetch).not.toHaveBeenCalled()

      reopen()
      expect(screen.getByLabelText(/código sis/i)).toHaveValue('')
    })

    it('pide confirmar y descarta los datos ya escritos sin registrar la cuenta', async () => {
      mockApi([{ matches: userMatchers.sisVerification, body: { data: sisPerson } }])
      const { onClose, reopen } = renderModal()

      await goToAccountData()
      await userEvent.type(screen.getByLabelText(/correo institucional/i), 'l.mendoza@umss.edu')
      await userEvent.click(screen.getByRole('button', { name: 'Cancelar' }))

      expect(screen.getByText('¿Descartar el registro?')).toBeInTheDocument()
      await userEvent.click(screen.getByRole('button', { name: 'Descartar' }))

      expect(onClose).toHaveBeenCalledTimes(1)
      expect(registrations()).toHaveLength(0)

      reopen()
      expect(screen.getByLabelText(/código sis/i)).toHaveValue('')
      expect(screen.queryByText('Persona reconocida por el SIS')).not.toBeInTheDocument()
    })

    it('permite seguir registrando sin perder lo escrito', async () => {
      mockApi([{ matches: userMatchers.sisVerification, body: { data: sisPerson } }])
      const { onClose } = renderModal()

      await goToAccountData()
      await userEvent.type(screen.getByLabelText(/correo institucional/i), 'l.mendoza@umss.edu')
      await userEvent.click(screen.getByRole('button', { name: 'Cancelar' }))
      await userEvent.click(screen.getByRole('button', { name: 'Seguir registrando' }))

      expect(onClose).not.toHaveBeenCalled()
      expect(screen.getByLabelText(/correo institucional/i)).toHaveValue('l.mendoza@umss.edu')
    })

    it('aborta una verificación en curso y su respuesta no revive el registro', async () => {
      let signal: AbortSignal | undefined
      vi.stubGlobal(
        'fetch',
        vi.fn((_input: RequestInfo | URL, init?: RequestInit) => {
          signal = init?.signal ?? undefined
          return new Promise<Response>((_resolve, reject) => {
            signal?.addEventListener('abort', () => reject(new DOMException('Aborted', 'AbortError')))
          })
        })
      )
      const { onClose, reopen } = renderModal()

      await verifyCode()
      expect(screen.getByText(/consultando la fuente institucional/i)).toBeInTheDocument()

      await userEvent.click(screen.getByRole('button', { name: 'Cancelar' }))

      expect(onClose).toHaveBeenCalledTimes(1)
      expect(signal?.aborted).toBe(true)

      reopen()
      expect(screen.queryByText(/consultando la fuente institucional/i)).not.toBeInTheDocument()
      expect(screen.getByLabelText(/código sis/i)).toHaveValue('')
    })
  })

  it('registra la cuenta con su contraseña temporal y pasa solo al paso Rol', async () => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 9, 5, 14, 37, 0))
    mockApi(registrationApi)
    renderModal()

    await verifyCode()

    expect(await screen.findByText('Persona reconocida por el SIS')).toBeInTheDocument()
    expect(screen.getByText('Verificado a las 14:37.')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: /continuar/i }))

    expect(screen.getByText('Este correo quedará asociado a la cuenta.')).toBeInTheDocument()
    expect(screen.queryByText(/recibirá sus credenciales de acceso/i)).not.toBeInTheDocument()
    // El catálogo de roles no se pide hasta llegar al paso Rol.
    expect(roleCatalogRequests()).toHaveLength(0)

    await fillAndRegister()

    expect(await screen.findByRole('radio', { name: /docente/i })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Asignar después' })).toBeInTheDocument()
    expect(screen.getByText('Paso 2 de 3')).toBeInTheDocument()

    expect(registrations()).toEqual([
      {
        method: 'POST',
        body: JSON.stringify({
          cod_sis: COD_SIS,
          nombre: 'Laura',
          apellido_paterno: 'Mendoza',
          apellido_materno: 'Rivas',
          correo: 'l.mendoza@umss.edu',
          contrasenia_temporal: TEMPORARY_PASSWORD,
        }),
      },
    ])
    vi.useRealTimers()
  })

  describe('flujo Datos → Rol → Listo', () => {
    it('asigna el rol en el mismo modal y confirma con nombre, SIS, rol y recordatorio', async () => {
      vi.useFakeTimers({ toFake: ['Date'] })
      vi.setSystemTime(new Date(2026, 9, 5, 14, 37, 0))
      mockApi(registrationApi)
      const { onClose, onFinished } = renderModal()

      await goToAccountData()
      await fillAndRegister()

      await userEvent.click(await screen.findByRole('radio', { name: /docente/i }))
      await userEvent.click(screen.getByRole('button', { name: 'Asignar rol' }))

      expect(await screen.findByText('Cuenta creada')).toBeInTheDocument()
      expect(screen.getByText('Laura Mendoza Rivas · SIS 202312345 · Docente')).toBeInTheDocument()
      expect(screen.getByText('laura.mendoza@umss.edu')).toBeInTheDocument()
      expect(screen.getByText(REMINDER)).toBeInTheDocument()
      expect(screen.getByText('Registrada a las 14:37 (hora de este equipo)')).toBeInTheDocument()

      expect(roleAssignments()).toEqual([
        { method: 'POST', body: JSON.stringify({ id_rol: roles[1].id_rol }) },
      ])
      // La contraseña no se vuelve a mostrar.
      expect(screen.queryByText(TEMPORARY_PASSWORD)).not.toBeInTheDocument()
      expect(onFinished).not.toHaveBeenCalled()

      await userEvent.click(screen.getByRole('button', { name: 'Cerrar' }))

      expect(onFinished).toHaveBeenCalledWith({ account: registeredAccount, role: roles[1] })
      expect(onClose).toHaveBeenCalledTimes(1)
      vi.useRealTimers()
    })

    it('«Asignar después» cierra el modal y deja la cuenta sin rol', async () => {
      mockApi(registrationApi)
      const { onClose, onFinished } = renderModal()

      await goToAccountData()
      await fillAndRegister()

      await userEvent.click(await screen.findByRole('button', { name: 'Asignar después' }))

      expect(onClose).toHaveBeenCalledTimes(1)
      expect(onFinished).toHaveBeenCalledWith({ account: registeredAccount, role: null })
      expect(roleAssignments()).toHaveLength(0)
      expect(screen.queryByText('¿Descartar el registro?')).not.toBeInTheDocument()
    })

    it('cerrar en el paso Rol equivale a «Asignar después»: la cuenta ya existe', async () => {
      mockApi(registrationApi)
      const { onClose, onFinished } = renderModal()

      await goToAccountData()
      await fillAndRegister()

      await screen.findByRole('button', { name: 'Asignar después' })
      // Hay una cabecera para móvil y otra para escritorio: las dos cierran igual.
      await userEvent.click(screen.getAllByRole('button', { name: 'Cancelar registro' })[0])

      expect(onFinished).toHaveBeenCalledWith({ account: registeredAccount, role: null })
      expect(onClose).toHaveBeenCalledTimes(1)
    })

    it('no asigna sin elegir un rol', async () => {
      mockApi(registrationApi)
      renderModal()

      await goToAccountData()
      await fillAndRegister()

      await screen.findByRole('radio', { name: /docente/i })

      expect(screen.getByRole('button', { name: 'Asignar rol' })).toBeDisabled()
    })

    it('muestra el rechazo del servidor y deja reintentar o asignar después', async () => {
      mockApi([
        {
          matches: userMatchers.assignRole,
          status: 422,
          body: {
            message: 'Los datos proporcionados no son válidos.',
            errors: { id_rol: ['El rol seleccionado no existe en el sistema.'] },
          },
        },
        ...registrationApi,
      ])
      const { onFinished } = renderModal()

      await goToAccountData()
      await fillAndRegister()

      await userEvent.click(await screen.findByRole('radio', { name: /auxiliar/i }))
      await userEvent.click(screen.getByRole('button', { name: 'Asignar rol' }))

      expect(
        await screen.findByText('El rol seleccionado no existe en el sistema.')
      ).toBeInTheDocument()
      expect(screen.queryByText('Cuenta creada')).not.toBeInTheDocument()
      expect(screen.getByRole('button', { name: 'Asignar después' })).toBeEnabled()
      expect(onFinished).not.toHaveBeenCalled()
    })

    it('«Registrar otra» informa la cuenta terminada y vuelve a empezar sin la contraseña', async () => {
      mockApi(registrationApi)
      const { onClose, onFinished } = renderModal()

      await goToAccountData()
      await fillAndRegister()
      await userEvent.click(await screen.findByRole('radio', { name: /docente/i }))
      await userEvent.click(screen.getByRole('button', { name: 'Asignar rol' }))
      await screen.findByText('Cuenta creada')

      await userEvent.click(screen.getByRole('button', { name: 'Registrar otra' }))

      expect(onFinished).toHaveBeenCalledTimes(1)
      expect(onClose).not.toHaveBeenCalled()
      expect(screen.getByLabelText(/código sis/i)).toHaveValue('')

      await goToAccountData()
      expect(screen.getByLabelText(/contraseña temporal/i)).toHaveValue('')
    })
  })

  describe('mensajes específicos de la verificación', () => {
    it('avisa de un código SIS no reconocido', async () => {
      mockApi([
        {
          matches: userMatchers.sisVerification,
          status: 422,
          body: {
            message: 'Los datos proporcionados no son válidos.',
            errors: {
              cod_sis: ['El código SIS no corresponde a una persona reconocida por la institución.'],
            },
          },
        },
      ])
      renderModal()

      await verifyCode('999999999')

      expect(
        await screen.findByText(
          'El código SIS no corresponde a una persona reconocida por la institución.'
        )
      ).toBeInTheDocument()
      expect(screen.queryByText('Ver la cuenta existente')).not.toBeInTheDocument()
      expect(screen.getByRole('button', { name: /continuar/i })).toBeDisabled()
    })

    it('avisa de un código SIS que ya tiene cuenta', async () => {
      mockApi([
        {
          matches: userMatchers.sisVerification,
          status: 422,
          body: {
            message: 'Los datos proporcionados no son válidos.',
            errors: { cod_sis: ['Ya existe una cuenta con este código SIS: Juan Perez.'] },
          },
        },
      ])
      renderModal()

      await verifyCode()

      expect(
        await screen.findByText('Ya existe una cuenta con este código SIS: Juan Perez.')
      ).toBeInTheDocument()
      expect(screen.getByText('Ver la cuenta existente')).toBeInTheDocument()
    })

    it('avisa cuando el SIS no está disponible y ofrece reintentar', async () => {
      mockApi([
        {
          matches: userMatchers.sisVerification,
          status: 503,
          body: { message: 'El servicio institucional (SIS) no está disponible. Intente más tarde.' },
        },
      ])
      renderModal()

      await verifyCode()

      expect(await screen.findByText('El SIS no está disponible')).toBeInTheDocument()
      expect(screen.getByRole('button', { name: /reintentar/i })).toBeInTheDocument()
    })

    it('muestra bajo el campo el error de validación del registro', async () => {
      mockApi([
        { matches: userMatchers.sisVerification, body: { data: sisPerson } },
        {
          matches: userMatchers.accounts,
          status: 422,
          body: {
            message: 'Los datos proporcionados no son válidos.',
            errors: { correo: ['Ya existe una cuenta registrada con este correo institucional.'] },
          },
        },
      ])
      renderModal()

      await goToAccountData()
      await fillAndRegister()

      expect(
        await screen.findByText('Ya existe una cuenta registrada con este correo institucional.')
      ).toBeInTheDocument()
      expect(screen.queryByText('Cuenta creada')).not.toBeInTheDocument()
      expect(screen.queryByRole('button', { name: 'Asignar después' })).not.toBeInTheDocument()
    })

    it('muestra bajo el campo el rechazo de la contraseña temporal', async () => {
      mockApi([
        { matches: userMatchers.sisVerification, body: { data: sisPerson } },
        {
          matches: userMatchers.accounts,
          status: 422,
          body: {
            message: 'Los datos proporcionados no son válidos.',
            errors: {
              contrasenia_temporal: ['La contraseña temporal debe tener al menos 8 caracteres.'],
            },
          },
        },
      ])
      renderModal()

      await goToAccountData()
      await fillAndRegister('12345678')

      expect(
        await screen.findByText('La contraseña temporal debe tener al menos 8 caracteres.')
      ).toBeInTheDocument()
      expect(screen.queryByText('Cuenta creada')).not.toBeInTheDocument()
    })
  })
})
