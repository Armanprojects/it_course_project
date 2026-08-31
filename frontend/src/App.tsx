import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { AttributeLibraryPage } from './pages/AttributeLibraryPage'
import { CvPage } from './pages/CvPage'
import { CvSearchPage } from './pages/CvSearchPage'
import { HomePage } from './pages/HomePage'
import { LoginPage } from './pages/LoginPage'
import { OAuthCallbackPage } from './pages/OAuthCallbackPage'
import { PositionDetailPage } from './pages/PositionDetailPage'
import { PositionEditPage } from './pages/PositionEditPage'
import { PositionsPage } from './pages/PositionsPage'
import { ProfilePage } from './pages/ProfilePage'
import { VerifyEmailPage } from './pages/VerifyEmailPage'

function App() {
  return (
    <BrowserRouter>
      <Routes>
        {/* Каталог открыт анонимно: по заданию гость просматривает позиции
            в режиме чтения, а вход нужен только для резюме и профиля. */}
        <Route path="/" element={<HomePage />} />
        <Route path="/positions" element={<PositionsPage />} />
        {/* Раньше конкретного /positions/:id — иначе "new" уйдёт в просмотр. */}
        <Route path="/positions/new" element={<PositionEditPage />} />
        <Route path="/positions/:id/edit" element={<PositionEditPage />} />
        <Route path="/positions/:id" element={<PositionDetailPage />} />

        {/* Всё ниже закрыто входом; сами страницы уводят на /login без токена. */}
        <Route path="/profile" element={<ProfilePage />} />
        <Route path="/cvs/search" element={<CvSearchPage />} />
        <Route path="/cvs/:id" element={<CvPage />} />
        <Route path="/attributes" element={<AttributeLibraryPage />} />

        <Route path="/login" element={<LoginPage />} />
        <Route path="/auth/callback" element={<OAuthCallbackPage />} />
        <Route path="/auth/verify" element={<VerifyEmailPage />} />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </BrowserRouter>
  )
}

export default App
