import { Link } from 'react-router-dom'

export default function AuthLayout({ eyebrow, title, description, children, footer }) {
  return (
    <main className="grid min-h-screen bg-white lg:grid-cols-[0.9fr_1.1fr]">
      <section className="relative hidden overflow-hidden bg-slate-950 p-12 text-white lg:flex lg:flex-col lg:justify-between">
        <div className="absolute -right-24 -top-24 h-80 w-80 rounded-full bg-brand-600/30 blur-3xl" />
        <Link to="/" className="relative flex items-center gap-3 text-lg font-bold"><span className="grid h-10 w-10 place-items-center rounded-xl bg-brand-600">T</span>Teknoplek CBT</Link>
        <div className="relative max-w-lg"><p className="mb-5 text-sm font-semibold uppercase tracking-[0.25em] text-red-300">Ujian digital yang tepercaya</p><h2 className="text-5xl font-bold leading-tight">Satu platform untuk ujian seluruh sekolah.</h2><p className="mt-6 text-lg leading-8 text-slate-300">Aman, terukur, dan dirancang untuk ribuan peserta secara bersamaan.</p></div>
        <p className="relative text-sm text-slate-500">© 2026 Teknoplek CBT</p>
      </section>
      <section className="flex items-center justify-center bg-gradient-to-br from-white to-slate-50 px-5 py-10 sm:px-10"><div className="w-full max-w-xl"><p className="text-sm font-bold uppercase tracking-[0.2em] text-brand-600">{eyebrow}</p><h1 className="mt-3 text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl">{title}</h1><p className="mt-3 text-slate-500">{description}</p><div className="mt-8">{children}</div><p className="mt-7 text-center text-sm text-slate-500">{footer}</p></div></section>
    </main>
  )
}
