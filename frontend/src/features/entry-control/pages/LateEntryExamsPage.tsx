import { useEffect, useState } from 'react'
import { Clock, RefreshCw } from 'lucide-react'
import { Link } from 'react-router-dom'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { ExamSummaryCard } from '@/components/common/ExamSummaryCard'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { AppShell } from '@/components/layout/AppShell'
import { Button } from '@/components/ui/button'
import { useInProgressExams } from '@/features/exams'
import { LATE_ENTRY_MESSAGES } from '../components/lateEntryMessages'
import { MAX_TIMEOUT_MS } from '../constants'
import { lateEntryPath } from '../paths'
import { openForLateEntry, scheduledEndOf } from '../utils/lateEntryExamWindow'

/**
 * Exámenes EN_CURSO cuyo fin programado no pasó; siempre en lista, también con uno solo. Un examen
 * sale de la lista al llegar su fin, sin esperar el cierre automático.
 */
export function LateEntryExamsPage() {
  const { status, data, error, reload } = useInProgressExams()
  const [now, setNow] = useState(() => Date.now())
  const exams = openForLateEntry(data ?? [], now)

  useEffect(() => {
    function reloadWhenVisible() {
      if (document.hidden) return
      setNow(Date.now())
      reload()
    }

    document.addEventListener('visibilitychange', reloadWhenVisible)
    return () => document.removeEventListener('visibilitychange', reloadWhenVisible)
  }, [reload])

  const nextEnd = Math.min(...exams.map(scheduledEndOf).filter((end): end is number => end !== null))

  useEffect(() => {
    const delay = Math.max(nextEnd - Date.now(), 0)
    if (!Number.isFinite(nextEnd) || delay > MAX_TIMEOUT_MS) return undefined
    const timer = window.setTimeout(() => setNow(Date.now()), delay)
    return () => window.clearTimeout(timer)
  }, [nextEnd])

  function refresh() {
    setNow(Date.now())
    reload()
  }

  return (
    <AppShell mobileTitle={LATE_ENTRY_MESSAGES.examsTitle} breadcrumbs={[{ label: LATE_ENTRY_MESSAGES.examsTitle }]}>
      <PageHeader
        title={LATE_ENTRY_MESSAGES.examsTitle}
        subtitle={LATE_ENTRY_MESSAGES.examsSubtitle}
        actions={
          <Button type="button" variant="outline" className="h-11" onClick={refresh}>
            <RefreshCw className="size-4" aria-hidden="true" />
            {LATE_ENTRY_MESSAGES.refresh}
          </Button>
        }
      />

      {status === 'loading' && <LoadingState rows={3} label={LATE_ENTRY_MESSAGES.loadingExams} />}

      {status === 'error' && error && <ErrorState error={error} onRetry={refresh} />}

      {status === 'success' && exams.length === 0 && (
        <EmptyState
          icon={Clock}
          title={LATE_ENTRY_MESSAGES.emptyTitle}
          description={LATE_ENTRY_MESSAGES.emptyDescription}
        />
      )}

      {status === 'success' && exams.length > 0 && (
        <ul className="space-y-3" aria-label={LATE_ENTRY_MESSAGES.examsTitle}>
          {exams.map((exam) => (
            <li key={exam.id}>
              <ExamSummaryCard
                title={exam.title}
                subject={exam.subject}
                dateLabel={exam.date ?? LATE_ENTRY_MESSAGES.missingDate}
                startsAt={exam.startsAt}
                endsAt={exam.endsAt}
                roomsLabel={LATE_ENTRY_MESSAGES.roomsLabel}
                rooms={exam.rooms}
                action={
                  <Button asChild className="h-11 w-full sm:w-auto">
                    <Link to={lateEntryPath(exam.id)}>{LATE_ENTRY_MESSAGES.openExam}</Link>
                  </Button>
                }
              />
            </li>
          ))}
        </ul>
      )}
    </AppShell>
  )
}
