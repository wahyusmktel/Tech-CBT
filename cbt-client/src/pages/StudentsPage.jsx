import { useEffect, useState } from 'react'
import toast from 'react-hot-toast'
import Swal from 'sweetalert2'
import api, { getErrorMessage } from '../api/client'
import AppShell from '../components/AppShell'
import { TableSkeleton } from '../components/Skeleton'

const emptyStudent = { nisn: '', name: '', classroom_id: '' }

export default function StudentsPage() {
  const [students, setStudents] = useState([])
  const [classrooms, setClassrooms] = useState([])
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 })
  const [filters, setFilters] = useState({ search: '', classroom_id: '', page: 1 })
  const [searchInput, setSearchInput] = useState('')
  const [isLoading, setIsLoading] = useState(true)
  const [studentForm, setStudentForm] = useState(null)
  const [classroomForm, setClassroomForm] = useState(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  async function loadClassrooms() {
    try {
      const response = await api.get('/classrooms')
      setClassrooms(response.data.data)
    } catch (error) {
      toast.error(getErrorMessage(error, 'Data kelas gagal dimuat.'))
    }
  }

  useEffect(() => {
    async function loadStudents() {
      setIsLoading(true)
      try {
        const response = await api.get('/students', { params: { ...filters, search: filters.search || undefined, classroom_id: filters.classroom_id || undefined } })
        setStudents(response.data.data)
        setMeta(response.data.meta)
      } catch (error) {
        toast.error(getErrorMessage(error, 'Data siswa gagal dimuat.'))
      } finally {
        setIsLoading(false)
      }
    }
    loadStudents()
  }, [filters])

  useEffect(() => {
    async function loadInitialClassrooms() {
      try {
        const response = await api.get('/classrooms')
        setClassrooms(response.data.data)
      } catch (error) {
        toast.error(getErrorMessage(error, 'Data kelas gagal dimuat.'))
      }
    }
    loadInitialClassrooms()
  }, [])

  function applySearch(event) {
    event.preventDefault()
    setFilters((current) => ({ ...current, search: searchInput.trim(), page: 1 }))
  }

  async function saveStudent(event) {
    event.preventDefault()
    setIsSubmitting(true)
    try {
      const request = studentForm.id ? api.put(`/students/${studentForm.id}`, studentForm) : api.post('/students', studentForm)
      const response = await request
      toast.success(response.data.message)
      setStudentForm(null)
      setFilters((current) => ({ ...current }))
      await loadClassrooms()
    } catch (error) {
      toast.error(getErrorMessage(error, 'Data siswa gagal disimpan.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  async function deleteStudent(student) {
    const confirmation = await Swal.fire({ title: 'Hapus siswa?', text: `${student.name} akan dihapus dari data sekolah.`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal' })
    if (!confirmation.isConfirmed) return
    try {
      const response = await api.delete(`/students/${student.id}`)
      toast.success(response.data.message)
      setFilters((current) => ({ ...current }))
      await loadClassrooms()
    } catch (error) {
      toast.error(getErrorMessage(error, 'Siswa gagal dihapus.'))
    }
  }

  async function saveClassroom(event) {
    event.preventDefault()
    setIsSubmitting(true)
    try {
      const request = classroomForm.id ? api.put(`/classrooms/${classroomForm.id}`, classroomForm) : api.post('/classrooms', classroomForm)
      const response = await request
      toast.success(response.data.message)
      setClassroomForm(null)
      await loadClassrooms()
    } catch (error) {
      toast.error(getErrorMessage(error, 'Kelas gagal disimpan.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  async function deleteClassroom(classroom) {
    const confirmation = await Swal.fire({ title: 'Hapus kelas?', text: `Kelas ${classroom.name} akan dihapus.`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal' })
    if (!confirmation.isConfirmed) return
    try {
      const response = await api.delete(`/classrooms/${classroom.id}`)
      toast.success(response.data.message)
      await loadClassrooms()
    } catch (error) {
      toast.error(getErrorMessage(error, 'Kelas gagal dihapus.'))
    }
  }

  async function importStudents(event) {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (!file) return
    const confirmation = await Swal.fire({ title: 'Import data siswa?', text: `File ${file.name} akan diproses. NISN yang sudah ada akan diperbarui.`, icon: 'question', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Mulai import', cancelButtonText: 'Batal' })
    if (!confirmation.isConfirmed) return
    try {
      const payload = new FormData()
      payload.append('file', file)
      const response = await api.post('/students/import', payload)
      const result = response.data.data
      toast.success(response.data.message)
      const details = [`Data baru: ${result.inserted}`, `Diperbarui: ${result.updated}`, `Gagal: ${result.failed}`, ...result.errors].join('\n')
      await Swal.fire({ title: 'Resume Hasil Import', text: details, icon: result.failed ? 'warning' : 'success', confirmButtonColor: '#dc2626' })
      setFilters((current) => ({ ...current, page: 1 }))
      await loadClassrooms()
    } catch (error) {
      toast.error(getErrorMessage(error, 'Import siswa gagal.'))
    }
  }

  async function downloadTemplate() {
    try {
      const response = await api.get('/students/import-template', { responseType: 'blob' })
      const url = URL.createObjectURL(response.data)
      const link = document.createElement('a')
      link.href = url
      link.download = 'template-import-data-siswa.xlsx'
      document.body.appendChild(link)
      link.click()
      link.remove()
      URL.revokeObjectURL(url)
      toast.success('Template import berhasil diunduh.')
    } catch (error) {
      toast.error(getErrorMessage(error, 'Template import gagal diunduh.'))
    }
  }

  return (
    <AppShell>
      <section className="mx-auto max-w-7xl px-6 py-10">
        <div className="flex flex-wrap items-end justify-between gap-5"><div><p className="text-sm font-bold uppercase tracking-[0.18em] text-brand-600">Administrasi</p><h1 className="mt-2 text-3xl font-bold text-slate-950">Data Siswa</h1><p className="mt-2 text-slate-500">Kelola siswa per kelas atau import data secara massal.</p></div><div className="flex flex-wrap gap-3"><button onClick={downloadTemplate} className="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700">Unduh template</button><button onClick={() => setClassroomForm({ name: '', manager: true })} className="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700">Kelola kelas</button><label className="cursor-pointer rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700">Import Excel/CSV<input type="file" className="sr-only" accept=".csv,.txt,.xlsx,.xls" onChange={importStudents} /></label><button onClick={() => setStudentForm({ ...emptyStudent })} className="primary-button">Tambah siswa</button></div></div>

        <div className="mt-8 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
          <div className="flex flex-wrap gap-3 border-b border-slate-200 p-5"><form onSubmit={applySearch} className="flex min-w-64 flex-1 gap-2"><input className="field" placeholder="Cari nama atau NISN..." value={searchInput} onChange={(event) => setSearchInput(event.target.value)} /><button className="rounded-xl bg-slate-900 px-5 text-sm font-bold text-white">Cari</button></form><select className="field max-w-64" value={filters.classroom_id} onChange={(event) => setFilters({ ...filters, classroom_id: event.target.value, page: 1 })}><option value="">Semua kelas</option>{classrooms.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></div>
          {isLoading ? <TableSkeleton /> : students.length === 0 ? <div className="p-16 text-center text-slate-500">Belum ada data siswa.</div> : <div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead className="bg-slate-50 text-xs uppercase tracking-wider text-slate-500"><tr><th className="px-6 py-4">NISN</th><th className="px-6 py-4">Nama</th><th className="px-6 py-4">Kelas</th><th className="px-6 py-4 text-right">Aksi</th></tr></thead><tbody className="divide-y divide-slate-100">{students.map((student) => <tr key={student.id} className="hover:bg-slate-50"><td className="px-6 py-4 font-mono text-slate-600">{student.nisn}</td><td className="px-6 py-4 font-semibold text-slate-900">{student.name}</td><td className="px-6 py-4"><span className="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600">{student.classroom.name}</span></td><td className="px-6 py-4 text-right"><button onClick={() => setStudentForm({ id: student.id, nisn: student.nisn, name: student.name, classroom_id: student.classroom.id })} className="mr-3 font-bold text-slate-600">Edit</button><button onClick={() => deleteStudent(student)} className="font-bold text-brand-600">Hapus</button></td></tr>)}</tbody></table></div>}
          <div className="flex items-center justify-between border-t border-slate-200 px-5 py-4 text-sm text-slate-500"><span>Total {meta.total} siswa</span><div className="flex gap-2"><button disabled={meta.current_page <= 1} onClick={() => setFilters({ ...filters, page: filters.page - 1 })} className="rounded-lg border px-3 py-2 disabled:opacity-40">Sebelumnya</button><span className="px-2 py-2">{meta.current_page}/{meta.last_page}</span><button disabled={meta.current_page >= meta.last_page} onClick={() => setFilters({ ...filters, page: filters.page + 1 })} className="rounded-lg border px-3 py-2 disabled:opacity-40">Berikutnya</button></div></div>
        </div>
      </section>

      {studentForm && <Modal title={studentForm.id ? 'Edit siswa' : 'Tambah siswa'} onClose={() => setStudentForm(null)}><form onSubmit={saveStudent} className="space-y-5"><label className="block"><span className="label">NISN</span><input className="field" required maxLength="30" value={studentForm.nisn} onChange={(event) => setStudentForm({ ...studentForm, nisn: event.target.value })} /></label><label className="block"><span className="label">Nama lengkap</span><input className="field" required maxLength="255" value={studentForm.name} onChange={(event) => setStudentForm({ ...studentForm, name: event.target.value })} /></label><label className="block"><span className="label">Kelas</span><select className="field" required value={studentForm.classroom_id} onChange={(event) => setStudentForm({ ...studentForm, classroom_id: event.target.value })}><option value="">Pilih kelas</option>{classrooms.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label><button className="primary-button w-full" disabled={isSubmitting}>{isSubmitting ? 'Menyimpan...' : 'Simpan siswa'}</button></form></Modal>}

      {classroomForm && <Modal title="Kelola kelas" onClose={() => setClassroomForm(null)}><form onSubmit={saveClassroom} className="flex gap-2"><input className="field" required maxLength="100" placeholder="Contoh: IX A" value={classroomForm.name} onChange={(event) => setClassroomForm({ ...classroomForm, name: event.target.value })} /><button className="primary-button" disabled={isSubmitting}>{classroomForm.id ? 'Simpan' : 'Tambah'}</button></form><div className="mt-6 max-h-72 space-y-2 overflow-y-auto">{classrooms.map((item) => <div key={item.id} className="flex items-center justify-between rounded-xl bg-slate-50 p-3"><div><p className="font-bold text-slate-800">{item.name}</p><p className="text-xs text-slate-500">{item.students_count} siswa</p></div><div><button onClick={() => setClassroomForm({ id: item.id, name: item.name, manager: true })} className="mr-3 text-sm font-bold text-slate-600">Edit</button><button onClick={() => deleteClassroom(item)} className="text-sm font-bold text-brand-600">Hapus</button></div></div>)}</div></Modal>}
    </AppShell>
  )
}

function Modal({ title, children, onClose }) {
  return <div className="fixed inset-0 z-50 grid place-items-center bg-slate-950/50 p-4" role="dialog" aria-modal="true"><div className="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl"><div className="mb-6 flex items-center justify-between"><h2 className="text-xl font-bold text-slate-950">{title}</h2><button onClick={onClose} className="rounded-lg px-3 py-2 text-slate-500 hover:bg-slate-100" aria-label="Tutup">✕</button></div>{children}</div></div>
}
