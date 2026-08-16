import { useState } from 'react'
import toast from 'react-hot-toast'
import { Link, useNavigate } from 'react-router-dom'
import api, { getErrorMessage } from '../api/client'
import AuthLayout from '../components/AuthLayout'
import { useAuth } from '../hooks/useAuth'

export default function LoginPage() {
  const [form, setForm] = useState({ username: '', password: '' })
  const [isSubmitting, setIsSubmitting] = useState(false)
  const { saveSession } = useAuth()
  const navigate = useNavigate()

  async function handleSubmit(event) {
    event.preventDefault()
    setIsSubmitting(true)
    try {
      const response = await api.post('/auth/login', form)
      saveSession(response.data.data)
      toast.success(response.data.message)
      const destinations = { pengawas: '/observer/monitoring', super_admin: '/super-admin' }
      navigate(destinations[response.data.data.user.role] ?? '/dashboard', { replace: true })
    } catch (error) {
      toast.error(getErrorMessage(error, 'Login gagal.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <AuthLayout eyebrow="Selamat datang" title="Masuk ke akun Anda" description="Gunakan kredensial sesuai peran yang telah diberikan." footer={<><Link className="font-bold text-brand-600" to="/student-login">Masuk sebagai siswa</Link><span className="mx-2">·</span>Belum terdaftar? <Link className="font-bold text-brand-600" to="/register">Daftarkan sekolah</Link></>}>
      <form className="space-y-5" onSubmit={handleSubmit}>
        <label className="block"><span className="label">Username</span><input className="field" autoComplete="username" required value={form.username} onChange={(event) => setForm({ ...form, username: event.target.value })} /></label>
        <label className="block"><span className="label">Password</span><input className="field" type="password" autoComplete="current-password" required value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} /></label>
        <button className="primary-button w-full" disabled={isSubmitting}>{isSubmitting ? 'Memproses...' : 'Masuk'}</button>
      </form>
    </AuthLayout>
  )
}
