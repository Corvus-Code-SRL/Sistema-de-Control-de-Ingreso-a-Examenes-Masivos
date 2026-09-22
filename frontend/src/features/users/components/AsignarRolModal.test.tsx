import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { AsignarRolModal } from './AsignarRolModal'
import type { UserAccount } from '../types/users.types'
import { mockApi } from '@/test/http'
import {
  assignmentsResponse,
  assistant,
  makeAccount,
  roles,
  teacher,
  userMatchers,
} from '@/test/userFixtures'

function renderModal(account: UserAccount) {
  const onAssigned = vi.fn()
  const onOpenChange = vi.fn()

  render(
    <AsignarRolModal open account={account} onOpenChange={onOpenChange} onAssigned={onAssigned} />
  )

  return { onAssigned, onOpenChange }
}

/** Peticiones hechas a un endpoint, con su método y cuerpo ya leídos. */
function requestsTo(matcher: (url: string) => boolean) {
  return vi
    .mocked(fetch)
    .mock.calls.filter(([input]) => matcher(String(input)))
    .map(([, init]) => ({ method: init?.method, body: init?.body }))
}

describe('AsignarRolModal', () => {
  it('asigna directamente un rol a una cuenta que no tiene ninguno', async () => {
    const role = roles[1]
    mockApi([
      { matches: userMatchers.roles, body: { data: roles } },
      { matches: userMatchers.assignRole, body: { data: role, mensaje: 'Rol asignado correctamente.' } },
    ])

    const { onAssigned } = renderModal(makeAccount())

    const submit = await screen.findByRole('button', { name: 'Asignar rol' })
    // Sin rol elegido no se puede guardar.
    expect(submit).toBeDisabled()

    await userEvent.click(screen.getByRole('radio', { name: /docente/i }))
    await userEvent.click(submit)

    expect(onAssigned).toHaveBeenCalledWith(role)
    expect(requestsTo(userMatchers.assignRole)).toEqual([
      { method: 'POST', body: JSON.stringify({ id_rol: role.id_rol }) },
    ])
    // Sin rol previo no hay nada que confirmar ni asignaciones que revisar.
    expect(requestsTo(userMatchers.assignments)).toHaveLength(0)
  })

  it('solo ofrece los roles del catálogo y no deja elegir el rol actual', async () => {
    mockApi([{ matches: userMatchers.roles, body: { data: roles } }])

    renderModal(assistant)

    const options = await screen.findAllByRole('radio')
    expect(options).toHaveLength(3)
    expect(screen.getByRole('radio', { name: /auxiliar/i })).toBeDisabled()
    expect(screen.getByText('Rol actual')).toBeInTheDocument()
  })

  it('pide confirmar el cambio mostrando el rol actual y el nuevo', async () => {
    mockApi([
      { matches: userMatchers.roles, body: { data: roles } },
      { matches: userMatchers.assignRole, body: { data: roles[1] } },
    ])

    const { onAssigned } = renderModal(assistant)

    await userEvent.click(await screen.findByRole('radio', { name: /docente/i }))
    await userEvent.click(screen.getByRole('button', { name: 'Continuar' }))

    expect(screen.getByText(`¿Cambiar el rol de ${assistant.nombre}?`)).toBeInTheDocument()
    expect(screen.getByLabelText('De Auxiliar a Docente')).toBeInTheDocument()
    expect(onAssigned).not.toHaveBeenCalled()
    // Un Auxiliar no tiene grupos a su cargo: no se consultan asignaciones.
    expect(requestsTo(userMatchers.assignments)).toHaveLength(0)

    await userEvent.click(screen.getByRole('button', { name: /cambiar rol/i }))

    expect(onAssigned).toHaveBeenCalledWith(roles[1])
  })

  it('avisa de los grupos, materias y exámenes antes de cambiar el rol de un Docente', async () => {
    mockApi([
      { matches: userMatchers.roles, body: { data: roles } },
      {
        matches: userMatchers.assignments,
        body: assignmentsResponse({ grupos: 2, materias: 1, examenes: 1, tiene_activas: true }),
      },
    ])

    renderModal(teacher)

    await userEvent.click(await screen.findByRole('radio', { name: /auxiliar/i }))
    await userEvent.click(screen.getByRole('button', { name: 'Continuar' }))

    expect(await screen.findByText('Tiene asignaciones activas como Docente')).toBeInTheDocument()
    expect(screen.getByText(/2 grupos en el periodo actual/)).toBeInTheDocument()
    expect(screen.getByText(/1 materia con esos grupos/)).toBeInTheDocument()
    expect(screen.getByText(/1 examen programado/)).toBeInTheDocument()
  })

  it('no muestra el aviso si el Docente no tiene asignaciones activas', async () => {
    mockApi([
      { matches: userMatchers.roles, body: { data: roles } },
      { matches: userMatchers.assignments, body: assignmentsResponse() },
    ])

    renderModal(teacher)

    await userEvent.click(await screen.findByRole('radio', { name: /auxiliar/i }))
    await userEvent.click(screen.getByRole('button', { name: 'Continuar' }))

    expect(await screen.findByText(`¿Cambiar el rol de ${teacher.nombre}?`)).toBeInTheDocument()
    expect(screen.queryByText(/tiene asignaciones activas/i)).not.toBeInTheDocument()
  })

  it('muestra el motivo cuando el servidor rechaza la asignación', async () => {
    mockApi([
      { matches: userMatchers.roles, body: { data: roles } },
      {
        matches: userMatchers.assignRole,
        status: 403,
        body: { message: 'No puede modificar su propio rol. Pídalo a otro administrador.' },
      },
    ])

    const { onAssigned } = renderModal(makeAccount())

    await userEvent.click(await screen.findByRole('radio', { name: /docente/i }))
    await userEvent.click(screen.getByRole('button', { name: 'Asignar rol' }))

    expect(
      await screen.findByText('No puede modificar su propio rol. Pídalo a otro administrador.')
    ).toBeInTheDocument()
    expect(onAssigned).not.toHaveBeenCalled()
  })

  it('avisa que asignar un rol no habilita una cuenta deshabilitada', async () => {
    mockApi([{ matches: userMatchers.roles, body: { data: roles } }])

    renderModal(makeAccount({ estado: 'INACTIVO' }))

    expect(await screen.findByText('La cuenta está deshabilitada')).toBeInTheDocument()
  })

  it('permite reintentar si no se pudo cargar el catálogo de roles', async () => {
    mockApi([{ matches: userMatchers.roles, status: 500, invalidJson: true }])

    renderModal(makeAccount())

    expect(await screen.findByText('No se pudo cargar la información')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /reintentar/i })).toBeInTheDocument()
  })
})
