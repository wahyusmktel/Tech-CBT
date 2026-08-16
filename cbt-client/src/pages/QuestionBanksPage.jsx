import { useEffect, useState } from 'react'
import toast from 'react-hot-toast'
import Swal from 'sweetalert2'
import api, { getErrorMessage } from '../api/client'
import AppShell from '../components/AppShell'
import { Skeleton } from '../components/Skeleton'

export default function QuestionBanksPage() {
  const [banks, setBanks] = useState([])
  const [subjects, setSubjects] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [bankForm, setBankForm] = useState(null)
  const [preview, setPreview] = useState(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  async function refresh() {
    try {
      const [bankResponse, subjectResponse] = await Promise.all([api.get('/question-banks'), api.get('/subjects')])
      setBanks(bankResponse.data.data); setSubjects(subjectResponse.data.data)
    } catch (error) { toast.error(getErrorMessage(error, 'Bank soal gagal dimuat.')) }
  }

  useEffect(() => { async function load() { await refresh(); setIsLoading(false) } load() }, [])

  async function saveBank(event) {
    event.preventDefault(); setIsSubmitting(true)
    try {
      const response = bankForm.id ? await api.put(`/question-banks/${bankForm.id}`, bankForm) : await api.post('/question-banks', bankForm)
      toast.success(response.data.message); setBankForm(null); await refresh()
    } catch (error) { toast.error(getErrorMessage(error, 'Bank soal gagal disimpan.')) } finally { setIsSubmitting(false) }
  }

  async function deleteBank(bank) {
    const result = await Swal.fire({ title: 'Hapus bank soal?', text: `${bank.title} dan seluruh soalnya akan dihapus.`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal' })
    if (!result.isConfirmed) return
    try { const response = await api.delete(`/question-banks/${bank.id}`); toast.success(response.data.message); await refresh() } catch (error) { toast.error(getErrorMessage(error, 'Bank soal gagal dihapus.')) }
  }

  async function importDocx(bank, file, force = false) {
    try {
      const payload = new FormData(); payload.append('file', file); payload.append('force', force ? '1' : '0')
      const response = await api.post(`/question-banks/${bank.id}/import`, payload)
      toast.success(response.data.message); await refresh()
    } catch (error) {
      if (error.response?.status === 409 && error.response?.data?.requires_confirmation) {
        const confirmation = await Swal.fire({ title: 'Import ulang soal?', text: 'Soal lama akan diganti seluruhnya dan status validasi akan dibatalkan.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Ya, import ulang', cancelButtonText: 'Batal' })
        if (confirmation.isConfirmed) await importDocx(bank, file, true)
      } else toast.error(getErrorMessage(error, 'Dokumen soal gagal diimport.'))
    }
  }

  function chooseDocument(bank, event) { const file = event.target.files?.[0]; event.target.value = ''; if (file) importDocx(bank, file) }

  async function openPreview(bank) {
    try { const response = await api.get(`/question-banks/${bank.id}`); setPreview(response.data.data) } catch (error) { toast.error(getErrorMessage(error, 'Preview soal gagal dimuat.')) }
  }

  async function validateBank(bank) {
    const result = await Swal.fire({ title: 'Validasi soal?', text: 'Pastikan seluruh pertanyaan, pilihan, dan kunci jawaban sudah benar.', icon: 'question', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Ya, validasi', cancelButtonText: 'Batal' })
    if (!result.isConfirmed) return
    try { const response = await api.post(`/question-banks/${bank.id}/validate`); toast.success(response.data.message); await refresh() } catch (error) { toast.error(getErrorMessage(error, 'Bank soal gagal divalidasi.')) }
  }

  return (
    <AppShell>
      <section className="mx-auto max-w-7xl px-6 py-10">
        <div className="flex flex-wrap items-end justify-between gap-5"><div><p className="text-sm font-bold uppercase tracking-[0.18em] text-brand-600">Konten ujian</p><h1 className="mt-2 text-3xl font-bold text-slate-950">Bank Soal</h1><p className="mt-2 text-slate-500">Import soal DOCX, periksa hasil parsing, lalu validasi.</p></div><button onClick={() => setBankForm({ title: '', subject_id: '' })} className="primary-button">Tambah bank soal</button></div>
        <div className="mt-6 rounded-2xl border border-blue-100 bg-blue-50 p-5 text-sm leading-7 text-blue-900"><b>Format DOCX:</b> setiap bagian dibuat pada paragraf terpisah: <code>1. Teks Soal</code>, <code>A. Pilihan A</code>, <code>B. Pilihan B</code>, lalu <code>ANS : A</code>.</div>
        {isLoading ? <div className="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">{[1, 2, 3, 4, 5, 6].map((item) => <Skeleton key={item} className="h-64" />)}</div> : banks.length === 0 ? <div className="mt-8 rounded-2xl border bg-white p-16 text-center text-slate-500">Belum ada bank soal.</div> : <div className="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">{banks.map((bank) => <article key={bank.id} className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><div className="flex justify-between gap-4"><span className="rounded-lg bg-red-50 px-3 py-1 text-xs font-bold text-brand-700">{bank.subject.code}</span><b className="text-2xl text-slate-900">{bank.questions_count}</b></div><h2 className="mt-4 text-xl font-bold text-slate-950">{bank.title}</h2><p className="mt-1 text-sm text-slate-500">{bank.subject.name}</p>{bank.validated_at ? <div className="mt-4 rounded-xl bg-emerald-50 p-3 text-xs leading-5 text-emerald-800">Soal sudah divalidasi oleh <b>{bank.validated_by}</b> pada {new Date(bank.validated_at).toLocaleString('id-ID')}.</div> : <div className="mt-4 rounded-xl bg-amber-50 p-3 text-xs font-semibold text-amber-800">Belum divalidasi</div>}<div className="mt-5 grid grid-cols-2 gap-2"><label className="cursor-pointer rounded-xl bg-slate-900 px-3 py-2.5 text-center text-sm font-bold text-white">Import DOCX<input type="file" accept=".docx" className="sr-only" onChange={(event) => chooseDocument(bank, event)} /></label><button onClick={() => openPreview(bank)} className="rounded-xl border px-3 py-2.5 text-sm font-bold">Preview</button><button onClick={() => validateBank(bank)} className="rounded-xl border px-3 py-2.5 text-sm font-bold text-emerald-700">Validasi</button><button onClick={() => setBankForm({ id: bank.id, title: bank.title, subject_id: bank.subject.id })} className="rounded-xl border px-3 py-2.5 text-sm font-bold">Edit</button><button onClick={() => deleteBank(bank)} className="col-span-2 rounded-xl border border-red-100 px-3 py-2.5 text-sm font-bold text-brand-600">Hapus</button></div></article>)}</div>}
      </section>

      {bankForm && <Modal title={bankForm.id ? 'Edit bank soal' : 'Tambah bank soal'} onClose={() => setBankForm(null)}><form onSubmit={saveBank} className="space-y-4"><label><span className="label">Judul paket soal</span><input className="field" required value={bankForm.title} onChange={(e) => setBankForm({ ...bankForm, title: e.target.value })} /></label><label><span className="label">Mata pelajaran</span><select className="field" required value={bankForm.subject_id} onChange={(e) => setBankForm({ ...bankForm, subject_id: e.target.value })}><option value="">Pilih mata pelajaran</option>{subjects.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label><button className="primary-button w-full" disabled={isSubmitting}>Simpan bank soal</button></form></Modal>}
      {preview && <Modal title={preview.title} onClose={() => setPreview(null)} wide><div className="max-h-[70vh] space-y-5 overflow-y-auto pr-2">{preview.questions.length === 0 ? <p className="py-10 text-center text-slate-500">Belum ada soal yang diimport.</p> : preview.questions.map((question) => <article key={question.id} className="rounded-2xl border border-slate-200 p-5"><p className="font-semibold leading-7 text-slate-900"><b className="mr-2">{question.number}.</b>{question.text}</p><div className="mt-4 space-y-2">{question.choices.map((choice) => <div key={choice.id} className={`flex gap-3 rounded-xl p-3 text-sm ${choice.is_correct ? 'bg-emerald-50 text-emerald-900' : 'bg-slate-50 text-slate-700'}`}><b>{choice.label}.</b><span>{choice.text}</span>{choice.is_correct && <span className="ml-auto text-xs font-bold">Kunci</span>}</div>)}</div></article>)}</div></Modal>}
    </AppShell>
  )
}

function Modal({ title, children, onClose, wide = false }) { return <div className="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-slate-950/50 p-4" role="dialog" aria-modal="true"><div className={`my-8 w-full rounded-2xl bg-white p-6 shadow-2xl ${wide ? 'max-w-4xl' : 'max-w-lg'}`}><div className="mb-6 flex justify-between"><h2 className="text-xl font-bold">{title}</h2><button onClick={onClose} aria-label="Tutup">✕</button></div>{children}</div></div> }
