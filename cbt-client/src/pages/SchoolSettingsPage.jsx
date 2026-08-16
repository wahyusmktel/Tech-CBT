import { useEffect, useState } from 'react'
import toast from 'react-hot-toast'
import api, { getErrorMessage } from '../api/client'
import AppShell from '../components/AppShell'
import { useAuth } from '../hooks/useAuth'
import { SettingsSkeleton } from '../components/Skeleton'

const emptyProfile = { name: '', npsn: '', type: '', address: '', principal_name: '', phone: '', email: '' }

export default function SchoolSettingsPage() {
  const [profile, setProfile] = useState(emptyProfile)
  const [letterhead, setLetterhead] = useState(null)
  const [preview, setPreview] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const { updateUser } = useAuth()

  useEffect(() => {
    async function loadProfile() {
      try {
        const response = await api.get('/school/profile')
        setProfile(response.data)
        setPreview(response.data.letterhead_url)
      } catch (error) {
        toast.error(getErrorMessage(error, 'Profil sekolah gagal dimuat.'))
      } finally {
        setIsLoading(false)
      }
    }
    loadProfile()
  }, [])

  useEffect(() => () => {
    if (preview?.startsWith('blob:')) URL.revokeObjectURL(preview)
  }, [preview])

  function update(field) {
    return (event) => setProfile((current) => ({ ...current, [field]: event.target.value }))
  }

  function selectLetterhead(event) {
    const file = event.target.files?.[0]
    if (!file) return
    if (!['image/jpeg', 'image/png'].includes(file.type)) { toast.error('Kop Surat harus berupa JPG atau PNG.'); event.target.value = ''; return }
    if (file.size > 4 * 1024 * 1024) { toast.error('Ukuran Kop Surat maksimal 4 MB.'); event.target.value = ''; return }
    setLetterhead(file)
    setPreview(URL.createObjectURL(file))
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setIsSubmitting(true)
    try {
      const payload = new FormData()
      Object.entries(profile).forEach(([key, value]) => {
        if (key in emptyProfile) payload.append(key, value ?? '')
      })
      if (letterhead) payload.append('letterhead', letterhead)
      const response = await api.post('/school/profile', payload)
      const school = response.data.data
      setProfile(school)
      setPreview(school.letterhead_url)
      setLetterhead(null)
      updateUser((user) => ({ ...user, school: { id: school.id, name: school.name, npsn: school.npsn, type: school.type } }))
      toast.success(response.data.message)
    } catch (error) {
      toast.error(getErrorMessage(error, 'Profil sekolah gagal diperbarui.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <AppShell>
      <section className="mx-auto max-w-7xl px-6 py-10">
        <div className="mb-8"><p className="text-sm font-bold uppercase tracking-[0.18em] text-brand-600">Administrasi</p><h1 className="mt-2 text-3xl font-bold text-slate-950">Pengaturan Sekolah</h1><p className="mt-2 text-slate-500">Data ini digunakan pada identitas aplikasi dan dokumen cetak sekolah.</p></div>
        {isLoading ? <SettingsSkeleton /> : (
          <form onSubmit={handleSubmit} className="grid gap-6 lg:grid-cols-[1fr_340px]">
            <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
              <h2 className="text-lg font-bold text-slate-900">Identitas sekolah</h2>
              <div className="mt-6 grid gap-5 sm:grid-cols-2">
                <label className="block sm:col-span-2"><span className="label">Nama sekolah</span><input className="field" required maxLength="255" value={profile.name ?? ''} onChange={update('name')} /></label>
                <label className="block"><span className="label">NPSN</span><input className="field" required maxLength="20" value={profile.npsn ?? ''} onChange={update('npsn')} /></label>
                <label className="block"><span className="label">Jenis sekolah</span><select className="field" required value={profile.type ?? ''} onChange={update('type')}><option value="sd_mi">SD/MI/Sederajat</option><option value="smp_mts">SMP/MTs/Sederajat</option><option value="sma_smk">SMA/SMK/Sederajat</option></select></label>
                <label className="block sm:col-span-2"><span className="label">Alamat</span><textarea className="field min-h-28 resize-y" required maxLength="2000" value={profile.address ?? ''} onChange={update('address')} /></label>
                <label className="block"><span className="label">Nama kepala sekolah</span><input className="field" required maxLength="255" value={profile.principal_name ?? ''} onChange={update('principal_name')} /></label>
                <label className="block"><span className="label">Nomor HP</span><input className="field" required maxLength="20" value={profile.phone ?? ''} onChange={update('phone')} /></label>
                <label className="block sm:col-span-2"><span className="label">Email sekolah</span><input className="field" type="email" required maxLength="255" value={profile.email ?? ''} onChange={update('email')} /></label>
              </div>
            </div>
            <aside className="h-fit rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
              <h2 className="text-lg font-bold text-slate-900">Kop Surat</h2><p className="mt-2 text-sm leading-6 text-slate-500">Gunakan gambar JPG atau PNG, maksimal 4 MB. Gambar ini akan muncul pada laporan dan dokumen PDF.</p>
              <div className="mt-5 grid min-h-40 place-items-center overflow-hidden rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 p-4">{preview ? <img src={preview} alt="Preview Kop Surat sekolah" className="max-h-44 w-full object-contain" /> : <span className="text-center text-sm text-slate-400">Belum ada Kop Surat</span>}</div>
              <label className="mt-4 block cursor-pointer rounded-xl border border-slate-200 px-4 py-3 text-center text-sm font-bold text-slate-700 hover:bg-slate-50">Pilih gambar<input className="sr-only" type="file" accept="image/png,image/jpeg" onChange={selectLetterhead} /></label>
              <button className="primary-button mt-6 w-full" disabled={isSubmitting}>{isSubmitting ? 'Menyimpan...' : 'Simpan pengaturan'}</button>
            </aside>
          </form>
        )}
      </section>
    </AppShell>
  )
}
