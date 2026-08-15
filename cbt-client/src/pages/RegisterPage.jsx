import { useState } from 'react'
import toast from 'react-hot-toast'
import { Link, useNavigate } from 'react-router-dom'
import api, { getErrorMessage } from '../api/client'
import AuthLayout from '../components/AuthLayout'
import { useAuth } from '../hooks/useAuth'

const initialForm = { npsn: '', school_type: '', address: '', email: '', username: '', password: '', password_confirmation: '' }

export default function RegisterPage() {
  const [form, setForm] = useState(initialForm)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const { saveSession } = useAuth()
  const navigate = useNavigate()
  const update = (field) => (event) => setForm({ ...form, [field]: event.target.value })

  async function handleSubmit(event) {
    event.preventDefault()
    if (form.password !== form.password_confirmation) { toast.error('Konfirmasi password tidak sama.'); return }
    setIsSubmitting(true)
    try {
      const response = await api.post('/schools/register', form)
      saveSession(response.data.data)
      toast.success(response.data.message)
      navigate('/dashboard', { replace: true })
    } catch (error) {
      toast.error(getErrorMessage(error, 'Pendaftaran gagal.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <AuthLayout eyebrow="Registrasi sekolah" title="Buat ruang kerja sekolah" description="Akun pertama otomatis mendapatkan akses Kurikulum." footer={<>Sudah punya akun? <Link className="font-bold text-brand-600" to="/login">Masuk</Link></>}>
      <form className="grid gap-5 sm:grid-cols-2" onSubmit={handleSubmit}>
        <label className="block"><span className="label">NPSN</span><input className="field" inputMode="numeric" required maxLength="20" value={form.npsn} onChange={update('npsn')} /></label>
        <label className="block"><span className="label">Jenis sekolah</span><select className="field" required value={form.school_type} onChange={update('school_type')}><option value="">Pilih jenis</option><option value="sd_mi">SD/MI/Sederajat</option><option value="smp_mts">SMP/MTs/Sederajat</option><option value="sma_smk">SMA/SMK/Sederajat</option></select></label>
        <label className="block sm:col-span-2"><span className="label">Alamat</span><textarea className="field min-h-24 resize-y" required maxLength="2000" value={form.address} onChange={update('address')} /></label>
        <label className="block sm:col-span-2"><span className="label">Email sekolah</span><input className="field" type="email" autoComplete="email" required value={form.email} onChange={update('email')} /></label>
        <label className="block sm:col-span-2"><span className="label">Username Kurikulum</span><input className="field" autoComplete="username" required minLength="4" value={form.username} onChange={update('username')} /></label>
        <label className="block"><span className="label">Password</span><input className="field" type="password" autoComplete="new-password" required minLength="8" value={form.password} onChange={update('password')} /></label>
        <label className="block"><span className="label">Konfirmasi password</span><input className="field" type="password" autoComplete="new-password" required minLength="8" value={form.password_confirmation} onChange={update('password_confirmation')} /></label>
        <button className="primary-button sm:col-span-2" disabled={isSubmitting}>{isSubmitting ? 'Mendaftarkan...' : 'Daftarkan sekolah'}</button>
      </form>
    </AuthLayout>
  )
}
