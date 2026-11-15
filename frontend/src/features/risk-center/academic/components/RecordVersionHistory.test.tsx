import { render, screen, within } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import type { RecordVersion } from '../types/riskRectification.types'
import { RecordVersionHistory } from './RecordVersionHistory'
import { RectificationStateBadge } from './RectificationStateBadge'

const versions: RecordVersion[] = [
  {
    numero_version: 3,
    estado: 'Vigente',
    tipo_falta: { id_falta: 2, nombre: 'Suplantación de identidad' },
    descripcion: null,
    motivo: 'Se verificó el documento original.',
    autor: { nombre_completo: 'Ana Administradora' },
    fecha: '2026-11-14T15:30:00+00:00',
  },
  {
    numero_version: 2,
    estado: 'Anterior',
    tipo_falta: { id_falta: 1, nombre: 'Copia en examen' },
    descripcion: 'Segunda descripción.',
    motivo: 'Primera corrección del tipo.',
    autor: { nombre_completo: 'Ana Administradora' },
    fecha: '2026-10-05T14:00:00+00:00',
  },
  {
    numero_version: 1,
    estado: 'Anterior',
    tipo_falta: { id_falta: 3, nombre: 'Material no permitido' },
    descripcion: 'Descripción original.',
    motivo: null,
    autor: { nombre_completo: 'Marcelo Quiroga' },
    fecha: '2026-09-11T19:00:00+00:00',
  },
]

describe('RecordVersionHistory', () => {
  it('lista las versiones en el orden recibido, la más nueva primero, con su estado', () => {
    render(<RecordVersionHistory versions={versions} />)

    const items = screen.getAllByRole('listitem')

    expect(items.map((item) => item.getAttribute('aria-label'))).toEqual([
      'Versión 3, Vigente',
      'Versión 2, Anterior',
      'Versión 1, Anterior',
    ])
  })

  it('cada versión muestra fecha y hora de La Paz, autor, descripción y motivo', () => {
    render(<RecordVersionHistory versions={versions} />)

    const [current, , original] = screen.getAllByRole('listitem')

    expect(within(current).getByText('14/11/2026 11:30')).toBeInTheDocument()
    expect(within(current).getByText('Ana Administradora')).toBeInTheDocument()
    expect(within(current).getByText('Sin descripción')).toBeInTheDocument()
    expect(within(current).getByText('Se verificó el documento original.')).toBeInTheDocument()
    expect(within(original).getByText('Registro original, sin rectificación')).toBeInTheDocument()
    expect(within(original).getByText('Descripción original.')).toBeInTheDocument()
  })

  it('no ofrece controles: el historial es de solo lectura', () => {
    render(<RecordVersionHistory versions={versions} />)

    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })
})

describe('RectificationStateBadge', () => {
  it('«Original» no muestra cuántas veces ni cuándo', () => {
    render(<RectificationStateBadge state="Original" count={0} lastAt={null} />)

    expect(screen.getByText('Original')).toBeInTheDocument()
    expect(screen.queryByText(/rectificaci/i)).not.toBeInTheDocument()
  })

  it('«Rectificado» indica el número de rectificaciones y la fecha de la última', () => {
    const { rerender } = render(
      <RectificationStateBadge state="Rectificado" count={1} lastAt="2026-10-05T14:00:00+00:00" />
    )

    expect(screen.getByText('1 rectificación · última 05/10/2026')).toBeInTheDocument()

    rerender(<RectificationStateBadge state="Rectificado" count={3} lastAt="2026-11-14T15:30:00+00:00" />)

    expect(screen.getByText('3 rectificaciones · última 14/11/2026')).toBeInTheDocument()
  })
})
