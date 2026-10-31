import { Clock, RefreshCw } from 'lucide-react'
import { Link, Navigate } from 'react-router-dom'
import { EmptyState } from '@/components/common/EmptyState'
import { ErrorState } from '@/components/common/ErrorState'
import { ExamSummaryCard } from '@/components/common/ExamSummaryCard'
import { LoadingState } from '@/components/common/LoadingState'
import { PageHeader } from '@/components/common/PageHeader'
import { AppShell } from '@/components/layout/AppShell'
import { Button } from '@/components/ui/button'
import { useInProgressExams } from '@/features/exams'
import { LATE_ENTRY_MESSAGES } from '../components/lateEntryMessages'
import { lateEntryPath } from '../paths'

/** Exámenes EN_CURSO en los que se puede registrar con retraso; con uno solo, entra directo. */
export function LateEntryExamsPage() {
  const { status, data, error, reload } = useInProgressExams()
  const exams = data ?? []

  if (status === 'success' && exams.length === 1) {
    return <Navigate to={lateEntryPath(exams[0].id)} replace />
  }

  return (
    <AppShell mobileTitle={LATE_ENTRY_MESSAGES.examsTitle} breadcrumbs={[{ label: LATE_ENTRY_MESSAGES.examsTitle }]}>
      <PageHeader
        title={LATE_ENTRY_MESSAGES.examsTitle}
        subtitle={LATE_ENTRY_MESSAGES.examsSubtitle}
        actions={
          <Button type="button" variant="outline" onClick={reload}>
            <RefreshCw className="size-4" aria-hidden="true" />
            {LATE_ENTRY_MESSAGES.refresh}
          </Button>
        }
      />

      {status === 'loading' && <LoadingState rows={3} label={LATE_ENTRY_MESSAGES.loadingExams} />}

      {status === 'error' && error && <ErrorState error={error} onRetry={reload} />}

      {status === 'success' && exams.length === 0 && (
        <EmptyState
          icon={Clock}
          title={LATE_ENTRY_MESSAGES.emptyTitle}
          description={LATE_ENTRY_MESSAGES.emptyDescription}
        />
      )}

      {status === 'success' && exams.length > 1 && (
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
                  <Button asChild className="h-11 w-full sm:h-8 sm:w-auto" size="sm">
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
