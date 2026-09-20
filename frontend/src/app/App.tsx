import { SubjectProvider } from '../features/academic/context/SubjectContext';
import SubjectSelector from '../features/academic/components/SubjectSelector';

function App() {
  return (
    <SubjectProvider>
      <main className="min-h-screen p-4">
        <SubjectSelector />
      </main>
    </SubjectProvider>
  )
}

export default App