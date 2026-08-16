import toast from 'react-hot-toast'
import { NavLink, useNavigate } from 'react-router-dom'
import api, { getErrorMessage } from '../api/client'
import { useAuth } from '../hooks/useAuth'

const navClass = ({ isActive }) => `rounded-xl px-4 py-2.5 text-sm font-semibold transition ${isActive ? 'bg-red-50 text-brand-700' : 'text-slate-600 hover:bg-slate-100'}`

export default function AppShell({ children }) {
  const { user, clearSession } = useAuth()
  const navigate = useNavigate()

  async function logout() {
    try {
      const response = await api.post('/auth/logout')
      toast.success(response.data.message)
    } catch (error) {
      toast.error(getErrorMessage(error, 'Gagal mengakhiri sesi.'))
    } finally {
      clearSession()
      navigate('/login', { replace: true })
    }
  }

  return (
    <main className="min-h-screen bg-slate-100">
      <header className="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-6 py-4">
          <div className="flex items-center gap-3 font-bold text-slate-950"><span className="grid h-10 w-10 place-items-center rounded-xl bg-brand-600 text-white">T</span>Teknoplek CBT</div>
          <nav className="order-3 flex w-full items-center gap-1 overflow-x-auto sm:order-2 sm:w-auto"><NavLink to="/dashboard" className={navClass}>Dashboard</NavLink>{user.role === 'kurikulum' && <><NavLink to="/students" className={navClass}>Siswa</NavLink><NavLink to="/rooms" className={navClass}>Ruang</NavLink><NavLink to="/exams" className={navClass}>Ujian</NavLink><NavLink to="/question-banks" className={navClass}>Bank Soal</NavLink><NavLink to="/settings/school" className={navClass}>Pengaturan Sekolah</NavLink></>}</nav>
          <button onClick={logout} className="order-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 sm:order-3">Keluar</button>
        </div>
      </header>
      {children}
    </main>
  )
}
