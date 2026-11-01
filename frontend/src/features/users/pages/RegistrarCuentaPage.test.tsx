import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { describe, expect, it } from 'vitest'
import { RegistrarCuentaPage } from './RegistrarCuentaPage'
import { mockApiWith, type RecordedRequest } from '@/test/http'
import { renderWithRouter } from '@/test/render'
import { guestTeacher, makeAccount, roles, sisPerson, userMatchers } from '@/test/userFixtures'

type Stub = { status?: number; body: unknown }

interface Backend {
  /** Respuestas de `POST /usuarios`, una por intento; la última se repite. */
  register?: Stub[]
  /** Respuesta de la verificación del código. */
  sis?: Stub
  /** Respuesta de `GET /usuarios/codigo-invitado`. */
  guestCode?: Stub
}

function stubBackend({ register, sis, guestCode }: Backend = {}) {
  let attempt = 0

  return mockApiWith((request) => {
    if (userMatchers.roles(request.url)) return { body: { data: roles } }
    if (userMatchers.sisVerification(request.url)) return sis ?? { body: { data: null } }
    if (userMatchers.guestCode(request.url)) return guestCode ?? { body: { data: { cod_sis: 'INV4821' } } }
    if (userMatchers.accounts(request.url) && request.method === 'POST') {
      const responses = register ?? [{ status: 201, body: { data: makeAccount() } }]
      const response = responses[Math.min(attempt, responses.length - 1)]

      attempt += 1

      return response
    }

    return undefined
  })
}

const posts = (calls: RecordedRequest[]) =>
  calls.filter((call) => userMatchers.accounts(call.url) && call.method === 'POST')

const verifications = (calls: RecordedRequest[]) =>
  calls.filter((call) => userMatchers.sisVerification(call.url))

const SLOW = { timeout: 3000 }

async function renderPage() {
  renderWithRouter(<RegistrarCuentaPage />, { route: '/cuentas/nueva' })
  await screen.findByRole('heading', { name: 'Registrar cuenta nueva' })
}

const next = () => screen.getByRole('button', { name: /Siguiente/ })
const sisField = () => screen.getByLabelText(/^Código SIS/)
const steps = () => within(screen.getByRole('list', { name: 'Pasos del registro' })).getAllByRole('listitem')

async function typeCode(user: ReturnType<typeof userEvent.setup>, code: string) {
  await user.type(sisField(), code)
}

/** Escribe un código y espera a que la verificación termine (disponible o SIS sin respuesta). */
async function enterCode(user: ReturnType<typeof userEvent.setup>, code = '10452') {
  await typeCode(user, code)
  await screen.findByText(/El código está disponible|No se pudo consultar el SIS/, undefined, SLOW)
}

async function fillPersonal(user: ReturnType<typeof userEvent.setup>) {
  await user.clear(screen.getByLabelText(/^Nombres/))
  await user.type(screen.getByLabelText(/^Nombres/), 'Laura')
  await user.clear(screen.getByLabelText(/^Apellido paterno/))
  await user.type(screen.getByLabelText(/^Apellido paterno/), 'Mendoza')
  await user.type(screen.getByLabelText(/^Correo institucional/), 'l.mendoza@umss.edu')
  await user.type(screen.getByLabelText(/^Contraseña temporal/), 'Temporal123')
}

/** Lleva el asistente de una cuenta con código SIS hasta el paso del rol. */
async function reachRoleStep(user: ReturnType<typeof userEvent.setup>, code = '10452') {
  await enterCode(user, code)
  await user.click(next())
  await fillPersonal(user)
  await user.click(next())
  await screen.findByRole('heading', { name: 'Rol de la cuenta' })
}

describe('RegistrarCuentaPage — paso 1: código', () => {
  it('muestra el campo del código SIS y la opción de docente invitado, con «Siguiente» deshabilitado', async () => {
    stubBackend()

    await renderPage()

    expect(sisField()).toBeEnabled()
    expect(screen.getByRole('checkbox', { name: /Registrar sin código SIS \(docente invitado\)/ })).not.toBeChecked()
    expect(next()).toBeDisabled()
    expect(steps()).toHaveLength(3)
    expect(steps()[0]).toHaveAttribute('aria-current', 'step')
  })

  it('con un código válido que no existe verifica contra el servidor y habilita «Siguiente»', async () => {
    const user = userEvent.setup()
    const { calls } = stubBackend({ sis: { body: { data: sisPerson } } })

    await renderPage()
    await typeCode(user, '10452')

    expect(await screen.findByText(/El código está disponible/, undefined, SLOW)).toBeInTheDocument()
    expect(next()).toBeEnabled()
    expect(verifications(calls)).toHaveLength(1)
    expect(verifications(calls)[0].url).toContain('/sis/verificar/10452')
    expect(posts(calls)).toHaveLength(0)
  })

  it('un código con formato inválido se informa y no se consulta al servidor', async () => {
    const user = userEvent.setup()
    const { calls } = stubBackend()

    await renderPage()
    await typeCode(user, 'A-1')

    expect(await screen.findByText(/formato válido/)).toBeInTheDocument()
    expect(next()).toBeDisabled()
    expect(verifications(calls)).toHaveLength(0)
  })

  it('un código INV escrito a mano se rechaza: lo genera el sistema', async () => {
    const user = userEvent.setup()
    stubBackend()

    await renderPage()
    await typeCode(user, 'INV4821')

    expect(await screen.findByText(/Los códigos que empiezan con INV los genera el sistema/)).toBeInTheDocument()
    expect(next()).toBeDisabled()
  })

  it('si ya existe una cuenta con el código muestra su nombre, ofrece verla y no deja continuar', async () => {
    const user = userEvent.setup()
    stubBackend({
      sis: {
        status: 422,
        body: {
          message: 'Los datos proporcionados no son válidos.',
          errors: { cod_sis: ['Ya existe una cuenta con este código SIS: Juan Perez.'] },
          cuenta_existente: { id_usuario: '3f2b6c1e-8d4a-4c7e-9a51-2b7d0e6f4a10' },
        },
      },
    })

    await renderPage()
    await typeCode(user, '10452')

    expect(await screen.findByText('Ya existe una cuenta con este código SIS: Juan Perez.', undefined, SLOW)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Ver la cuenta existente' })).toHaveAttribute(
      'href',
      '/cuentas/3f2b6c1e-8d4a-4c7e-9a51-2b7d0e6f4a10'
    )
    expect(next()).toBeDisabled()
  })

  it('con el SIS caído (503) avisa con suavidad y deja continuar', async () => {
    const user = userEvent.setup()
    stubBackend({ sis: { status: 503, body: { message: 'El SIS no responde.' } } })

    await renderPage()
    await typeCode(user, '10452')

    expect(await screen.findByText('No se pudo consultar el SIS; complete los datos manualmente.', undefined, SLOW)).toBeInTheDocument()
    expect(next()).toBeEnabled()
  })

  it('si el SIS no conoce el código (sin datos) muestra el mismo aviso y deja continuar', async () => {
    const user = userEvent.setup()
    stubBackend({ sis: { body: { data: null } } })

    await renderPage()
    await typeCode(user, '10452')

    expect(await screen.findByText('No se pudo consultar el SIS; complete los datos manualmente.', undefined, SLOW)).toBeInTheDocument()
    expect(next()).toBeEnabled()
  })

  it('«Volver» en el primer paso regresa a la página anterior', async () => {
    stubBackend()

    render(
      <MemoryRouter initialEntries={['/cuentas/roles', '/cuentas/nueva']} initialIndex={1}>
        <Routes>
          <Route path="/cuentas/roles" element={<p>Lista de cuentas</p>} />
          <Route path="/cuentas/nueva" element={<RegistrarCuentaPage />} />
        </Routes>
      </MemoryRouter>
    )
    await screen.findByRole('heading', { name: 'Registrar cuenta nueva' })
    await userEvent.click(screen.getByRole('button', { name: 'Volver' }))

    expect(await screen.findByText('Lista de cuentas')).toBeInTheDocument()
  })
})

describe('RegistrarCuentaPage — con código SIS', () => {
  it('paso 2: «Siguiente» solo se habilita con los datos obligatorios válidos; el apellido materno es opcional', async () => {
    const user = userEvent.setup()
    stubBackend()

    await renderPage()
    await enterCode(user)
    await user.click(next())

    expect(screen.getByRole('heading', { name: 'Datos personales' })).toBeInTheDocument()
    expect(steps()[1]).toHaveAttribute('aria-current', 'step')
    expect(next()).toBeDisabled()

    await user.type(screen.getByLabelText(/^Nombres/), 'Laura')
    await user.type(screen.getByLabelText(/^Apellido paterno/), 'Mendoza')
    await user.type(screen.getByLabelText(/^Correo institucional/), 'no-es-un-correo')
    await user.type(screen.getByLabelText(/^Contraseña temporal/), 'Temporal123')

    expect(next()).toBeDisabled()

    await user.clear(screen.getByLabelText(/^Correo institucional/))
    await user.type(screen.getByLabelText(/^Correo institucional/), 'l.mendoza@umss.edu')

    expect(screen.getByLabelText(/^Apellido materno/)).toHaveValue('')
    expect(next()).toBeEnabled()
  })

  it('un código que el SIS conoce precarga los nombres, que siguen siendo editables', async () => {
    const user = userEvent.setup()
    stubBackend({ sis: { body: { data: sisPerson } } })

    await renderPage()
    await enterCode(user)
    await user.click(next())

    expect(screen.getByLabelText(/^Nombres/)).toHaveValue('Laura')
    expect(screen.getByLabelText(/^Apellido paterno/)).toHaveValue('Mendoza')
    expect(screen.getByLabelText(/^Apellido materno/)).toHaveValue('Rivas')

    await user.type(screen.getByLabelText(/^Nombres/), ' María')

    expect(screen.getByLabelText(/^Nombres/)).toHaveValue('Laura María')
  })

  it('«Volver» conserva lo escrito y no repite la verificación del código', async () => {
    const user = userEvent.setup()
    const { calls } = stubBackend()

    await renderPage()
    await enterCode(user)
    await user.click(next())
    await fillPersonal(user)
    await user.click(screen.getByRole('button', { name: 'Volver' }))

    expect(sisField()).toHaveValue('10452')
    expect(next()).toBeEnabled()

    await user.click(next())

    expect(screen.getByLabelText(/^Nombres/)).toHaveValue('Laura')
    expect(screen.getByLabelText(/^Correo institucional/)).toHaveValue('l.mendoza@umss.edu')
    expect(verifications(calls)).toHaveLength(1)
  })

  it('paso 3: los tres roles son tarjetas seleccionables, sin lista desplegable', async () => {
    const user = userEvent.setup()
    stubBackend()

    await renderPage()
    await reachRoleStep(user)

    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
    expect(screen.getAllByRole('radio').map((radio) => radio.closest('label')?.textContent)).toEqual([
      expect.stringContaining('Administrador'),
      expect.stringContaining('Docente'),
      expect.stringContaining('Auxiliar'),
    ])
    expect(screen.queryByRole('radio', { name: /Docente invitado/ })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Registrar cuenta' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Continuar sin rol' })).toBeEnabled()
    expect(steps()).toHaveLength(3)
  })

  it('registra la cuenta con el rol elegido y muestra el resumen', async () => {
    const user = userEvent.setup()
    const { calls } = stubBackend({
      register: [
        {
          status: 201,
          body: {
            data: makeAccount({
              cod_sis: '10452',
              nombre_completo: 'Laura Mendoza',
              correo: 'l.mendoza@umss.edu',
              rol: { id_rol: 2, nombre_rol: 'Docente' },
            }),
          },
        },
      ],
    })

    await renderPage()
    await reachRoleStep(user)
    await user.click(screen.getByRole('radio', { name: /Docente/ }))
    await user.click(screen.getByRole('button', { name: 'Registrar cuenta' }))

    expect(await screen.findByRole('heading', { name: 'Cuenta creada' })).toBeInTheDocument()
    expect(posts(calls)).toHaveLength(1)
    expect(posts(calls)[0].body).toEqual({
      cod_sis: '10452',
      id_rol: 2,
      nombre: 'Laura',
      apellido_paterno: 'Mendoza',
      apellido_materno: null,
      correo: 'l.mendoza@umss.edu',
      contrasenia_temporal: 'Temporal123',
    })

    const summary = within(screen.getByRole('heading', { name: 'Cuenta creada' }).parentElement!.parentElement!)

    expect(summary.getByText('Laura Mendoza')).toBeInTheDocument()
    expect(summary.getByText('10452')).toBeInTheDocument()
    expect(summary.getByText('l.mendoza@umss.edu')).toBeInTheDocument()
    expect(summary.getByText('Docente')).toBeInTheDocument()
    expect(screen.getByRole('note')).toHaveTextContent('Entregue la contraseña temporal')
    expect(screen.queryByRole('button', { name: 'Volver' })).not.toBeInTheDocument()
    expect(screen.queryByRole('list', { name: 'Pasos del registro' })).not.toBeInTheDocument()
  })

  it('«Continuar sin rol» registra la cuenta sin rol y el resumen lo dice', async () => {
    const user = userEvent.setup()
    const { calls } = stubBackend()

    await renderPage()
    await reachRoleStep(user)
    await user.click(screen.getByRole('button', { name: 'Continuar sin rol' }))

    expect(await screen.findByRole('heading', { name: 'Cuenta creada' })).toBeInTheDocument()
    expect(posts(calls)[0].body).toMatchObject({ cod_sis: '10452', id_rol: null })
    expect(screen.getByText('Sin rol')).toBeInTheDocument()
  })

  it('deshabilita los roles que no corresponden al código, con una pista, y deja «Continuar sin rol»', async () => {
    const user = userEvent.setup()
    stubBackend()

    await renderPage()
    await reachRoleStep(user, '202312345')

    const administrator = screen.getByRole('radio', { name: /Administrador/ })

    expect(administrator).toBeDisabled()
    expect(administrator).toHaveAccessibleDescription('Este código no corresponde a una cuenta de administrador.')
    expect(screen.getByRole('radio', { name: /Docente/ })).toBeEnabled()
    expect(screen.getByRole('radio', { name: /Auxiliar/ })).toBeEnabled()
    expect(screen.getByRole('button', { name: 'Continuar sin rol' })).toBeEnabled()
  })

  it.each([
    ['ADM0001', /Auxiliar/, 'auxiliar'],
    ['10452', /Auxiliar/, 'auxiliar'],
    ['10452', /Administrador/, 'administrador'],
  ])('con el código %s el rol %s queda deshabilitado', async (code, roleName, noun) => {
    const user = userEvent.setup()
    stubBackend()

    await renderPage()
    await reachRoleStep(user, code)

    expect(screen.getByRole('radio', { name: roleName })).toBeDisabled()
    expect(screen.getByText(`Este código no corresponde a una cuenta de ${noun}.`)).toBeInTheDocument()
  })

  it('si el servidor rechaza la combinación código-rol lo muestra junto a los roles, en el paso 3', async () => {
    const user = userEvent.setup()
    const { calls } = stubBackend({
      register: [
        {
          status: 422,
          body: {
            message: 'Los datos proporcionados no son válidos.',
            errors: { id_rol: ['El código SIS de un Auxiliar tiene 9 dígitos y empieza con un año.'] },
          },
        },
      ],
    })

    await renderPage()
    await reachRoleStep(user, '202312345')
    await user.click(screen.getByRole('radio', { name: /Auxiliar/ }))
    await user.click(screen.getByRole('button', { name: 'Registrar cuenta' }))

    expect(await screen.findByText('El código SIS de un Auxiliar tiene 9 dígitos y empieza con un año.')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Rol de la cuenta' })).toBeInTheDocument()
    expect(posts(calls)).toHaveLength(1)
  })

  it('si el servidor rechaza el código mismo, vuelve al paso 1 con el mensaje', async () => {
    const user = userEvent.setup()
    stubBackend({
      register: [
        {
          status: 422,
          body: {
            message: 'Los datos proporcionados no son válidos.',
            errors: { cod_sis: ['Ya existe una cuenta registrada con este código SIS.'] },
          },
        },
      ],
    })

    await renderPage()
    await reachRoleStep(user)
    await user.click(screen.getByRole('button', { name: 'Continuar sin rol' }))

    expect(await screen.findByText('Ya existe una cuenta registrada con este código SIS.')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Código SIS' })).toBeInTheDocument()
    expect(next()).toBeDisabled()

    await user.type(sisField(), '1')

    expect(screen.queryByText('Ya existe una cuenta registrada con este código SIS.')).not.toBeInTheDocument()
  })

  it('si el servidor rechaza un dato personal, vuelve al paso 2 y lo marca en su campo', async () => {
    const user = userEvent.setup()
    stubBackend({
      register: [
        {
          status: 422,
          body: {
            message: 'Los datos proporcionados no son válidos.',
            errors: { correo: ['Ya existe una cuenta registrada con este correo institucional.'] },
          },
        },
      ],
    })

    await renderPage()
    await reachRoleStep(user)
    await user.click(screen.getByRole('button', { name: 'Continuar sin rol' }))

    expect(await screen.findByText('Ya existe una cuenta registrada con este correo institucional.')).toBeInTheDocument()
    expect(screen.getByText('Corrija los campos marcados para registrar la cuenta.')).toBeInTheDocument()
    expect(screen.getByLabelText(/^Nombres/)).toHaveValue('Laura')
  })
})

describe('RegistrarCuentaPage — docente invitado', () => {
  async function checkGuest(user: ReturnType<typeof userEvent.setup>) {
    await user.click(screen.getByRole('checkbox', { name: /Registrar sin código SIS \(docente invitado\)/ }))
    await screen.findByDisplayValue('INV4821')
  }

  it('muestra el código INV que se usará, ya generado, en el campo deshabilitado, y habilita «Siguiente»', async () => {
    const user = userEvent.setup()
    stubBackend()

    await renderPage()
    await checkGuest(user)

    expect(sisField()).toBeDisabled()
    expect(sisField()).toHaveValue('INV4821')
    expect(screen.queryByText(/el sistema generará/i)).not.toBeInTheDocument()
    expect(next()).toBeEnabled()
    expect(steps()).toHaveLength(2)
  })

  it('el paso 2 termina en «Registrar cuenta», sin paso de rol, y crea la cuenta con el rol Docente invitado', async () => {
    const user = userEvent.setup()
    const { calls } = stubBackend({ register: [{ status: 201, body: { data: guestTeacher } }] })

    await renderPage()
    await checkGuest(user)
    await user.click(next())
    await fillPersonal(user)

    expect(screen.queryByRole('button', { name: /Siguiente/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Continuar sin rol' })).not.toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Registrar cuenta' }))

    expect(await screen.findByRole('heading', { name: 'Cuenta creada' })).toBeInTheDocument()

    const body = posts(calls)[0].body as Record<string, unknown>

    expect(body).toMatchObject({ sin_codigo_sis: true, cod_invitado: 'INV4821', id_rol: 4 })
    expect(body).not.toHaveProperty('cod_sis')
    expect(screen.getByText('INV4821')).toBeInTheDocument()
    expect(screen.getByText('Docente invitado')).toBeInTheDocument()
  })

  it('si el código mostrado se tomó mientras tanto, muestra el nuevo antes de crear y no registra aún', async () => {
    const user = userEvent.setup()
    const { calls } = stubBackend({
      register: [
        {
          status: 409,
          body: {
            message: 'El código INV4821 ya fue asignado a otra cuenta. Se propone el código INV7310.',
            codigo_sugerido: 'INV7310',
          },
        },
        { status: 201, body: { data: { ...guestTeacher, cod_sis: 'INV7310' } } },
      ],
    })

    await renderPage()
    await checkGuest(user)
    await user.click(next())
    await fillPersonal(user)
    await user.click(screen.getByRole('button', { name: 'Registrar cuenta' }))

    expect(await screen.findByText(/El código INV4821 ya fue asignado a otra cuenta\. Se usará INV7310/)).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Cuenta creada' })).not.toBeInTheDocument()
    expect(posts(calls)).toHaveLength(1)

    await user.click(screen.getByRole('button', { name: 'Volver' }))

    expect(sisField()).toHaveValue('INV7310')

    await user.click(next())
    await user.click(screen.getByRole('button', { name: 'Registrar cuenta' }))

    expect(await screen.findByRole('heading', { name: 'Cuenta creada' })).toBeInTheDocument()
    expect(posts(calls)).toHaveLength(2)
    expect(posts(calls)[1].body).toMatchObject({ cod_invitado: 'INV7310' })
  })

  it('si no se puede obtener el código lo informa, ofrece reintentar y no deja continuar', async () => {
    const user = userEvent.setup()
    stubBackend({ guestCode: { status: 500, body: { message: 'Error.' } } })

    await renderPage()
    await user.click(screen.getByRole('checkbox', { name: /docente invitado/ }))

    expect(await screen.findByText('No se pudo obtener un código para el docente invitado.')).toBeInTheDocument()
    expect(next()).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Reintentar' })).toBeInTheDocument()
  })

  it('al desmarcar la opción vuelve el campo del código SIS, vacío y editable', async () => {
    const user = userEvent.setup()
    stubBackend()

    await renderPage()
    await checkGuest(user)
    await user.click(screen.getByRole('checkbox', { name: /docente invitado/ }))

    expect(sisField()).toBeEnabled()
    expect(sisField()).toHaveValue('')
    expect(next()).toBeDisabled()
  })
})

describe('RegistrarCuentaPage — resumen', () => {
  it('«Registrar otra cuenta» empieza un asistente nuevo y vacío', async () => {
    const user = userEvent.setup()
    stubBackend()

    await renderPage()
    await reachRoleStep(user)
    await user.click(screen.getByRole('button', { name: 'Continuar sin rol' }))
    await screen.findByRole('heading', { name: 'Cuenta creada' })
    await user.click(screen.getByRole('button', { name: 'Registrar otra cuenta' }))

    expect(sisField()).toHaveValue('')
    expect(steps()[0]).toHaveAttribute('aria-current', 'step')
  })
})
