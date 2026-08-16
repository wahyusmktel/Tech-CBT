import { useEffect, useState } from 'react'
import toast from 'react-hot-toast'
import Swal from 'sweetalert2'
import api, { getErrorMessage } from '../api/client'
import AppShell from '../components/AppShell'
import { Skeleton } from '../components/Skeleton'

const emptyExam = { name: '', subject_id: '', question_bank_id: '', start_at: '', duration_minutes: 90, status: 'draft', room_ids: [] }
const statusLabels = { draft: 'Draft', scheduled: 'Terjadwal', active: 'Berlangsung', completed: 'Selesai' }

export default function ExamsPage() {
  const [exams, setExams] = useState([])
  const [subjects, setSubjects] = useState([])
  const [rooms, setRooms] = useState([])
  const [questionBanks, setQuestionBanks] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [examForm, setExamForm] = useState(null)
  const [subjectForm, setSubjectForm] = useState(null)
  const [credentialForm, setCredentialForm] = useState(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  async function refresh() {
    try {
      const [examResponse, subjectResponse, roomResponse, bankResponse] = await Promise.all([api.get('/exams'), api.get('/subjects'), api.get('/rooms'), api.get('/question-banks')])
      setExams(examResponse.data.data); setSubjects(subjectResponse.data.data); setRooms(roomResponse.data.data); setQuestionBanks(bankResponse.data.data)
    } catch (error) { toast.error(getErrorMessage(error, 'Data ujian gagal dimuat.')) }
  }

  useEffect(() => {
    async function loadPage() { setIsLoading(true); await refresh(); setIsLoading(false) }
    loadPage()
  }, [])

  async function saveExam(event) {
    event.preventDefault(); setIsSubmitting(true)
    try {
      const response = examForm.id ? await api.put(`/exams/${examForm.id}`, examForm) : await api.post('/exams', examForm)
      toast.success(response.data.message); setExamForm(null); await refresh()
    } catch (error) { toast.error(getErrorMessage(error, 'Ujian gagal disimpan.')) } finally { setIsSubmitting(false) }
  }

  async function deleteExam(exam) {
    const result = await Swal.fire({ title: 'Hapus ujian?', text: `${exam.name} beserta kredensialnya akan dihapus.`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal' })
    if (!result.isConfirmed) return
    try { const response = await api.delete(`/exams/${exam.id}`); toast.success(response.data.message); await refresh() } catch (error) { toast.error(getErrorMessage(error, 'Ujian gagal dihapus.')) }
  }

  async function saveSubject(event) {
    event.preventDefault(); setIsSubmitting(true)
    try {
      const response = subjectForm.id ? await api.put(`/subjects/${subjectForm.id}`, subjectForm) : await api.post('/subjects', subjectForm)
      toast.success(response.data.message); setSubjectForm(null); await refresh()
    } catch (error) { toast.error(getErrorMessage(error, 'Mata pelajaran gagal disimpan.')) } finally { setIsSubmitting(false) }
  }

  async function deleteSubject(subject) {
    const result = await Swal.fire({ title: 'Hapus mata pelajaran?', text: subject.name, icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal' })
    if (!result.isConfirmed) return
    try { const response = await api.delete(`/subjects/${subject.id}`); toast.success(response.data.message); await refresh() } catch (error) { toast.error(getErrorMessage(error, 'Mata pelajaran gagal dihapus.')) }
  }

  async function generateCredentials(force = false) {
    setIsSubmitting(true)
    try {
      const response = await api.post(`/exams/${credentialForm.exam.id}/generate-credentials`, { ...credentialForm, force })
      toast.success(response.data.message); setCredentialForm(null); await refresh()
    } catch (error) {
      if (error.response?.status === 409 && error.response?.data?.requires_confirmation) {
        const confirmation = await Swal.fire({ title: 'Generate ulang kredensial?', text: 'Melakukan tindakan ini akan merubah seluruh username & password ujian ini.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Ya, generate ulang', cancelButtonText: 'Batal' })
        if (confirmation.isConfirmed) { setIsSubmitting(false); await generateCredentials(true); return }
      } else toast.error(getErrorMessage(error, 'Kredensial gagal dibuat.'))
    } finally { setIsSubmitting(false) }
  }

  function editExam(exam) {
    setExamForm({ id: exam.id, name: exam.name, subject_id: exam.subject.id, question_bank_id: exam.question_bank?.id ?? '', start_at: toLocalInput(exam.start_at), duration_minutes: exam.duration_minutes, status: exam.status, room_ids: exam.rooms.map((room) => room.id) })
  }

  return (
    <AppShell>
      <section className="mx-auto max-w-7xl px-6 py-10">
        <div className="flex flex-wrap items-end justify-between gap-5"><div><p className="text-sm font-bold uppercase tracking-[0.18em] text-brand-600">Administrasi ujian</p><h1 className="mt-2 text-3xl font-bold text-slate-950">Manajemen Ujian</h1><p className="mt-2 text-slate-500">Atur mata pelajaran, jadwal, ruang, durasi, dan kredensial peserta.</p></div><div className="flex gap-3"><button onClick={() => setSubjectForm({ name: '', code: '', manager: true })} className="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700">Mata pelajaran</button><button onClick={() => setExamForm({ ...emptyExam })} className="primary-button">Tambah ujian</button></div></div>
        {isLoading ? <div className="mt-8 space-y-4">{[1, 2, 3, 4].map((item) => <Skeleton key={item} className="h-40" />)}</div> : exams.length === 0 ? <div className="mt-8 rounded-2xl border bg-white p-16 text-center text-slate-500">Belum ada jadwal ujian.</div> : <div className="mt-8 space-y-4">{exams.map((exam) => <article key={exam.id} className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><div className="flex flex-wrap items-start justify-between gap-5"><div><div className="flex flex-wrap items-center gap-2"><span className="rounded-lg bg-red-50 px-3 py-1 text-xs font-bold text-brand-700">{exam.subject.code}</span><span className="rounded-lg bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{statusLabels[exam.status]}</span><span className="rounded-lg bg-slate-900 px-3 py-1 font-mono text-xs font-bold text-white">Kode: {exam.access_code}</span></div><h2 className="mt-3 text-xl font-bold text-slate-950">{exam.name}</h2><p className="mt-2 text-sm text-slate-500">{new Date(exam.start_at).toLocaleString('id-ID')} · {exam.duration_minutes} menit</p><p className="mt-2 text-sm text-slate-500">Ruang: {exam.rooms.map((room) => room.name).join(', ')}</p><p className="mt-1 text-sm text-slate-500">Paket: {exam.question_bank?.title ?? 'Belum dipilih'}</p></div><div className="text-right"><p className="text-2xl font-bold text-slate-900">{exam.credentials_count}</p><p className="text-xs text-slate-500">kredensial peserta</p></div></div><div className="mt-5 flex flex-wrap gap-2"><button onClick={() => setCredentialForm({ exam, username_strategy: 'nisn', password_type: 'mixed', password_length: 8 })} className="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white">{exam.credentials_generated_at ? 'Generate ulang' : 'Generate kredensial'}</button><button onClick={() => editExam(exam)} className="rounded-xl border px-4 py-2.5 text-sm font-bold text-slate-700">Edit</button><button onClick={() => deleteExam(exam)} className="rounded-xl border border-red-100 px-4 py-2.5 text-sm font-bold text-brand-600">Hapus</button></div></article>)}</div>}
      </section>

      {examForm && <Modal title={examForm.id ? 'Edit ujian' : 'Tambah ujian'} onClose={() => setExamForm(null)}><form onSubmit={saveExam} className="grid gap-4 sm:grid-cols-2"><label className="sm:col-span-2"><span className="label">Nama ujian</span><input className="field" required value={examForm.name} onChange={(e) => setExamForm({ ...examForm, name: e.target.value })} /></label><label><span className="label">Mata pelajaran</span><select className="field" required value={examForm.subject_id} onChange={(e) => setExamForm({ ...examForm, subject_id: e.target.value })}><option value="">Pilih</option>{subjects.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label><label><span className="label">Paket soal</span><select className="field" value={examForm.question_bank_id} onChange={(e) => setExamForm({ ...examForm, question_bank_id: e.target.value || null })}><option value="">Belum dipilih</option>{questionBanks.filter((item) => item.subject.id === examForm.subject_id).map((item) => <option key={item.id} value={item.id}>{item.title}{item.validated_at ? '' : ' (belum valid)'}</option>)}</select></label><label><span className="label">Status</span><select className="field" value={examForm.status} onChange={(e) => setExamForm({ ...examForm, status: e.target.value })}>{Object.entries(statusLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label><label><span className="label">Mulai</span><input className="field" type="datetime-local" required value={examForm.start_at} onChange={(e) => setExamForm({ ...examForm, start_at: e.target.value })} /></label><label><span className="label">Durasi (menit)</span><input className="field" type="number" min="1" max="600" required value={examForm.duration_minutes} onChange={(e) => setExamForm({ ...examForm, duration_minutes: Number(e.target.value) })} /></label><fieldset className="sm:col-span-2"><legend className="label">Ruang ujian</legend><div className="grid gap-2 sm:grid-cols-2">{rooms.map((room) => <label key={room.id} className="flex items-center gap-3 rounded-xl border p-3"><input type="checkbox" checked={examForm.room_ids.includes(room.id)} onChange={() => setExamForm({ ...examForm, room_ids: examForm.room_ids.includes(room.id) ? examForm.room_ids.filter((id) => id !== room.id) : [...examForm.room_ids, room.id] })} />{room.name}</label>)}</div></fieldset><button className="primary-button sm:col-span-2" disabled={isSubmitting}>Simpan ujian</button></form></Modal>}
      {subjectForm && <Modal title="Mata pelajaran" onClose={() => setSubjectForm(null)}><form onSubmit={saveSubject} className="grid grid-cols-[120px_1fr_auto] gap-2"><input className="field" placeholder="Kode" required value={subjectForm.code} onChange={(e) => setSubjectForm({ ...subjectForm, code: e.target.value })} /><input className="field" placeholder="Nama mata pelajaran" required value={subjectForm.name} onChange={(e) => setSubjectForm({ ...subjectForm, name: e.target.value })} /><button className="primary-button">Simpan</button></form><div className="mt-5 max-h-64 space-y-2 overflow-auto">{subjects.map((item) => <div key={item.id} className="flex justify-between rounded-xl bg-slate-50 p-3"><span><b>{item.code}</b> · {item.name}</span><span><button onClick={() => setSubjectForm({ id: item.id, code: item.code, name: item.name, manager: true })} className="mr-3 text-sm font-bold">Edit</button><button onClick={() => deleteSubject(item)} className="text-sm font-bold text-brand-600">Hapus</button></span></div>)}</div></Modal>}
      {credentialForm && <Modal title="Generate kredensial" onClose={() => setCredentialForm(null)}><div className="space-y-4"><label><span className="label">Format username</span><select className="field" value={credentialForm.username_strategy} onChange={(e) => setCredentialForm({ ...credentialForm, username_strategy: e.target.value })}><option value="nisn">Gunakan NISN</option><option value="random">Random</option></select></label><label><span className="label">Format password</span><select className="field" value={credentialForm.password_type} onChange={(e) => setCredentialForm({ ...credentialForm, password_type: e.target.value })}><option value="numeric">Angka</option><option value="letters">Huruf</option><option value="mixed">Kombinasi</option></select></label><label><span className="label">Panjang password</span><input className="field" type="number" min="6" max="20" value={credentialForm.password_length} onChange={(e) => setCredentialForm({ ...credentialForm, password_length: Number(e.target.value) })} /></label><button onClick={() => generateCredentials(false)} className="primary-button w-full" disabled={isSubmitting}>Generate kredensial</button></div></Modal>}
    </AppShell>
  )
}

function toLocalInput(value) { const date = new Date(value); const offset = date.getTimezoneOffset() * 60000; return new Date(date.getTime() - offset).toISOString().slice(0, 16) }
function Modal({ title, children, onClose }) { return <div className="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-slate-950/50 p-4" role="dialog" aria-modal="true"><div className="my-8 w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl"><div className="mb-6 flex justify-between"><h2 className="text-xl font-bold">{title}</h2><button onClick={onClose} aria-label="Tutup">✕</button></div>{children}</div></div> }
