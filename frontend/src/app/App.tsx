import { BrowserRouter } from 'react-router-dom'
import { TooltipProvider } from '@/components/ui/tooltip'
import { AppRouter } from './router/AppRouter'

function App() {
  return (
    <BrowserRouter>
      <TooltipProvider>
        <AppRouter />
      </TooltipProvider>
    </BrowserRouter>
  )
}

export default App
