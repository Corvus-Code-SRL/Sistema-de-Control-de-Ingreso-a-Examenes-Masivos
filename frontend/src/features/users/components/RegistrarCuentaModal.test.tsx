import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { RegistrarCuentaModal } from './RegistrarCuentaModal'
import { mockApi } from '@/test/http'
import { makeAccount, sisPerson, userMatchers } from '@/test/userFixtures'

const COD_SIS = '202312345'

function renderModal() {
  const onClose = vi.fn()
  const onSuccess = vi.fn()
  const view = render(<RegistrarCuentaModal isOpen onClose={onClose} onSuccess={onSuccess} />)

  /** El modal sigue montado al cerrarse: reabrirlo muestra si descartó lo escrito. */
  const reopen = () => {
    view.rerender(<RegistrarCuentaModal isOpen={false} onClose={onClose} onSuccess={onSuccess} />)
    view.rerender(<RegistrarCuentaModal isOpen onClose={onClose} onSuccess={onSuccess} />)
  }

  return { onClose, onSuccess, reopen }
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
      expect(screen.getByText(/^teléfono/i, { selector: 'label' })).not.toHaveTextContent('*')
      expect(screen.getByLabelText(/correo institucional/i)).toHaveAttribute('aria-required', 'true')
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

  it('confirma el registro y envía solo los campos que el backend declara', async () => {
    mockApi([
      { matches: userMatchers.sisVerification, body: { data: sisPerson } },
      {
        matches: userMatchers.accounts,
        status: 201,
        body: { data: makeAccount({ cod_sis: COD_SIS }), mensaje: 'Cuenta creada correctamente.' },
      },
    ])
    renderModal()

    await goToAccountData()
    await userEvent.type(screen.getByLabelText(/correo institucional/i), 'l.mendoza@umss.edu')
    await userEvent.type(screen.getByLabelText(/teléfono/i), '71234567')
    await userEvent.click(screen.getByRole('button', { name: 'Registrar cuenta' }))

    expect(await screen.findByText('Cuenta creada')).toBeInTheDocument()
    expect(screen.getByText(/laura mendoza rivas · sis 202312345 · sin rol/i)).toBeInTheDocument()
    expect(registrations()).toEqual([
      {
        method: 'POST',
        body: JSON.stringify({
          cod_sis: COD_SIS,
          nombre: 'Laura',
          apellido_paterno: 'Mendoza',
          apellido_materno: 'Rivas',
          correo: 'l.mendoza@umss.edu',
        }),
      },
    ])
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
      await userEvent.type(screen.getByLabelText(/correo institucional/i), 'l.mendoza@umss.edu')
      await userEvent.click(screen.getByRole('button', { name: 'Registrar cuenta' }))

      expect(
        await screen.findByText('Ya existe una cuenta registrada con este correo institucional.')
      ).toBeInTheDocument()
      expect(screen.queryByText('Cuenta creada')).not.toBeInTheDocument()
    })
  })
})
