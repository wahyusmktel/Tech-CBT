import { Link } from 'react-router-dom'

export default function LandingPage() {
  return (
    <main className="min-h-screen bg-gradient-to-b from-white via-white to-slate-100">
      <nav className="mx-auto flex max-w-7xl items-center justify-between px-6 py-6">
        <div className="flex items-center gap-3 text-lg font-bold text-slate-950"><span className="grid h-10 w-10 place-items-center rounded-xl bg-brand-600 text-white">T</span>Teknoplek CBT</div>
        <div className="flex gap-3"><Link to="/login" className="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100">Masuk</Link><Link to="/register" className="primary-button">Daftarkan Sekolah</Link></div>
      </nav>
      <section className="mx-auto max-w-5xl px-6 pb-24 pt-24 text-center sm:pt-32">
        <div className="mx-auto mb-7 w-fit rounded-full border border-red-100 bg-red-50 px-4 py-2 text-sm font-semibold text-brand-700">Platform CBT multi-sekolah</div>
        <h1 className="text-5xl font-extrabold leading-tight tracking-[-0.04em] text-slate-950 sm:text-7xl">Ujian sekolah yang aman, tenang, dan terukur.</h1>
        <p className="mx-auto mt-7 max-w-2xl text-lg leading-8 text-slate-600">Kelola siswa, ruang, bank soal, pengawas, pelaksanaan ujian, hingga laporan dalam satu sistem.</p>
        <div className="mt-10 flex flex-wrap justify-center gap-4"><Link to="/register" className="primary-button px-7 py-4">Mulai daftarkan sekolah</Link><Link to="/login" className="rounded-xl border border-slate-200 bg-white px-7 py-4 text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50">Masuk ke akun</Link></div>
      </section>
    </main>
  )
}
