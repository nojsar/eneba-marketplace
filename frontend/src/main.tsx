import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router'
import '@fontsource/metropolis/400.css'
import '@fontsource/metropolis/600.css'
import '@fontsource/metropolis/700.css'
import '@fontsource/metropolis/800.css'
import './styles/tokens.css'
import './styles/global.css'
import { App } from './App'
import { AuthProvider } from './state/AuthProvider'

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <BrowserRouter>
      <AuthProvider>
        <App />
      </AuthProvider>
    </BrowserRouter>
  </StrictMode>,
)
