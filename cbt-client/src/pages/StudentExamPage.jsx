import { useEffect, useState } from 'react'
import toast from 'react-hot-toast'
import Swal from 'sweetalert2'
import { useNavigate } from 'react-router-dom'
import api, { getErrorMessage } from '../api/client'
import { Skeleton } from '../components/Skeleton'
import { useAuth } from '../hooks/useAuth'

export default function StudentExamPage() {
  const [data, setData] = useState(null)
  const [answers, setAnswers] = useState({})
  const [current, setCurrent] = useState(0)
  const [seconds, setSeconds] = useState(0)
  const [errorMessage, setErrorMessage] = useState('')
  const { user, clearSession } = useAuth(); const navigate = useNavigate()

  useEffect(() => {
    async function startExam() {
      try {
        const response = await api.post('/student/exam/start')
        setData(response.data.data); setAnswers(response.data.data.attempt.answers ?? {})
        setSeconds(Math.max(0, Math.floor((new Date(response.data.data.attempt.ends_at).getTime() - Date.now()) / 1000)))
      } catch (error) { const message = getErrorMessage(error, 'Ujian gagal dimuat.'); setErrorMessage(message); toast.error(message) }
    }
    startExam()
  }, [])

  useEffect(() => {
    if (!data) return undefined
    const timer = window.setInterval(() => setSeconds((value) => Math.max(0, value - 1)), 1000)
    const warnReload = (event) => { event.preventDefault(); event.returnValue = '' }
    window.addEventListener('beforeunload', warnReload)
    return () => { window.clearInterval(timer); window.removeEventListener('beforeunload', warnReload) }
  }, [data])

  async function choose(questionId, choiceId) {
    setAnswers((value) => ({ ...value, [questionId]: choiceId }))
    try { const response = await api.put(`/student/exam/answers/${questionId}`, { question_choice_id: choiceId }); toast.success(response.data.message, { duration: 1000 }) }
    catch (error) { toast.error(getErrorMessage(error, 'Jawaban gagal disimpan.')) }
  }

  async function finish() {
    const unanswered = data.questions.length - Object.keys(answers).length
    const confirmation = await Swal.fire({ title: 'Selesaikan ujian?', text: unanswered > 0 ? `Masih ada ${unanswered} soal belum dijawab.` : 'Jawaban tidak dapat diubah setelah ujian diselesaikan.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Ya, selesai', cancelButtonText: 'Periksa lagi' })
    if (!confirmation.isConfirmed) return
    try {
      const response = await api.post('/student/exam/submit'); clearSession()
      await Swal.fire({ title: 'Ujian selesai', text: `Nilai Anda: ${response.data.data.score}`, icon: 'success', confirmButtonColor: '#dc2626' })
      navigate('/student-login', { replace: true })
    } catch (error) { toast.error(getErrorMessage(error, 'Ujian gagal diselesaikan.')) }
  }

  if (errorMessage) return <main className="grid min-h-screen place-items-center bg-slate-100 p-6"><div className="max-w-lg rounded-2xl bg-white p-10 text-center shadow-sm"><h1 className="text-2xl font-bold">Ujian belum dapat dimulai</h1><p className="mt-3 text-slate-500">{errorMessage}</p><button onClick={() => window.location.reload()} className="primary-button mt-6">Coba lagi</button></div></main>
  if (!data) return <main className="min-h-screen bg-slate-100"><div className="mx-auto max-w-6xl space-y-6 px-6 py-10"><Skeleton className="h-24" /><Skeleton className="h-96" /></div></main>
  const question = data.questions[current]
  const minutes = String(Math.floor(seconds / 60)).padStart(2, '0'); const remainingSeconds = String(seconds % 60).padStart(2, '0')

  return <main className="min-h-screen bg-slate-100"><header className="sticky top-0 z-20 border-b bg-white"><div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-6 py-4"><div><p className="text-xs font-bold uppercase text-brand-600">{data.exam.subject}</p><h1 className="font-bold text-slate-950">{data.exam.name}</h1></div><div className={`rounded-xl px-5 py-3 font-mono text-xl font-bold ${seconds < 300 ? 'bg-red-50 text-brand-700' : 'bg-slate-900 text-white'}`}>{minutes}:{remainingSeconds}</div></div></header><div className="mx-auto grid max-w-7xl gap-6 px-6 py-8 lg:grid-cols-[240px_1fr]"><aside className="h-fit rounded-2xl bg-white p-5 shadow-sm"><p className="font-bold text-slate-900">{user.name}</p><p className="mt-1 text-xs text-slate-500">Tersimpan {Object.keys(answers).length}/{data.questions.length}</p><div className="mt-5 grid grid-cols-5 gap-2">{data.questions.map((item, index) => <button key={item.id} onClick={() => setCurrent(index)} className={`aspect-square rounded-lg text-sm font-bold ${index === current ? 'bg-brand-600 text-white' : answers[item.id] ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'}`}>{item.number}</button>)}</div><button onClick={finish} className="primary-button mt-6 w-full">Selesaikan</button></aside><section className="rounded-2xl bg-white p-6 shadow-sm sm:p-8"><p className="text-sm font-bold text-brand-600">Soal {question.number}</p><p className="mt-4 text-lg font-semibold leading-8 text-slate-900">{question.text}</p><div className="mt-7 space-y-3">{question.choices.map((choice) => <button key={choice.id} onClick={() => choose(question.id, choice.id)} className={`flex w-full gap-4 rounded-xl border p-4 text-left transition ${answers[question.id] === choice.id ? 'border-brand-500 bg-red-50 ring-2 ring-red-100' : 'border-slate-200 hover:bg-slate-50'}`}><b className="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-white text-slate-700 shadow-sm">{choice.label}</b><span className="pt-1 text-slate-700">{choice.text}</span></button>)}</div><div className="mt-8 flex justify-between"><button disabled={current === 0} onClick={() => setCurrent((value) => value - 1)} className="rounded-xl border px-5 py-3 font-bold disabled:opacity-30">Sebelumnya</button><button disabled={current === data.questions.length - 1} onClick={() => setCurrent((value) => value + 1)} className="rounded-xl bg-slate-900 px-5 py-3 font-bold text-white disabled:opacity-30">Berikutnya</button></div></section></div></main>
}
