// API pública del feature incidents
export { InProgressIncidentAction, InProgressIncidentsCount } from './components/InProgressIncidents'
export { IncidentsPage } from './pages/IncidentsPage'
export { ReportIncidentDialog } from './components/ReportIncidentDialog'
export type { ReportIncidentContext } from './types/incident.types'
export { usePendingIncidentsCount } from './hooks/usePendingIncidentsCount'
//De HU-057 Situacion Excepcional
export { ExamSituationDialog } from './components/ExamSituationDialog'
export { ExamSituationsPanel } from './components/ExamSituationsPanel'
export type { ExamSituationContext, SituationCreated } from './types/exam-situation.types'
export { ExamSituationsPage } from './pages/ExamSituationsPage'

export { InProgressSituationAction, InProgressSituationsPanel } from './components/InProgressSituations'
