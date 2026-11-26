import { useCallback, useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import type { DistributionConfirmation } from '../types/roomDistribution.types'

interface PendingConfirmation { kind: DistributionConfirmation; action: () => void }

/** Protege la descarga del documento, los enlaces y el botón «atrás», sin alterar BrowserRouter ni otras HU. */
export function useDistributionLeaveGuard(hasChanges: boolean, busy: boolean) {
  const navigate = useNavigate()
  const [pending, setPending] = useState<PendingConfirmation | null>(null)
  const request = useCallback((kind: DistributionConfirmation, action: () => void, confirm = hasChanges): void => {
    if (busy) return
    if (confirm) setPending({ kind, action })
    else action()
  }, [hasChanges, busy])

  useEffect(() => {
    if (!hasChanges && !busy) return
    const beforeUnload = (event: BeforeUnloadEvent): void => {
      event.preventDefault()
      event.returnValue = ''
    }
    const followLink = (event: MouseEvent): void => {
      if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return
      const anchor = event.target instanceof Element ? event.target.closest('a[href]') : null
      if (!(anchor instanceof HTMLAnchorElement) || anchor.download || (anchor.target && anchor.target !== '_self')) return
      const destination = new URL(anchor.href)
      if (destination.origin !== window.location.origin) return
      if (destination.pathname === window.location.pathname && destination.search === window.location.search) return
      event.preventDefault()
      if (busy) return
      setPending({ kind: 'leave', action: () => {
        navigate(destination.pathname + destination.search + destination.hash)
      } })
    }
    window.addEventListener('beforeunload', beforeUnload)
    document.addEventListener('click', followLink, true)
    return () => {
      window.removeEventListener('beforeunload', beforeUnload)
      document.removeEventListener('click', followLink, true)
    }
  }, [hasChanges, busy, navigate])

  // Botón «atrás»: con cambios se agrega una entrada de historial; al volver se repone y se pide confirmar.
  useEffect(() => {
    if (!hasChanges) return
    const guard = { distributionGuard: true }
    window.history.pushState(guard, '')
    const onPopState = (): void => {
      window.history.pushState(guard, '')
      setPending({ kind: 'leave', action: () => {
        window.removeEventListener('popstate', onPopState)
        window.history.go(-2)
      } })
    }
    window.addEventListener('popstate', onPopState)
    return () => window.removeEventListener('popstate', onPopState)
  }, [hasChanges])

  const confirm = (): void => {
    if (!pending || busy) return
    setPending(null)
    pending.action()
  }
  return { pending: pending?.kind ?? null, request, confirm, cancel: () => setPending(null) }
}
