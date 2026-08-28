import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'

// Bootstrap подключаем до собственных стилей, чтобы их можно было
// переопределять. JS-бандл нужен для интерактивных компонентов
// (dropdown, modal) — понадобится начиная с этапа 4.
import 'bootstrap/dist/css/bootstrap.min.css'
import 'bootstrap/dist/js/bootstrap.bundle.min.js'

import './index.css'
import App from './App.tsx'

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
