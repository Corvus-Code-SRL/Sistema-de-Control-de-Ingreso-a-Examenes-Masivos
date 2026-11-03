import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { detailOf, stubAuditLogApi } from '@/test/auditLogFixtures'
import { renderWithRouter } from '@/test/render'
import type { AuditChange } from '../types/auditLog.types'
import { AuditLogDetailPanel } from './AuditLogDetailPanel'

function renderPanel(id: number | null, onClose = vi.fn()) {
  renderWithRouter(<AuditLogDetailPanel id={id} onClose={onClose} />)

  return { onClose }
}

async function openPanel() {
  return screen.findByRole('dialog')
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('AuditLogDetailPanel', () => {
  it('muestra fecha, usuario, acción, módulo, registro y el cambio campo por campo', async () => {
    stubAuditLogApi()

    renderPanel(130)

    const panel = await openPanel()

    expect(await within(panel).findByText('Número de grupo')).toBeInTheDocument()
    expect(within(panel).getByText('20/10/2026 10:21')).toBeInTheDocument()
    expect(within(panel).getByText('Marcelo Quiroga Andrade')).toBeInTheDocument()
    expect(within(panel).getByText('Modificar')).toBeInTheDocument()
    expect(within(panel).getByText('Grupos')).toBeInTheDocument()
    expect(within(panel).getByText('Grupo #7')).toBeInTheDocument()
    expect(within(panel).getByText('Anterior').nextElementSibling).toHaveTextContent('3')
    expect(within(panel).getByText('Nuevo').nextElementSibling).toHaveTextContent('5')
    expect(within(panel).queryByText('Docente del examen')).not.toBeInTheDocument()
  })

  it('es de solo lectura: lo único que ofrece es cerrar', async () => {
    stubAuditLogApi()

    renderPanel(130)

    const panel = await openPanel()
    await within(panel).findByText('Número de grupo')

    expect(within(panel).getByText('Solo lectura')).toBeInTheDocument()
    expect(
      within(panel)
        .getAllByRole('button')
        .map((button) => button.textContent || button.getAttribute('aria-label'))
        .sort()
    ).toEqual(['Cerrar', 'Cerrar detalle'].sort())
  })

  it('una creación muestra «anterior» vacío', async () => {
    const changes: AuditChange[] = [{ campo: 'estado', etiqueta: 'Estado', oculto: false, anterior: null, nuevo: 'PROGRAMADO' }]
    stubAuditLogApi({ details: { 129: detailOf(129, { cambios: changes }) } })

    renderPanel(129)

    const panel = await openPanel()

    expect((await within(panel).findByText('Anterior')).nextElementSibling).toHaveTextContent('(sin valor)')
    expect(within(panel).getByText('Nuevo').nextElementSibling).toHaveTextContent('PROGRAMADO')
  })

  it('una eliminación muestra «nuevo» vacío', async () => {
    const changes: AuditChange[] = [{ campo: 'estado', etiqueta: 'Estado', oculto: false, anterior: 'ACTIVO', nuevo: null }]
    stubAuditLogApi({ details: { 126: detailOf(126, { cambios: changes }) } })

    renderPanel(126)

    const panel = await openPanel()

    expect((await within(panel).findByText('Nuevo')).nextElementSibling).toHaveTextContent('(sin valor)')
    expect(within(panel).getByText('Anterior').nextElementSibling).toHaveTextContent('ACTIVO')
  })

  it('los booleanos salen como «Sí» y «No»', async () => {
    const changes: AuditChange[] = [{ campo: 'activo', etiqueta: 'Activo', oculto: false, anterior: true, nuevo: false }]
    stubAuditLogApi({ details: { 126: detailOf(126, { cambios: changes }) } })

    renderPanel(126)

    const panel = await openPanel()

    expect((await within(panel).findByText('Anterior')).nextElementSibling).toHaveTextContent('Sí')
    expect(within(panel).getByText('Nuevo').nextElementSibling).toHaveTextContent('No')
  })

  it('un campo oculto muestra «(oculto)» y ningún valor', async () => {
    const changes: AuditChange[] = [{ campo: 'contrasenia', etiqueta: 'Contraseña', oculto: true }]
    stubAuditLogApi({ details: { 126: detailOf(126, { cambios: changes }) } })

    renderPanel(126)

    const panel = await openPanel()

    expect(await within(panel).findByText('Contraseña')).toBeInTheDocument()
    expect(within(panel).getByText('(oculto)')).toBeInTheDocument()
    expect(within(panel).queryByText('Anterior')).not.toBeInTheDocument()
    expect(within(panel).queryByText('Nuevo')).not.toBeInTheDocument()
  })

  it('una operación automática indica el docente del examen con su SIS', async () => {
    stubAuditLogApi()

    renderPanel(128)

    const panel = await openPanel()

    expect(await within(panel).findByText('Automático')).toBeInTheDocument()
    expect(within(panel).getByText('Docente del examen')).toBeInTheDocument()
    expect(within(panel).getByText('Carlos Rojas Vargas (202000111)')).toBeInTheDocument()
  })

  it('una operación sin cambios de campos lo dice', async () => {
    stubAuditLogApi({ details: { 127: detailOf(127, { cambios: [] }) } })

    renderPanel(127)

    const panel = await openPanel()

    expect(await within(panel).findByText('Esta operación no registró cambios de campos.')).toBeInTheDocument()
    expect(within(panel).getByText('Sistema')).toBeInTheDocument()
  })

  it('un id inexistente muestra «no encontrada» dentro del panel', async () => {
    stubAuditLogApi()

    renderPanel(99999)

    const panel = await openPanel()

    expect(await within(panel).findByText('Operación no encontrada')).toBeInTheDocument()
  })

  it('un id inválido no pide nada al servidor', async () => {
    const api = stubAuditLogApi()

    renderPanel(null)

    const panel = await openPanel()

    expect(within(panel).getByText('Operación no encontrada')).toBeInTheDocument()
    expect(api.calls).toHaveLength(0)
  })

  it('sin permiso muestra el error del servidor dentro del panel', async () => {
    stubAuditLogApi({ detailForbidden: true })

    renderPanel(130)

    const panel = await openPanel()

    expect(await within(panel).findByRole('alert')).toHaveTextContent('Sin permiso')
    expect(within(panel).getByText('No tiene permiso para ver esta sección.')).toBeInTheDocument()
  })

  it('«Cerrar» avisa al cerrar', async () => {
    const user = userEvent.setup()
    stubAuditLogApi()

    const { onClose } = renderPanel(130)

    const panel = await openPanel()
    await within(panel).findByText('Número de grupo')
    await user.click(within(panel).getByRole('button', { name: 'Cerrar detalle' }))

    await waitFor(() => expect(onClose).toHaveBeenCalled())
  })
})
