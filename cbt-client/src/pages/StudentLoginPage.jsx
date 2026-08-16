import { useState } from 'react'
import toast from 'react-hot-toast'
import { Link, useNavigate } from 'react-router-dom'
import api, { getErrorMessage } from '../api/client'
import AuthLayout from '../components/AuthLayout'
import { useAuth } from '../hooks/useAuth'

export default function StudentLoginPage() {
  const [form, setForm] = useState({ access_code: '', username: '', password: '' })
  const [isSubmitting, setIsSubmitting] = useState(false)
  const { saveSession } = useAuth(); const navigate = useNavigate()
  async function submit(event) {
    event.preventDefault(); setIsSubmitting(true)
    try { const response = await api.post('/student/login', form); saveSession(response.data.data); toast.success(response.data.message); navigate('/student/exam', { replace: true }) }
    catch (error) { toast.error(getErrorMessage(error, 'Login peserta gagal.')) } finally { setIsSubmitting(false) }
  }
  return <AuthLayout eyebrow="Portal peserta" title="Masuk ke ruang ujian" description="Masukkan kode ujian dan kredensial pada kartu ujian Anda." footer={<Link className="font-bold text-brand-600" to="/login">Masuk sebagai admin atau pengawas</Link>}><form onSubmit={submit} className="space-y-5"><label><span className="label">Kode ujian</span><input className="field uppercase" required maxLength="12" value={form.access_code} onChange={(e) => setForm({ ...form, access_code: e.target.value.toUpperCase() })} /></label><label><span className="label">Username</span><input className="field" required value={form.username} onChange={(e) => setForm({ ...form, username: e.target.value })} /></label><label><span className="label">Password</span><input className="field" type="password" required value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} /></label><button className="primary-button w-full" disabled={isSubmitting}>{isSubmitting ? 'Memeriksa...' : 'Masuk ujian'}</button></form></AuthLayout>
}
