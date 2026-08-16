import { useEffect, useState } from 'react'
import toast from 'react-hot-toast'
import Swal from 'sweetalert2'
import api, { getErrorMessage } from '../api/client'
import AppShell from '../components/AppShell'
import { Skeleton, TableSkeleton } from '../components/Skeleton'

const schoolTypeLabels = { sd_mi: 'SD/MI', smp_mts: 'SMP/MTs', sma_smk: 'SMA/SMK' }
const examStatusLabels = { draft: 'Draft', scheduled: 'Terjadwal', active: 'Berlangsung', completed: 'Selesai' }

export default function SuperAdminPage() {
  const [summary, setSummary] = useState(null)
  const [schools, setSchools] = useState([])
  const [meta, setMeta] = useState(null)
  const [search, setSearch] = useState('')
  const [isLoading, setIsLoading] = useState(true)
  const [detail, setDetail] = useState(null)
  const [isDetailLoading, setIsDetailLoading] = useState(false)
  const [resettingSchool, setResettingSchool] = useState('')

  useEffect(() => {
    async function loadInitial() {
      setIsLoading(true)
      try {
        const response = await api.get('/super-admin/schools', { params: { page: 1, per_page: 10 } })
        setSummary(response.data.data.summary)
        setSchools(response.data.data.schools)
        setMeta(response.data.meta)
      } catch (error) {
        toast.error(getErrorMessage(error, 'Dashboard Super Admin gagal dimuat.'))
      } finally {
        setIsLoading(false)
      }
    }

    loadInitial()
  }, [])

  async function loadSchools(page = 1, keyword = search) {
    setIsLoading(true)
    try {
      const response = await api.get('/super-admin/schools', { params: { page, per_page: 10, search: keyword || undefined } })
      setSummary(response.data.data.summary)
      setSchools(response.data.data.schools)
      setMeta(response.data.meta)
    } catch (error) {
      toast.error(getErrorMessage(error, 'Dashboard Super Admin gagal dimuat.'))
    } finally {
      setIsLoading(false)
    }
  }

  async function submitSearch(event) {
    event.preventDefault()
    await loadSchools(1, search)
  }

  async function openSchool(school) {
    setDetail({ school })
    setIsDetailLoading(true)
    try {
      const response = await api.get(`/super-admin/schools/${school.id}`)
      setDetail(response.data.data)
    } catch (error) {
      setDetail(null)
      toast.error(getErrorMessage(error, 'Detail sekolah gagal dimuat.'))
    } finally {
      setIsDetailLoading(false)
    }
  }

  async function resetPassword(school) {
    const confirmation = await Swal.fire({ title: 'Reset password Kurikulum?', text: `Semua sesi Kurikulum ${school.name} akan dikeluarkan.`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Ya, reset password', cancelButtonText: 'Batal' })
    if (!confirmation.isConfirmed) return

    setResettingSchool(school.id)
    try {
      const response = await api.post(`/super-admin/schools/${school.id}/reset-curriculum-password`)
      const credential = response.data.data
      toast.success(response.data.message)
      await Swal.fire({ title: 'Kredensial sementara', text: `Username: ${credential.username}\nPassword: ${credential.temporary_password}\n\nSalin sekarang dan berikan melalui kanal yang aman.`, icon: 'success', confirmButtonColor: '#dc2626', confirmButtonText: 'Sudah disalin' })
    } catch (error) {
      toast.error(getErrorMessage(error, 'Password Kurikulum gagal direset.'))
    } finally {
      setResettingSchool('')
    }
  }

  return (
    <AppShell>
      <section className="mx-auto max-w-7xl px-6 py-10">
        <div className="flex flex-wrap items-end justify-between gap-5"><div><p className="text-sm font-bold uppercase tracking-[0.18em] text-brand-600">Platform control</p><h1 className="mt-2 text-3xl font-bold text-slate-950">Dashboard Super Admin</h1><p className="mt-2 text-slate-500">Monitoring sekolah, siswa, mata pelajaran, ujian, dan hasil lintas tenant.</p></div><form onSubmit={submitSearch} className="flex w-full gap-2 sm:w-auto"><input className="field min-w-72" placeholder="Cari sekolah, NPSN, atau email" value={search} onChange={(event) => setSearch(event.target.value)} /><button className="primary-button">Cari</button></form></div>

        {isLoading && !summary ? <DashboardSkeleton /> : <>
          <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
            <Stat label="Sekolah" value={summary?.schools_count ?? 0} />
            <Stat label="Siswa" value={summary?.students_count ?? 0} />
            <Stat label="Mata Pelajaran" value={summary?.subjects_count ?? 0} />
            <Stat label="Ujian" value={summary?.exams_count ?? 0} />
            <Stat label="Ujian Selesai" value={summary?.finished_attempts_count ?? 0} />
            <Stat label="Rata-rata Nilai" value={formatScore(summary?.average_score)} />
          </div>

          <div className="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-100 px-6 py-4"><h2 className="font-bold text-slate-950">Sekolah Terdaftar</h2></div>
            {isLoading ? <TableSkeleton rows={6} /> : schools.length === 0 ? <div className="p-14 text-center text-slate-500">Sekolah tidak ditemukan.</div> : <div className="overflow-x-auto"><table className="w-full min-w-[960px] text-left text-sm"><thead><tr>{['Sekolah', 'Kurikulum', 'Siswa', 'Mapel', 'Ujian', 'Rata-rata', 'Aksi'].map((item) => <th key={item} className="bg-slate-50 px-4 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">{item}</th>)}</tr></thead><tbody>{schools.map((school) => <tr key={school.id} className="border-t border-slate-100"><td className="px-4 py-4"><button onClick={() => openSchool(school)} className="text-left"><span className="block font-bold text-slate-950 hover:text-brand-600">{school.name}</span><span className="mt-1 block text-xs text-slate-500">NPSN {school.npsn} · {schoolTypeLabels[school.type] ?? school.type}</span></button></td><td className="px-4 py-4"><span className="block font-semibold text-slate-700">{school.curriculum?.username ?? '-'}</span><span className="text-xs text-slate-500">{school.curriculum?.email ?? 'Akun tidak ditemukan'}</span></td><td className="px-4 py-4 font-semibold">{school.students_count}</td><td className="px-4 py-4 font-semibold">{school.subjects_count}</td><td className="px-4 py-4 font-semibold">{school.exams_count}</td><td className="px-4 py-4 font-bold">{formatScore(school.average_score)}</td><td className="px-4 py-4"><div className="flex gap-2"><button onClick={() => openSchool(school)} className="rounded-lg border px-3 py-2 text-xs font-bold text-slate-700">Detail</button><button disabled={!school.curriculum || Boolean(resettingSchool)} onClick={() => resetPassword(school)} className="rounded-lg bg-red-50 px-3 py-2 text-xs font-bold text-brand-700 disabled:opacity-50">{resettingSchool === school.id ? 'Mereset...' : 'Reset Password'}</button></div></td></tr>)}</tbody></table></div>}
            {meta && meta.last_page > 1 && <div className="flex items-center justify-between border-t p-4"><button disabled={meta.current_page <= 1 || isLoading} onClick={() => loadSchools(meta.current_page - 1)} className="rounded-lg border px-4 py-2 text-sm font-bold disabled:opacity-40">Sebelumnya</button><span className="text-sm text-slate-500">Halaman {meta.current_page} dari {meta.last_page}</span><button disabled={meta.current_page >= meta.last_page || isLoading} onClick={() => loadSchools(meta.current_page + 1)} className="rounded-lg border px-4 py-2 text-sm font-bold disabled:opacity-40">Berikutnya</button></div>}
          </div>
        </>}
      </section>

      {detail && <SchoolDetail detail={detail} loading={isDetailLoading} onClose={() => setDetail(null)} onReset={resetPassword} resetting={resettingSchool} />}
    </AppShell>
  )
}

function SchoolDetail({ detail, loading, onClose, onReset, resetting }) {
  const school = detail.school
  return <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-950/60 p-4" role="dialog" aria-modal="true"><div className="mx-auto my-8 w-full max-w-5xl rounded-3xl bg-white p-6 shadow-2xl"><div className="flex items-start justify-between gap-4"><div><p className="text-xs font-bold uppercase tracking-wider text-brand-600">Detail tenant</p><h2 className="mt-1 text-2xl font-bold text-slate-950">{school.name}</h2><p className="mt-1 text-sm text-slate-500">NPSN {school.npsn} · {school.email}</p></div><button onClick={onClose} className="rounded-lg border px-3 py-2" aria-label="Tutup">✕</button></div>{loading ? <div className="mt-6 space-y-5"><Skeleton className="h-28" /><TableSkeleton rows={4} /></div> : <><div className="mt-6 grid gap-3 sm:grid-cols-4"><Stat label="Siswa" value={school.students_count} /><Stat label="Mapel" value={school.subjects_count} /><Stat label="Ujian" value={school.exams_count} /><Stat label="Percobaan" value={school.attempts_count} /></div><div className="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-slate-50 p-4"><div><p className="text-sm font-bold text-slate-900">Akun Kurikulum: {school.curriculum?.username ?? '-'}</p><p className="text-xs text-slate-500">{school.curriculum?.email ?? 'Belum ada akun Kurikulum'}</p></div><button disabled={!school.curriculum || Boolean(resetting)} onClick={() => onReset(school)} className="rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50">Reset Password</button></div><DetailTable title="Mata Pelajaran"><thead><tr><Head>Kode</Head><Head>Nama</Head><Head>Ujian</Head><Head>Peserta Selesai</Head><Head>Rata-rata</Head></tr></thead><tbody>{detail.subjects.length === 0 ? <Empty columns={5} /> : detail.subjects.map((subject) => <tr key={subject.id} className="border-t"><Cell>{subject.code}</Cell><Cell>{subject.name}</Cell><Cell>{subject.exams_count}</Cell><Cell>{subject.finished_attempts_count}</Cell><Cell>{formatScore(subject.average_score)}</Cell></tr>)}</tbody></DetailTable><DetailTable title="Ujian Terbaru"><thead><tr><Head>Ujian</Head><Head>Mapel</Head><Head>Status</Head><Head>Selesai</Head><Head>Rata-rata</Head></tr></thead><tbody>{detail.recent_exams.length === 0 ? <Empty columns={5} /> : detail.recent_exams.map((exam) => <tr key={exam.id} className="border-t"><Cell>{exam.name}</Cell><Cell>{exam.subject}</Cell><Cell>{examStatusLabels[exam.status] ?? exam.status}</Cell><Cell>{exam.finished_count}/{exam.attempts_count}</Cell><Cell>{formatScore(exam.average_score)}</Cell></tr>)}</tbody></DetailTable><DetailTable title="Nilai Terbaru"><thead><tr><Head>Siswa</Head><Head>Kelas</Head><Head>Ujian</Head><Head>Mapel</Head><Head>Nilai</Head></tr></thead><tbody>{detail.recent_scores.length === 0 ? <Empty columns={5} /> : detail.recent_scores.map((score) => <tr key={score.id} className="border-t"><Cell>{score.student}<span className="block text-xs text-slate-400">{score.nisn}</span></Cell><Cell>{score.classroom}</Cell><Cell>{score.exam}</Cell><Cell>{score.subject}</Cell><Cell><b>{formatScore(score.score)}</b></Cell></tr>)}</tbody></DetailTable></>}</div></div>
}

function DashboardSkeleton() { return <div className="mt-8 space-y-6"><div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">{[1, 2, 3, 4, 5, 6].map((item) => <Skeleton key={item} className="h-28" />)}</div><div className="rounded-2xl border bg-white"><TableSkeleton rows={7} /></div></div> }
function Stat({ label, value }) { return <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p className="text-xs font-bold uppercase tracking-wide text-slate-500">{label}</p><p className="mt-2 text-2xl font-bold text-slate-950">{value}</p></article> }
function DetailTable({ title, children }) { return <section className="mt-5 overflow-hidden rounded-2xl border"><div className="bg-slate-50 px-4 py-3 font-bold">{title}</div><div className="overflow-x-auto"><table className="w-full min-w-[640px] text-left text-sm">{children}</table></div></section> }
function Head({ children }) { return <th className="px-4 py-3 text-xs font-bold uppercase text-slate-500">{children}</th> }
function Cell({ children }) { return <td className="px-4 py-3 text-slate-600">{children}</td> }
function Empty({ columns }) { return <tr><td colSpan={columns} className="px-4 py-8 text-center text-slate-500">Belum ada data.</td></tr> }
function formatScore(value) { return value === null || value === undefined ? '-' : Number(value).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }
