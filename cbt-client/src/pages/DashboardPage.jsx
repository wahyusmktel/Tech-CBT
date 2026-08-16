import AppShell from '../components/AppShell'
import { useAuth } from '../hooks/useAuth'

const roleLabels = { super_admin: 'Super Admin', kurikulum: 'Kurikulum', pengawas: 'Pengawas', siswa: 'Siswa' }

export default function DashboardPage() {
  const { user } = useAuth()

  return (
    <AppShell>
      <section className="mx-auto max-w-7xl px-6 py-10">
        <div className="rounded-3xl bg-slate-950 p-8 text-white shadow-xl sm:p-10"><span className="rounded-full bg-brand-600/20 px-3 py-1 text-xs font-bold uppercase tracking-wider text-red-300">{roleLabels[user.role]}</span><h1 className="mt-5 text-3xl font-bold">Halo, {user.name}</h1><p className="mt-2 text-slate-400">{user.school?.name ?? `Sekolah NPSN ${user.school?.npsn ?? '-'}`}</p></div>
        <div className="mt-7 grid gap-5 md:grid-cols-3">{['Data siswa', 'Bank soal', 'Jadwal ujian'].map((label) => <article key={label} className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><p className="text-sm font-semibold text-slate-500">{label}</p><p className="mt-4 text-3xl font-bold text-slate-900">0</p><p className="mt-2 text-sm text-slate-400">Modul siap dibangun pada tahap berikutnya.</p></article>)}</div>
      </section>
    </AppShell>
  )
}
