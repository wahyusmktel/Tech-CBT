import { useEffect, useState } from 'react'
import toast from 'react-hot-toast'
import Swal from 'sweetalert2'
import api, { getErrorMessage } from '../api/client'
import AppShell from '../components/AppShell'
import { Skeleton } from '../components/Skeleton'

export default function RoomsPage() {
  const [rooms, setRooms] = useState([])
  const [classrooms, setClassrooms] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [roomForm, setRoomForm] = useState(null)
  const [mapping, setMapping] = useState(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  async function loadRooms() {
    try {
      const response = await api.get('/rooms')
      setRooms(response.data.data)
    } catch (error) {
      toast.error(getErrorMessage(error, 'Data ruang gagal dimuat.'))
    }
  }

  useEffect(() => {
    async function loadPage() {
      setIsLoading(true)
      try {
        const [roomResponse, classroomResponse] = await Promise.all([api.get('/rooms'), api.get('/classrooms')])
        setRooms(roomResponse.data.data)
        setClassrooms(classroomResponse.data.data)
      } catch (error) {
        toast.error(getErrorMessage(error, 'Data ruang gagal dimuat.'))
      } finally {
        setIsLoading(false)
      }
    }
    loadPage()
  }, [])

  async function saveRoom(event) {
    event.preventDefault()
    setIsSubmitting(true)
    try {
      const response = roomForm.id ? await api.put(`/rooms/${roomForm.id}`, { name: roomForm.name }) : await api.post('/rooms', { name: roomForm.name })
      toast.success(response.data.message)
      setRoomForm(null)
      await loadRooms()
      if (response.data.data.observer_credentials) {
        const credentials = response.data.data.observer_credentials
        await Swal.fire({ title: 'Kredensial Pengawas', text: `Username: ${credentials.username}\nPassword: ${credentials.password}\n\nSimpan kredensial ini dengan aman.`, icon: 'success', confirmButtonColor: '#dc2626' })
      }
    } catch (error) {
      toast.error(getErrorMessage(error, 'Ruang gagal disimpan.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  async function deleteRoom(room) {
    const result = await Swal.fire({ title: 'Hapus ruang?', text: `${room.name}, mapping siswa, dan akun Pengawas akan dihapus.`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal' })
    if (!result.isConfirmed) return
    try {
      const response = await api.delete(`/rooms/${room.id}`)
      toast.success(response.data.message)
      await loadRooms()
    } catch (error) {
      toast.error(getErrorMessage(error, 'Ruang gagal dihapus.'))
    }
  }

  async function openMapping(room) {
    try {
      const response = await api.get(`/rooms/${room.id}/mapping`)
      setMapping({ room, classroom_ids: response.data.data.classroom_ids })
    } catch (error) {
      toast.error(getErrorMessage(error, 'Mapping ruang gagal dimuat.'))
    }
  }

  function toggleClassroom(id) {
    setMapping((current) => ({ ...current, classroom_ids: current.classroom_ids.includes(id) ? current.classroom_ids.filter((item) => item !== id) : [...current.classroom_ids, id] }))
  }

  async function saveMapping() {
    setIsSubmitting(true)
    try {
      const response = await api.put(`/rooms/${mapping.room.id}/mapping`, { classroom_ids: mapping.classroom_ids, student_ids: [] })
      toast.success(response.data.message)
      setMapping(null)
      await loadRooms()
    } catch (error) {
      toast.error(getErrorMessage(error, 'Mapping ruang gagal disimpan.'))
    } finally {
      setIsSubmitting(false)
    }
  }

  async function showCredentials(room) {
    try {
      const response = await api.get(`/rooms/${room.id}/observer-credentials`)
      const credentials = response.data.data
      const result = await Swal.fire({ title: `Pengawas ${room.name}`, text: `Username: ${credentials.username}\nPassword: ${credentials.password}`, icon: 'info', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Tutup', cancelButtonText: 'Ganti password' })
      if (result.dismiss === Swal.DismissReason.cancel) await rotateCredentials(room)
    } catch (error) {
      toast.error(getErrorMessage(error, 'Kredensial Pengawas gagal dimuat.'))
    }
  }

  async function rotateCredentials(room) {
    const confirmation = await Swal.fire({ title: 'Ganti password Pengawas?', text: 'Password lama tidak akan dapat digunakan lagi.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Ya, ganti', cancelButtonText: 'Batal' })
    if (!confirmation.isConfirmed) return
    try {
      const response = await api.post(`/rooms/${room.id}/observer-credentials/rotate`)
      toast.success(response.data.message)
      await Swal.fire({ title: 'Password baru', text: `Username: ${response.data.data.username}\nPassword: ${response.data.data.password}`, icon: 'success', confirmButtonColor: '#dc2626' })
    } catch (error) {
      toast.error(getErrorMessage(error, 'Password Pengawas gagal diganti.'))
    }
  }

  return (
    <AppShell>
      <section className="mx-auto max-w-7xl px-6 py-10">
        <div className="flex flex-wrap items-end justify-between gap-5"><div><p className="text-sm font-bold uppercase tracking-[0.18em] text-brand-600">Administrasi ujian</p><h1 className="mt-2 text-3xl font-bold text-slate-950">Ruang Ujian</h1><p className="mt-2 text-slate-500">Atur ruang, peserta, dan akun Pengawas.</p></div><button onClick={() => setRoomForm({ name: '' })} className="primary-button">Tambah ruang</button></div>
        {isLoading ? <div className="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">{[1, 2, 3, 4, 5, 6].map((item) => <Skeleton key={item} className="h-56" />)}</div> : rooms.length === 0 ? <div className="mt-8 rounded-2xl border border-slate-200 bg-white p-16 text-center text-slate-500">Belum ada ruang ujian.</div> : <div className="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">{rooms.map((room) => <article key={room.id} className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><div className="flex items-start justify-between gap-4"><div><p className="text-xs font-bold uppercase tracking-wider text-brand-600">Ruang ujian</p><h2 className="mt-2 text-xl font-bold text-slate-950">{room.name}</h2></div><span className="rounded-xl bg-slate-100 px-3 py-2 text-sm font-bold text-slate-700">{room.students_count} siswa</span></div><div className="mt-5 rounded-xl bg-slate-50 p-4"><p className="text-xs font-semibold uppercase tracking-wider text-slate-400">Akun Pengawas</p><p className="mt-1 font-mono text-sm font-bold text-slate-700">{room.observer?.username}</p></div><div className="mt-5 grid grid-cols-2 gap-2"><button onClick={() => openMapping(room)} className="rounded-xl bg-slate-900 px-3 py-2.5 text-sm font-bold text-white">Mapping siswa</button><button onClick={() => showCredentials(room)} className="rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700">Kredensial</button><button onClick={() => setRoomForm({ id: room.id, name: room.name })} className="rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700">Edit</button><button onClick={() => deleteRoom(room)} className="rounded-xl border border-red-100 px-3 py-2.5 text-sm font-bold text-brand-600">Hapus</button></div></article>)}</div>}
      </section>

      {roomForm && <Modal title={roomForm.id ? 'Edit ruang' : 'Tambah ruang'} onClose={() => setRoomForm(null)}><form onSubmit={saveRoom} className="space-y-5"><label className="block"><span className="label">Nama ruang</span><input className="field" required maxLength="100" placeholder="Contoh: Ruang 1" value={roomForm.name} onChange={(event) => setRoomForm({ ...roomForm, name: event.target.value })} /></label><button className="primary-button w-full" disabled={isSubmitting}>{isSubmitting ? 'Menyimpan...' : 'Simpan ruang'}</button></form></Modal>}
      {mapping && <Modal title={`Mapping ${mapping.room.name}`} onClose={() => setMapping(null)}><p className="mb-5 text-sm leading-6 text-slate-500">Pilih kelas untuk memasukkan seluruh siswanya. Siswa yang sebelumnya berada di ruang lain akan dipindahkan.</p><div className="max-h-80 space-y-2 overflow-y-auto">{classrooms.map((item) => <label key={item.id} className="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 p-4 hover:bg-slate-50"><span><b className="text-slate-800">{item.name}</b><small className="ml-2 text-slate-500">{item.students_count} siswa</small></span><input type="checkbox" className="h-5 w-5 accent-red-600" checked={mapping.classroom_ids.includes(item.id)} onChange={() => toggleClassroom(item.id)} /></label>)}</div><button onClick={saveMapping} className="primary-button mt-6 w-full" disabled={isSubmitting}>{isSubmitting ? 'Menyimpan...' : 'Simpan mapping'}</button></Modal>}
    </AppShell>
  )
}

function Modal({ title, children, onClose }) {
  return <div className="fixed inset-0 z-50 grid place-items-center bg-slate-950/50 p-4" role="dialog" aria-modal="true"><div className="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl"><div className="mb-6 flex items-center justify-between"><h2 className="text-xl font-bold text-slate-950">{title}</h2><button onClick={onClose} className="rounded-lg px-3 py-2 text-slate-500 hover:bg-slate-100" aria-label="Tutup">✕</button></div>{children}</div></div>
}
