import { SubjectProvider } from '../../../features/academic/context/SubjectContext'
import SubjectSelector from '../../../features/academic/components/SubjectSelector'

// TODO: mover esta página al feature académico y exportarla desde su index.ts.
export default function MateriasPage() {
  return (
    <SubjectProvider>
      <SubjectSelector />
    </SubjectProvider>
  )
}
