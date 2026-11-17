import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { savedDistributionFixture } from '@/test/roomDistributionFixtures'
import { RoomCard } from './RoomCard'

describe('RoomCard', () => {
  it('un ambiente completo usa advertencia y nunca peligro', () => {
    const data = savedDistributionFixture()
    const room = data.ambientes[0]
    if (!room) throw new Error('Falta un ambiente de prueba')
    render(<RoomCard room={{ ...room, asignados: 3 }} students={[]} rooms={data.ambientes} editable onMove={vi.fn()} />)
    const count = screen.getByLabelText('3 asignados de 3 lugares')
    expect(count).toHaveClass('bg-warn-soft', 'text-warn-fg')
    expect(count).toHaveAttribute('data-variant', 'outline')
    expect(count).not.toHaveClass('bg-danger-soft', 'bg-destructive')
  })
  it('con dos ambientes ofrece el destino directo de forma accesible', async () => {
    const data = savedDistributionFixture()
    const room = data.ambientes[0]
    if (!room) throw new Error('Falta un ambiente de prueba')
    const move = vi.fn()
    render(<RoomCard room={room} students={data.estudiantes.slice(0, 1)} rooms={data.ambientes} editable onMove={move} />)
    await userEvent.click(screen.getByRole('button', { name: /Mover a 691B: Álvarez Ana, SIS 001000001/ }))
    expect(move).toHaveBeenCalledWith(1, 20)
  })
  it('con tres ambientes muestra ocupación y deshabilita destinos llenos', async () => {
    const data = savedDistributionFixture()
    const room = data.ambientes[0]
    if (!room) throw new Error('Falta un ambiente de prueba')
    const rooms = [room, { ...room, id_ambiente: 20, nro_aula: '691B', asignados: 3 },
      { ...room, id_ambiente: 30, nro_aula: '691C', asignados: 0 }]
    const move = vi.fn()
    render(<RoomCard room={room} students={data.estudiantes.slice(0, 1)} rooms={rooms} editable onMove={move} />)
    await userEvent.click(screen.getByRole('button', { name: /Asignar ambiente: Álvarez/ }))
    expect(screen.getByRole('menuitem', { name: /691B.*El ambiente ya está lleno/ })).toHaveAttribute('aria-disabled', 'true')
    await userEvent.click(screen.getByRole('menuitem', { name: /691C/ }))
    expect(move).toHaveBeenCalledWith(1, 30)
  })
  it('solo lectura oculta los controles de movimiento', () => {
    const data = savedDistributionFixture()
    const room = data.ambientes[0]
    if (!room) throw new Error('Falta un ambiente de prueba')
    render(<RoomCard room={room} students={data.estudiantes} rooms={data.ambientes} editable={false} onMove={vi.fn()} />)
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })
})
