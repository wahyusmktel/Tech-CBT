import { Navigate, Route, Routes } from 'react-router-dom'
import { useAuth } from './hooks/useAuth'
import DashboardPage from './pages/DashboardPage'
import LandingPage from './pages/LandingPage'
import LoginPage from './pages/LoginPage'
import RegisterPage from './pages/RegisterPage'
import SchoolSettingsPage from './pages/SchoolSettingsPage'
import StudentsPage from './pages/StudentsPage'
import RoomsPage from './pages/RoomsPage'
import ExamsPage from './pages/ExamsPage'
import QuestionBanksPage from './pages/QuestionBanksPage'
import StudentLoginPage from './pages/StudentLoginPage'
import StudentExamPage from './pages/StudentExamPage'
import ObserverMonitoringPage from './pages/ObserverMonitoringPage'
import { FullPageSkeleton } from './components/Skeleton'

function ProtectedRoute({ children, roles }) {
  const { isLoading, user } = useAuth()
  if (isLoading) return <FullPageSkeleton />
  if (!user) return <Navigate to="/login" replace />
  if (roles && !roles.includes(user.role)) return <Navigate to="/dashboard" replace />
  return children
}

function GuestRoute({ children }) {
  const { isLoading, user } = useAuth()
  if (isLoading) return <FullPageSkeleton />
  return user ? <Navigate to="/dashboard" replace /> : children
}

export default function App() {
  return (
    <Routes>
      <Route path="/" element={<LandingPage />} />
      <Route path="/login" element={<GuestRoute><LoginPage /></GuestRoute>} />
      <Route path="/student-login" element={<GuestRoute><StudentLoginPage /></GuestRoute>} />
      <Route path="/register" element={<GuestRoute><RegisterPage /></GuestRoute>} />
      <Route path="/dashboard" element={<ProtectedRoute><DashboardPage /></ProtectedRoute>} />
      <Route path="/settings/school" element={<ProtectedRoute roles={['kurikulum']}><SchoolSettingsPage /></ProtectedRoute>} />
      <Route path="/students" element={<ProtectedRoute roles={['kurikulum']}><StudentsPage /></ProtectedRoute>} />
      <Route path="/rooms" element={<ProtectedRoute roles={['kurikulum']}><RoomsPage /></ProtectedRoute>} />
      <Route path="/exams" element={<ProtectedRoute roles={['kurikulum']}><ExamsPage /></ProtectedRoute>} />
      <Route path="/question-banks" element={<ProtectedRoute roles={['kurikulum']}><QuestionBanksPage /></ProtectedRoute>} />
      <Route path="/student/exam" element={<ProtectedRoute roles={['siswa']}><StudentExamPage /></ProtectedRoute>} />
      <Route path="/observer/monitoring" element={<ProtectedRoute roles={['pengawas']}><ObserverMonitoringPage /></ProtectedRoute>} />
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
