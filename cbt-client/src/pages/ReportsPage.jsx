import { useEffect, useState } from 'react'
import toast from 'react-hot-toast'
import Swal from 'sweetalert2'
import api, { getErrorMessage } from '../api/client'
import AppShell from '../components/AppShell'
import { Skeleton, TableSkeleton } from '../components/Skeleton'

const statusLabels = { finished: 'Selesai', in_progress: 'Mengerjakan', not_started: 'Belum mulai' }

export default function ReportsPage() {
  const [exams, setExams] = useState([])
  const [selectedExam, setSelectedExam] = useState('')
  const [report, setReport] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isReportLoading, setIsReportLoading] = useState(false)
  const [downloading, setDownloading] = useState('')

  useEffect(() => {
    async function loadPage() {
      try {
        const response = await api.get('/exams')
        const availableExams = response.data.data
        setExams(availableExams)
        if (availableExams.length > 0) {
          setSelectedExam(availableExams[0].id)
          const reportResponse = await api.get(`/reports/exams/${availableExams[0].id}`)
          setReport(reportResponse.data.data)
        }
      } catch (error) {
        toast.error(getErrorMessage(error, 'Daftar laporan gagal dimuat.'))
      } finally {
        setIsLoading(false)
      }
    }

    loadPage()
  }, [])

  async function loadReport(examId) {
    setIsReportLoading(true)
    try {
      const response = await api.get(`/reports/exams/${examId}`)
      setReport(response.data.data)
    } catch (error) {
      setReport(null)
      toast.error(getErrorMessage(error, 'Laporan ujian gagal dimuat.'))
    } finally {
      setIsReportLoading(false)
    }
  }

  async function changeExam(event) {
    const examId = event.target.value
    setSelectedExam(examId)
    if (examId) await loadReport(examId)
    else setReport(null)
  }

  async function downloadReport(type, label) {
    const confirmation = await Swal.fire({
      title: `Unduh ${label}?`,
      text: 'Dokumen akan dibuat berdasarkan data ujian terbaru.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#dc2626',
      confirmButtonText: 'Ya, unduh',
      cancelButtonText: 'Batal',
    })
    if (!confirmation.isConfirmed) return

    setDownloading(type)
    try {
      const response = await api.get(`/reports/exams/${selectedExam}/${type}`, { responseType: 'blob', timeout: 60000 })
      const disposition = response.headers['content-disposition'] ?? ''
      const encodedName = disposition.match(/filename\*=UTF-8''([^;]+)/i)?.[1]
      const regularName = disposition.match(/filename="?([^";]+)"?/i)?.[1]
      const filename = encodedName ? decodeURIComponent(encodedName) : regularName ?? `${label}.${type.endsWith('xlsx') ? 'xlsx' : 'pdf'}`
      const url = URL.createObjectURL(response.data)
      const link = document.createElement('a')
      link.href = url
      link.download = filename
      document.body.appendChild(link)
      link.click()
      link.remove()
      URL.revokeObjectURL(url)
      toast.success(`${label} berhasil diunduh.`)
    } catch (error) {
      toast.error(getErrorMessage(error, `${label} gagal diunduh.`))
    } finally {
      setDownloading('')
    }
  }

  return (
    <AppShell>
      <section className="mx-auto max-w-7xl px-6 py-10">
        <div className="flex flex-wrap items-end justify-between gap-5">
          <div><p className="text-sm font-bold uppercase tracking-[0.18em] text-brand-600">Reporting</p><h1 className="mt-2 text-3xl font-bold text-slate-950">Hasil & Analisis Ujian</h1><p className="mt-2 text-slate-500">Pantau hasil peserta dan unduh dokumen dengan kop surat sekolah.</p></div>
          {isLoading ? <Skeleton className="h-12 w-72" /> : <label className="w-full sm:w-80"><span className="label">Pilih ujian</span><select className="field" value={selectedExam} onChange={changeExam}><option value="">Pilih ujian</option>{exams.map((exam) => <option key={exam.id} value={exam.id}>{exam.name} - {exam.subject.name}</option>)}</select></label>}
        </div>

        {isLoading || isReportLoading ? <ReportSkeleton /> : !report ? <div className="mt-8 rounded-2xl border border-slate-200 bg-white p-16 text-center text-slate-500">Belum ada laporan ujian yang dapat ditampilkan.</div> : <>
          <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <SummaryCard label="Peserta" value={report.summary.participant_count} />
            <SummaryCard label="Selesai" value={report.summary.finished_count} />
            <SummaryCard label="Rata-rata" value={formatScore(report.summary.average_score)} />
            <SummaryCard label="Tertinggi" value={formatScore(report.summary.highest_score)} />
            <SummaryCard label="Terendah" value={formatScore(report.summary.lowest_score)} />
          </div>

          <div className="mt-6 flex flex-wrap gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <button disabled={Boolean(downloading)} onClick={() => downloadReport('report.xlsx', 'Laporan Excel')} className="primary-button">{downloading === 'report.xlsx' ? 'Membuat Excel...' : 'Unduh Excel'}</button>
            <button disabled={Boolean(downloading)} onClick={() => downloadReport('results.pdf', 'Rekap Nilai PDF')} className="rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white disabled:opacity-60">{downloading === 'results.pdf' ? 'Membuat PDF...' : 'Rekap Nilai PDF'}</button>
            <button disabled={Boolean(downloading)} onClick={() => downloadReport('analysis.pdf', 'Analisis Soal PDF')} className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-bold text-slate-700 disabled:opacity-60">{downloading === 'analysis.pdf' ? 'Membuat PDF...' : 'Analisis Soal PDF'}</button>
          </div>

          <ReportTable title="Rekap Nilai Peserta"><thead><tr><Th>No</Th><Th>NISN</Th><Th>Nama</Th><Th>Kelas</Th><Th>Status</Th><Th>Nilai</Th></tr></thead><tbody>{report.results.length === 0 ? <EmptyRow columns={6} /> : report.results.map((result, index) => <tr key={result.student_id} className="border-t border-slate-100"><Td>{index + 1}</Td><Td>{result.nisn}</Td><Td className="font-semibold text-slate-900">{result.name}</Td><Td>{result.classroom}</Td><Td><span className={`rounded-lg px-2.5 py-1 text-xs font-bold ${result.status === 'finished' ? 'bg-emerald-50 text-emerald-700' : result.status === 'in_progress' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600'}`}>{statusLabels[result.status]}</span></Td><Td className="font-bold">{formatScore(result.score)}</Td></tr>)}</tbody></ReportTable>

          <ReportTable title="Analisis Butir Soal"><thead><tr><Th>No</Th><Th>Pertanyaan</Th><Th>Kunci</Th><Th>Benar</Th><Th>Salah</Th><Th>Kosong</Th><Th>% Benar</Th></tr></thead><tbody>{report.question_analysis.length === 0 ? <EmptyRow columns={7} /> : report.question_analysis.map((item) => <tr key={item.question_id} className="border-t border-slate-100"><Td>{item.number}</Td><Td className="max-w-xl whitespace-normal text-slate-900">{item.text}</Td><Td>{item.correct_answer}</Td><Td>{item.correct_count}</Td><Td>{item.wrong_count}</Td><Td>{item.unanswered_count}</Td><Td className="font-bold">{item.correct_percentage.toLocaleString('id-ID')}%</Td></tr>)}</tbody></ReportTable>
        </>}
      </section>
    </AppShell>
  )
}

function SummaryCard({ label, value }) { return <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p className="text-sm font-semibold text-slate-500">{label}</p><p className="mt-2 text-3xl font-bold text-slate-950">{value}</p></article> }
function ReportTable({ title, children }) { return <section className="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div className="border-b border-slate-100 px-6 py-4"><h2 className="font-bold text-slate-950">{title}</h2></div><div className="overflow-x-auto"><table className="w-full min-w-[760px] text-left text-sm">{children}</table></div></section> }
function Th({ children }) { return <th className="bg-slate-50 px-4 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">{children}</th> }
function Td({ children, className = '' }) { return <td className={`whitespace-nowrap px-4 py-3 text-slate-600 ${className}`}>{children}</td> }
function EmptyRow({ columns }) { return <tr><td colSpan={columns} className="px-6 py-12 text-center text-slate-500">Belum ada data.</td></tr> }
function formatScore(value) { return value === null || value === undefined ? '-' : Number(value).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }
function ReportSkeleton() { return <div className="mt-8 space-y-6"><div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">{[1, 2, 3, 4, 5].map((item) => <Skeleton key={item} className="h-28" />)}</div><Skeleton className="h-20" /><div className="rounded-2xl border bg-white"><TableSkeleton rows={6} /></div></div> }
