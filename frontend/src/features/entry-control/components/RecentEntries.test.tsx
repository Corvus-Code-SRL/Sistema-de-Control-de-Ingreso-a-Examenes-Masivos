import { render, screen, within } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import type { EntryGroupProgress, RecentEntry } from '../types/entry-control.types'
import { RecentEntries } from './RecentEntries'

function entry(id: number, name: string, groupId: number): RecentEntry {
  return {
    id_estudiante: id,
    id_grupo: groupId,
    cod_sis: `20260${id}`,
    nombre: name,
    apellido_paterno: 'Pérez',
    apellido_materno: null,
    hora_ingreso: '08:05:00',
    registrado_en: `2026-12-14T12:0${id}:00+00:00`,
    nro_aula: '691A',
    controlador_nombre: 'Ana',
    controlador_apellido: 'Rojas',
  }
}

const GROUPS: EntryGroupProgress[] = [
  { id_grupo: 1, num_grupo: '1', docente: 'Ana Rojas', total: 2, ingresados: 1 },
  { id_grupo: 3, num_grupo: '3', docente: 'Luis Vargas', total: 3, ingresados: 1 },
]

describe('RecentEntries (HU-027)', () => {
  it('con la lista junta muestra una sola lista, sin grupos', () => {
    render(<RecentEntries entries={[entry(1, 'Juan', 1), entry(2, 'Rosa', 3)]} />)

    expect(screen.queryByRole('list', { name: 'Avance por grupo' })).not.toBeInTheDocument()
    expect(within(screen.getByRole('list', { name: 'Últimos ingresos' })).getAllByRole('listitem')).toHaveLength(2)
  })

  it('con la lista separada muestra el avance y los ingresos de cada grupo con su docente', () => {
    render(<RecentEntries entries={[entry(2, 'Rosa', 3), entry(1, 'Juan', 1)]} groups={GROUPS} />)

    const progress = screen.getByRole('list', { name: 'Avance por grupo' })
    expect(within(progress).getByText('Grupo 1 · Ana Rojas')).toBeInTheDocument()
    expect(within(progress).getAllByText('1 de 2 ingresados')).toHaveLength(1)
    expect(within(progress).getByText('1 de 3 ingresados')).toBeInTheDocument()

    const first = screen.getByRole('region', { name: 'Grupo 1 · Ana Rojas' })
    expect(within(first).getByText('Juan Pérez')).toBeInTheDocument()
    expect(within(first).queryByText('Rosa Pérez')).not.toBeInTheDocument()
    expect(within(screen.getByRole('region', { name: 'Grupo 3 · Luis Vargas' })).getByText('Rosa Pérez')).toBeInTheDocument()
  })

  it('con la lista separada y sin ingresos muestra el avance en cero y el texto vacío', () => {
    render(<RecentEntries entries={[]} groups={GROUPS.map((group) => ({ ...group, ingresados: 0 }))} />)

    expect(screen.getByText('0 de 2 ingresados')).toBeInTheDocument()
    expect(screen.getByText('Aún no se registraron ingresos.')).toBeInTheDocument()
  })
})
