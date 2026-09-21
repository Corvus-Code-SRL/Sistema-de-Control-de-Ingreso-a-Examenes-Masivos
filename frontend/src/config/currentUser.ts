import type { NavRole } from '@/config/navigation'

// TODO: reemplazar por el usuario autenticado cuando exista el módulo de auth.
export const currentUser: {
  name: string
  initials: string
  role: NavRole
  roleLabel: string
} = {
  name: 'Usuario Docente',
  initials: 'UD',
  role: 'docente',
  roleLabel: 'Docente',
}
