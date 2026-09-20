import { BrowserRouter } from 'react-router-dom'
import { TooltipProvider } from '@/components/ui/tooltip'
import { CurrentUserProvider } from '@/features/auth'
import { AppRouter } from './router/AppRouter'

function App() {
  return (
    <BrowserRouter>
      <CurrentUserProvider>
        <TooltipProvider>
          <AppRouter />
        </TooltipProvider>
      </CurrentUserProvider>
    </BrowserRouter>
  )
}

export default App
