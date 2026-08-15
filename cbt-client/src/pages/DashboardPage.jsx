import toast from 'react-hot-toast'
import { useNavigate } from 'react-router-dom'
import api, { getErrorMessage } from '../api/client'
import { useAuth } from '../hooks/useAuth'

const roleLabels = { super_admin: 'Super Admin', kurikulum: 'Kurikulum', pengawas: 'Pengawas', siswa: 'Siswa' }

export default function DashboardPage() {
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
      <header className="border-b border-slate-200 bg-white"><div className="mx-auto flex max-w-7xl items-center justify-between px-6 py-5"><div className="flex items-center gap-3 font-bold text-slate-950"><span className="grid h-10 w-10 place-items-center rounded-xl bg-brand-600 text-white">T</span>Teknoplek CBT</div><button onClick={logout} className="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Keluar</button></div></header>
      <section className="mx-auto max-w-7xl px-6 py-10">
        <div className="rounded-3xl bg-slate-950 p-8 text-white shadow-xl sm:p-10"><span className="rounded-full bg-brand-600/20 px-3 py-1 text-xs font-bold uppercase tracking-wider text-red-300">{roleLabels[user.role]}</span><h1 className="mt-5 text-3xl font-bold">Halo, {user.name}</h1><p className="mt-2 text-slate-400">{user.school?.name ?? `Sekolah NPSN ${user.school?.npsn ?? '-'}`}</p></div>
        <div className="mt-7 grid gap-5 md:grid-cols-3">{['Data siswa', 'Bank soal', 'Jadwal ujian'].map((label) => <article key={label} className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><p className="text-sm font-semibold text-slate-500">{label}</p><p className="mt-4 text-3xl font-bold text-slate-900">0</p><p className="mt-2 text-sm text-slate-400">Modul siap dibangun pada tahap berikutnya.</p></article>)}</div>
      </section>
    </main>
  )
}
