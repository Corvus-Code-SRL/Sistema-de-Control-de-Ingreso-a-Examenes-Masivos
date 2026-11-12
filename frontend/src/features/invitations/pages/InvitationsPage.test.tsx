import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { mockApi } from '@/test/http'
import {
  expired,
  finalExam,
  firstPartial,
  rejected,
  stubInvitationsBackend,
} from '@/test/invitationFixtures'
import { renderWithRouter } from '@/test/render'
import { INVITATIONS_CHANGED_EVENT } from '../hooks/usePendingInvitationsCount'
import { InvitationsPage } from './InvitationsPage'

function renderPage() {
  renderWithRouter(<InvitationsPage />, { route: '/invitaciones', path: '/invitaciones' })
}

function list() {
  return screen.findByRole('list', { name: 'Invitaciones' })
}

async function openInvitation(user: ReturnType<typeof userEvent.setup>, examName: RegExp) {
  await user.click(within(await list()).getByRole('button', { name: examName }))

  return screen.findByRole('heading', { level: 2 })
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('InvitationsPage — bandeja (CA-1, CA-2)', () => {
  it('abre en «Pendientes» con el número de cada pestaña', async () => {
    stubInvitationsBackend([firstPartial, finalExam, expired, rejected])
    renderPage()

    const pending = screen.getByRole('tab', { name: /Pendientes/ })
    expect(pending).toHaveAttribute('aria-selected', 'true')

    await list()
    expect(pending).toHaveTextContent('2')
    expect(screen.getByRole('tab', { name: /Cerradas/ })).toHaveTextContent('2')
    expect(screen.getByRole('tab', { name: /Aceptadas/ })).not.toHaveTextContent(/\d/)
  })

  it('lista solo lo que devuelve el servidor, en su orden, con fecha, hora y quién invita', async () => {
    stubInvitationsBackend([firstPartial, finalExam])
    renderPage()

    const items = within(await list()).getAllByRole('button')

    expect(items).toHaveLength(2)
    expect(items[0]).toHaveTextContent('1er Parcial · Cálculo I')
    expect(items[0]).toHaveTextContent('mar 20/10 · 10:00 · invita Marcelo Quiroga Andrade')
    expect(items[1]).toHaveTextContent('Examen final · Cálculo I')
    expect(items[1]).toHaveTextContent('vie 13/11 · 08:15 · invita Rosario Salazar Vega')
  })

  it('«Cerradas» reúne las rechazadas y las vencidas', async () => {
    const user = userEvent.setup()
    stubInvitationsBackend([firstPartial, expired, rejected])
    renderPage()
    await list()

    await user.click(screen.getByRole('tab', { name: /Cerradas/ }))

    await waitFor(() => expect(within(screen.getByRole('list', { name: 'Invitaciones' })).getAllByRole('button')).toHaveLength(2))
    const closed = within(screen.getByRole('list', { name: 'Invitaciones' }))
    expect(closed.getByText('Vencida')).toBeInTheDocument()
    expect(closed.getByText('Rechazada')).toBeInTheDocument()
  })

  it('sin pendientes muestra el estado vacío', async () => {
    stubInvitationsBackend([rejected])
    renderPage()

    expect(await screen.findByText('No tiene invitaciones pendientes')).toBeInTheDocument()
  })

  it('no se rompe si la respuesta no trae meta', async () => {
    mockApi([{ matches: (url) => url.includes('/api/invitaciones'), body: { data: [] } }])
    renderPage()

    expect(await screen.findByText('No tiene invitaciones pendientes')).toBeInTheDocument()
  })

  it('si la carga falla ofrece reintentar', async () => {
    mockApi([{ matches: (url) => url.includes('/api/invitaciones'), status: 500, body: { message: 'Error' } }])
    renderPage()

    expect(await screen.findByRole('button', { name: /Reintentar/ })).toBeInTheDocument()
  })
})

describe('InvitationsPage — detalle (CA-3, CA-4, CA-6)', () => {
  it('muestra examen, materia, fecha y hora, ambientes, quién invita y su mensaje', async () => {
    const user = userEvent.setup()
    stubInvitationsBackend([firstPartial])
    renderPage()

    expect(await openInvitation(user, /1er Parcial/)).toHaveTextContent('1er Parcial')

    expect(screen.getByText('Cálculo I · Ing. de Sistemas · 2/2026')).toBeInTheDocument()
    expect(screen.getByText('mar 20/10/2026 · 10:00 a 11:30')).toBeInTheDocument()
    expect(screen.getByText('Aula 691A (30) · Aula 691B (30)')).toBeInTheDocument()
    expect(screen.getByText('Grupo 2 · Grupo 5 (55 estudiantes)')).toBeInTheDocument()
    expect(screen.getByText(/Invita Marcelo Quiroga Andrade \(docente responsable\) · enviada el 20\/10 08:12/)).toBeInTheDocument()
    expect(screen.getByText(/Tomemos el 1er parcial juntos/)).toBeInTheDocument()
  })

  it('ofrece solo sus grupos de la materia; un grupo sin nómina no se puede marcar', async () => {
    const user = userEvent.setup()
    stubInvitationsBackend([firstPartial])
    renderPage()
    await openInvitation(user, /1er Parcial/)

    const groups = within(screen.getByRole('group', { name: 'Mis grupos de Cálculo I que sumo al examen' }))

    expect(groups.getByRole('checkbox', { name: /Grupo 3/ })).toBeEnabled()
    expect(groups.getByRole('checkbox', { name: /Grupo 6/ })).toBeEnabled()
    expect(groups.getByRole('checkbox', { name: /Grupo 7/ })).toBeDisabled()
    expect(groups.getByText('38 inscritos')).toBeInTheDocument()
    expect(groups.getByText('sin nómina')).toBeInTheDocument()
  })

  it('dice qué podrá hacer como colaborador y qué queda para el responsable', async () => {
    const user = userEvent.setup()
    stubInvitationsBackend([firstPartial])
    renderPage()
    await openInvitation(user, /1er Parcial/)

    expect(within(screen.getByRole('list', { name: 'Podrá' })).getByText('Controlar el ingreso en la puerta')).toBeInTheDocument()
    expect(
      within(screen.getByRole('list', { name: 'Solo el docente responsable' })).getByText('Editar o cancelar el examen')
    ).toBeInTheDocument()
  })
})

describe('InvitationsPage — aceptar (CA-5, CA-7, CA-9)', () => {
  it('sin grupos marcados no abre la confirmación', async () => {
    const user = userEvent.setup()
    const backend = stubInvitationsBackend([firstPartial])
    renderPage()
    await openInvitation(user, /1er Parcial/)

    await user.click(screen.getByRole('button', { name: 'Aceptar con 0 grupos' }))

    expect(screen.getByText('Elija al menos uno de sus grupos.')).toBeInTheDocument()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(backend.calls.some((call) => call.method === 'POST')).toBe(false)
  })

  it('con dos grupos avisa que los ambientes no alcanzan, pero deja aceptar', async () => {
    const user = userEvent.setup()
    stubInvitationsBackend([firstPartial])
    renderPage()
    await openInvitation(user, /1er Parcial/)

    await user.click(screen.getByRole('checkbox', { name: /Grupo 3/ }))
    await user.click(screen.getByRole('checkbox', { name: /Grupo 6/ }))
    await user.click(screen.getByRole('button', { name: 'Aceptar con 2 grupos' }))

    const dialog = await screen.findByRole('dialog', { name: '¿Aceptar la invitación?' })
    expect(dialog).toHaveTextContent('El examen pasará de 55 a 115 estudiantes.')
    expect(within(dialog).getByText('Los ambientes no alcanzan')).toBeInTheDocument()
    expect(dialog).toHaveTextContent('El 691A y el 691B suman 60 lugares para 115 estudiantes.')
    expect(within(dialog).getByRole('button', { name: 'Aceptar invitación' })).toBeEnabled()
  })

  it('si caben, la confirmación no muestra el aviso de capacidad', async () => {
    const user = userEvent.setup()
    const roomy = { ...firstPartial, ambientes: [{ id_ambiente: 9, nro_aula: '690', capacidad: 200 }] }
    stubInvitationsBackend([roomy])
    renderPage()
    await openInvitation(user, /1er Parcial/)

    await user.click(screen.getByRole('checkbox', { name: /Grupo 6/ }))
    await user.click(screen.getByRole('button', { name: 'Aceptar con 1 grupo' }))

    const dialog = await screen.findByRole('dialog', { name: '¿Aceptar la invitación?' })
    expect(within(dialog).queryByText('Los ambientes no alcanzan')).not.toBeInTheDocument()
  })

  it('aceptar envía los grupos marcados y la invitación pasa a «Aceptadas»', async () => {
    const user = userEvent.setup()
    const changed = vi.fn()
    window.addEventListener(INVITATIONS_CHANGED_EVENT, changed)
    const backend = stubInvitationsBackend([firstPartial, finalExam])
    renderPage()
    await openInvitation(user, /1er Parcial/)

    await user.click(screen.getByRole('checkbox', { name: /Grupo 3/ }))
    await user.click(screen.getByRole('checkbox', { name: /Grupo 6/ }))
    await user.click(screen.getByRole('button', { name: 'Aceptar con 2 grupos' }))
    await user.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Aceptar invitación' }))

    const post = backend.calls.find((call) => call.method === 'POST')
    expect(post?.url).toMatch(/\/invitaciones\/11\/aceptar$/)
    expect(post?.body).toEqual({ grupos: [3, 6] })

    expect(await screen.findByText('Invitación aceptada')).toBeInTheDocument()
    expect(screen.getByText('Grupo 3 y Grupo 6 ya forman parte del 1er Parcial. Marcelo verá «Aceptada».')).toBeInTheDocument()
    await waitFor(() => expect(screen.getByRole('tab', { name: /Aceptadas/ })).toHaveAttribute('aria-selected', 'true'))
    expect(await screen.findByText(/Aceptada el 20\/10 08:40/)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Aceptar con/ })).not.toBeInTheDocument()
    expect(changed).toHaveBeenCalled()

    window.removeEventListener(INVITATIONS_CHANGED_EVENT, changed)
  })

  it('si el examen ya abrió el control (409) lo explica y recarga la bandeja', async () => {
    const user = userEvent.setup()
    stubInvitationsBackend([firstPartial], {
      acceptResponse: {
        status: 409,
        body: { message: 'El examen ya abrió el control de ingreso; la invitación quedó sin efecto.' },
      },
    })
    renderPage()
    await openInvitation(user, /1er Parcial/)

    await user.click(screen.getByRole('checkbox', { name: /Grupo 3/ }))
    await user.click(screen.getByRole('button', { name: 'Aceptar con 1 grupo' }))
    await user.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Aceptar invitación' }))

    expect(await screen.findByText('La invitación ya no se puede responder')).toBeInTheDocument()
    expect(screen.getByText('El examen ya abrió el control de ingreso; la invitación quedó sin efecto.')).toBeInTheDocument()
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })
})

describe('InvitationsPage — rechazar y vencida (CA-8, CA-10)', () => {
  it('rechazar con motivo opcional la pasa a «Cerradas»', async () => {
    const user = userEvent.setup()
    const backend = stubInvitationsBackend([firstPartial])
    renderPage()
    await openInvitation(user, /1er Parcial/)

    await user.click(screen.getByRole('button', { name: 'Rechazar' }))
    const dialog = await screen.findByRole('dialog', { name: '¿Rechazar la invitación?' })
    expect(dialog).toHaveTextContent('Marcelo verá «Rechazada» y el examen no cambia.')
    await user.type(within(dialog).getByLabelText('Motivo (opcional)'), 'Tengo otro examen.')
    await user.click(within(dialog).getByRole('button', { name: 'Rechazar invitación' }))

    const post = backend.calls.find((call) => call.method === 'POST')
    expect(post?.url).toMatch(/\/invitaciones\/11\/rechazar$/)
    expect(post?.body).toEqual({ motivo: 'Tengo otro examen.' })
    expect(await screen.findByText('Invitación rechazada')).toBeInTheDocument()
    expect(await screen.findByText('No tiene invitaciones pendientes')).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: /Cerradas/ })).toHaveTextContent('1')
  })

  it('el motivo puede quedar vacío', async () => {
    const user = userEvent.setup()
    const backend = stubInvitationsBackend([firstPartial])
    renderPage()
    await openInvitation(user, /1er Parcial/)

    await user.click(screen.getByRole('button', { name: 'Rechazar' }))
    await user.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Rechazar invitación' }))

    await waitFor(() => expect(backend.calls.find((call) => call.method === 'POST')?.body).toEqual({ motivo: null }))
  })

  it('una vencida se ve sin «Aceptar» ni «Rechazar»', async () => {
    const user = userEvent.setup()
    stubInvitationsBackend([expired])
    renderPage()
    await screen.findByText('No tiene invitaciones pendientes')

    await user.click(screen.getByRole('tab', { name: /Cerradas/ }))
    await openInvitation(user, /Examen diagnóstico/)

    expect(screen.getByText('La invitación venció')).toBeInTheDocument()
    expect(screen.getByText('La invitación venció').parentElement).toHaveTextContent(
      'El examen abrió el control de ingreso el 06/10 09:50 sin una respuesta suya.'
    )
    expect(screen.queryByRole('button', { name: /Aceptar con/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Rechazar' })).not.toBeInTheDocument()
    expect(screen.queryByRole('checkbox')).not.toBeInTheDocument()
  })

  it('una rechazada muestra su motivo y no se puede responder de nuevo', async () => {
    const user = userEvent.setup()
    stubInvitationsBackend([rejected])
    renderPage()
    await screen.findByText('No tiene invitaciones pendientes')

    await user.click(screen.getByRole('tab', { name: /Cerradas/ }))
    await openInvitation(user, /Recuperatorio/)

    expect(screen.getByText('Tengo otro examen a la misma hora.')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Rechazar' })).not.toBeInTheDocument()
  })
})
