import { useEffect, useState } from 'react'
import toast from 'react-hot-toast'
import api, { getErrorMessage } from '../api/client'
import AppShell from '../components/AppShell'
import { TableSkeleton } from '../components/Skeleton'

const labels = { not_logged_in: 'Belum login', logged_in: 'Sudah login', in_progress: 'Mengerjakan', finished: 'Selesai' }
const colors = { not_logged_in: 'bg-slate-100 text-slate-600', logged_in: 'bg-blue-100 text-blue-700', in_progress: 'bg-amber-100 text-amber-800', finished: 'bg-emerald-100 text-emerald-800' }

export default function ObserverMonitoringPage() {
  const [exams, setExams] = useState([]); const [isLoading, setIsLoading] = useState(true); const [updatedAt, setUpdatedAt] = useState(null)
  useEffect(() => {
    let active = true
    async function load(silent = false) {
      try { const response = await api.get('/observer/monitoring'); if (active) { setExams(response.data.data); setUpdatedAt(new Date()) } }
      catch (error) { if (!silent) toast.error(getErrorMessage(error, 'Monitoring gagal dimuat.')) }
      finally { if (active) setIsLoading(false) }
    }
    load(); const interval = window.setInterval(() => load(true), 5000)
    return () => { active = false; window.clearInterval(interval) }
  }, [])

  return <AppShell><section className="mx-auto max-w-7xl px-6 py-10"><div className="flex flex-wrap justify-between gap-4"><div><p className="text-sm font-bold uppercase tracking-[0.18em] text-brand-600">Real-time monitoring</p><h1 className="mt-2 text-3xl font-bold text-slate-950">Monitoring Pengawas</h1><p className="mt-2 text-slate-500">Data diperbarui otomatis setiap 5 detik.</p></div>{updatedAt && <span className="h-fit rounded-xl bg-white px-4 py-2 text-xs text-slate-500 shadow-sm">Diperbarui {updatedAt.toLocaleTimeString('id-ID')}</span>}</div>{isLoading ? <div className="mt-8 rounded-2xl bg-white"><TableSkeleton rows={8} /></div> : exams.length === 0 ? <div className="mt-8 rounded-2xl bg-white p-16 text-center text-slate-500">Tidak ada ujian aktif untuk ruang ini.</div> : <div className="mt-8 space-y-7">{exams.map((exam) => <article key={exam.id} className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div className="flex flex-wrap justify-between gap-4 border-b p-5"><div><span className="rounded-lg bg-red-50 px-3 py-1 text-xs font-bold text-brand-700">{exam.status}</span><h2 className="mt-3 text-xl font-bold">{exam.name}</h2><p className="mt-1 text-sm text-slate-500">{exam.subject} · {new Date(exam.start_at).toLocaleString('id-ID')}</p></div><div className="text-right"><b className="text-2xl">{exam.participants.filter((item) => item.status === 'finished').length}/{exam.participants.length}</b><p className="text-xs text-slate-500">selesai</p></div></div><div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead className="bg-slate-50 text-xs uppercase text-slate-500"><tr><th className="px-5 py-3">Peserta</th><th className="px-5 py-3">Kelas</th><th className="px-5 py-3">Status</th><th className="px-5 py-3">Progress</th><th className="px-5 py-3">Aktivitas</th></tr></thead><tbody className="divide-y">{exam.participants.map((student) => <tr key={student.id}><td className="px-5 py-4"><b>{student.name}</b><p className="font-mono text-xs text-slate-500">{student.nisn}</p></td><td className="px-5 py-4">{student.classroom}</td><td className="px-5 py-4"><span className={`rounded-lg px-3 py-1.5 text-xs font-bold ${colors[student.status]}`}>{labels[student.status]}</span></td><td className="min-w-40 px-5 py-4"><div className="mb-1 flex justify-between text-xs"><span>{student.answered}/{student.total}</span><span>{student.total ? Math.round(student.answered / student.total * 100) : 0}%</span></div><div className="h-2 rounded-full bg-slate-100"><div className="h-2 rounded-full bg-brand-600" style={{ width: `${student.total ? student.answered / student.total * 100 : 0}%` }} /></div></td><td className="px-5 py-4 text-xs text-slate-500">{student.last_activity_at ? new Date(student.last_activity_at).toLocaleTimeString('id-ID') : '-'}</td></tr>)}</tbody></table></div></article>)}</div>}</section></AppShell>
}
