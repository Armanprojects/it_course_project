import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'

// Собственная дизайн-система в index.css: Bootstrap убран, потому что
// его палитра и плотность конфликтовали с макетом, а из его компонентов
// здесь использовалась только сетка.
import './index.css'
import App from './App.tsx'

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
