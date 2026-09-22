import {
  CheckCircle2,
  FileClock,
  LoaderCircle,
} from 'lucide-react'
import { useEffect, useRef } from 'react'

import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'

import {
  groupLabel,
  type Group,
} from '@/features/groups'

import { useRosterUpload } from '../hooks/useRosterUpload'
import { RosterConfirmStep } from './RosterConfirmStep'
import { RosterFileStep } from './RosterFileStep'
import { RosterPreviewStep } from './RosterPreviewStep'
import { RosterResultStep } from './RosterResultStep'

interface RosterUploadPanelProps {
  group: Group
  subjectName: string
  onReload: () => void | Promise<unknown>
}

/**
 * Orquesta todo el flujo visual de carga de nómina.
 */
export function RosterUploadPanel({
  group,
  subjectName,
  onReload,
}: RosterUploadPanelProps) {
  const upload = useRosterUpload(group.id_grupo)
  const resultReloadedRef = useRef(false)

  useEffect(() => {
    if (upload.step === 'result' && upload.result) {
      if (!resultReloadedRef.current) {
        resultReloadedRef.current = true
        void onReload()
      }

      return
    }

    resultReloadedRef.current = false
  }, [onReload, upload.result, upload.step])

  const handleResultBack = (): void => {
    if (!resultReloadedRef.current) {
        void onReload()
    }
    upload.reset()
  }

  const activeStep = getActiveStep(upload.step)

  return (
    <Card key={group.id_grupo} className="overflow-hidden">
      <CardHeader className="border-b">
        <div className="flex items-center justify-between gap-4">
          <CardTitle className="sciem-h2">
            Carga de nómina
          </CardTitle>

          <span className="sciem-caption">
            {groupLabel(group)}
          </span>
        </div>

        <RosterStepIndicator activeStep={activeStep} />
      </CardHeader>

      {upload.step === 'file' && (
        <RosterFileStep
          subjectName={subjectName}
          groupLabel={groupLabel(group)}
          file={upload.file}
          errorMessage={upload.errorMessage}
          canRetry={upload.canRetry}
          onSelectFile={upload.selectFile}
          onSubmitPreview={upload.submitPreview}
          onRetry={upload.retry}
          onReset={upload.reset}
        />
      )}

      {upload.step === 'processing' && (
        <ProcessingState
          file={upload.file}
          onCancel={upload.reset}
        />
      )}

      {upload.step === 'preview' && upload.preview && (
        <RosterPreviewStep
          subjectName={subjectName}
          groupLabel={groupLabel(group)}
          file={upload.file}
          preview={upload.preview}
          onSelectFile={upload.selectFile}
          onCancel={upload.reset}
          onConfirm={upload.goToConfirm}
        />
      )}

      {upload.step === 'confirm' && upload.preview && (
        <RosterConfirmStep
          subjectName={subjectName}
          groupLabel={groupLabel(group)}
          preview={upload.preview}
          onBack={upload.backToPreview}
          onConfirm={upload.confirm}
          isConfirming={false}
        />
      )}

      {upload.step === 'confirming' && upload.preview && (
        <RosterConfirmStep
          subjectName={subjectName}
          groupLabel={groupLabel(group)}
          preview={upload.preview}
          onBack={upload.backToPreview}
          onConfirm={upload.confirm}
          isConfirming
        />
      )}

      {upload.step === 'result' && upload.result && (
        <RosterResultStep
          result={upload.result}
          onBack={handleResultBack}
        />
      )}
    </Card>
  )
}

function ProcessingState({
  file,
  onCancel,
}: {
  file: File | null
  onCancel: () => void
}) {
  return (
    <>
      <CardContent className="space-y-5">
        <div
          role="status"
          aria-live="polite"
          className="flex flex-col items-center justify-center rounded-xl border border-border-soft bg-sunken px-6 py-12 text-center"
        >
          <span className="flex size-12 items-center justify-center rounded-full bg-brand-soft text-brand">
            <LoaderCircle
              aria-hidden="true"
              className="size-6 animate-spin"
            />
          </span>

          <p className="sciem-h3 mt-4">Procesando…</p>

          {file && (
            <p className="sciem-supporting mt-1 max-w-md truncate text-muted-foreground">
              {file.name}
            </p>
          )}

          <div className="mt-4 flex items-center gap-2 text-muted-foreground">
            <FileClock aria-hidden="true" className="size-4" />

            <span className="sciem-supporting">
              La nómina del grupo todavía no se modificó.
            </span>
          </div>
        </div>
      </CardContent>

      <div className="flex justify-end border-t bg-surface p-4">
        <button
          type="button"
          className="inline-flex h-8 items-center justify-center rounded-lg border border-border bg-background px-3 text-sm font-medium text-foreground transition-colors hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50"
          onClick={onCancel}
        >
          Cancelar
        </button>
      </div>
    </>
  )
}

function RosterStepIndicator({
  activeStep,
}: {
  activeStep: number
}) {
  const steps = [
    'Archivo',
    'Previsualización',
    'Confirmación',
  ]

  return (
    <nav aria-label="Progreso de la carga de nómina" className="mt-4">
      <ol className="grid grid-cols-3 gap-2">
        {steps.map((label, index) => {
          const stepNumber = index + 1
          const isActive = stepNumber === activeStep
          const isComplete = stepNumber < activeStep

          return (
            <li
              key={label}
              className="flex min-w-0 items-center gap-2"
            >
              <span
                aria-current={isActive ? 'step' : undefined}
                className={`flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold ${
                  isComplete
                    ? 'bg-ok-soft text-ok-fg'
                    : isActive
                      ? 'bg-brand text-primary-foreground'
                      : 'bg-sunken text-muted-foreground'
                }`}
              >
                {isComplete ? (
                  <CheckCircle2
                    aria-hidden="true"
                    className="size-4"
                  />
                ) : (
                  stepNumber
                )}
              </span>

              <span
                className={`sciem-supporting truncate ${
                  isActive
                    ? 'font-medium text-foreground'
                    : 'text-muted-foreground'
                }`}
              >
                {label}
              </span>
            </li>
          )
        })}
      </ol>
    </nav>
  )
}

function getActiveStep(
  step:
    | 'file'
    | 'processing'
    | 'preview'
    | 'confirm'
    | 'confirming'
    | 'result',
): number {
  switch (step) {
    case 'file':
      return 1

    case 'processing':
    case 'preview':
      return 2

    case 'confirm':
    case 'confirming':
    case 'result':
      return 3
  }
}