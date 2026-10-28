import { useCallback, useEffect, useState } from 'react'

const STORAGE_PREFIX = 'sciem.sidebar.v1.'

function storageKey(userId: string | undefined): string {
  return `${STORAGE_PREFIX}${userId ?? 'anonimo'}`
}

/** Lo guardado por el usuario, o `null` si no hay nada utilizable (o el navegador no deja leer). */
function readStored(userId: string | undefined): string[] | null {
  try {
    const raw = window.localStorage.getItem(storageKey(userId))
    if (raw === null) return null

    const parsed: unknown = JSON.parse(raw)

    if (Array.isArray(parsed) && parsed.every((id): id is string => typeof id === 'string')) {
      return parsed
    }
  } catch {
    // Almacenamiento bloqueado o contenido corrupto: el menú funciona igual, sin recordar.
  }

  return null
}

function writeStored(userId: string | undefined, openIds: ReadonlySet<string>): void {
  try {
    window.localStorage.setItem(storageKey(userId), JSON.stringify([...openIds]))
  } catch {
    // Modo privado o cuota llena: se pierde solo el recuerdo, no el menú.
  }
}

export interface SidebarSectionsState {
  isOpen: (sectionId: string) => boolean
  toggle: (sectionId: string) => void
  expandAll: () => void
  collapseAll: () => void
  allOpen: boolean
  noneOpen: boolean
}

/**
 * Qué secciones del menú están abiertas, recordado por usuario en `localStorage`.
 *
 * Cada pantalla monta su propio menú, así que el estado debe sobrevivir a la navegación: se lee del
 * almacenamiento al montar y se guarda en cada cambio. Sin nada guardado arranca solo con la
 * sección activa abierta (o la primera, si ninguna lo está). La sección que contiene la entrada
 * activa se abre siempre, aunque el usuario la hubiera contraído antes de navegar.
 *
 * @param userId Identifica de quién es el recuerdo.
 * @param sectionIds Secciones visibles, en orden.
 * @param activeSectionId Sección con la entrada activa, si la hay.
 */
export function useSidebarSections(
  userId: string | undefined,
  sectionIds: readonly string[],
  activeSectionId: string | undefined
): SidebarSectionsState {
  const [openIds, setOpenIds] = useState<ReadonlySet<string>>(() => {
    const stored = readStored(userId)
    const initial = new Set(stored ?? [])

    if (activeSectionId !== undefined) {
      initial.add(activeSectionId)
    } else if (stored === null && sectionIds.length > 0) {
      initial.add(sectionIds[0])
    }

    return initial
  })

  // Navegar a otra sección sin volver a montar el menú también la abre.
  useEffect(() => {
    if (activeSectionId === undefined) return

    setOpenIds((current) => {
      if (current.has(activeSectionId)) return current

      return new Set(current).add(activeSectionId)
    })
  }, [activeSectionId])

  useEffect(() => {
    writeStored(userId, openIds)
  }, [userId, openIds])

  const isOpen = useCallback((sectionId: string) => openIds.has(sectionId), [openIds])

  const toggle = useCallback((sectionId: string) => {
    setOpenIds((current) => {
      const next = new Set(current)

      if (!next.delete(sectionId)) next.add(sectionId)

      return next
    })
  }, [])

  const expandAll = useCallback(() => setOpenIds(new Set(sectionIds)), [sectionIds])
  const collapseAll = useCallback(() => setOpenIds(new Set()), [])

  return {
    isOpen,
    toggle,
    expandAll,
    collapseAll,
    allOpen: sectionIds.every((id) => openIds.has(id)),
    noneOpen: sectionIds.every((id) => !openIds.has(id))
  }
}
