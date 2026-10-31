import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { DataPagination } from './DataPagination'

const base = { page: 1, perPage: 10, total: 3, totalPages: 1, onPageChange: vi.fn(), itemLabel: 'registros' }

describe('DataPagination', () => {
  it('por defecto resume con «Mostrando»', () => {
    render(<DataPagination {...base} />)

    expect(screen.getByText('Mostrando 1–3 de 3 registros')).toBeInTheDocument()
  })

  it('con summaryPrefix vacío resume solo el rango', () => {
    render(<DataPagination {...base} summaryPrefix="" />)

    expect(screen.getByText('1–3 de 3 registros')).toBeInTheDocument()
  })

  it('no dibuja nada cuando no hay elementos', () => {
    const { container } = render(<DataPagination {...base} total={0} />)

    expect(container).toBeEmptyDOMElement()
  })
})
