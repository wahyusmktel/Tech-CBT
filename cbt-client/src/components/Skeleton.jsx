export function Skeleton({ className = '' }) {
  return <div aria-hidden="true" className={`animate-pulse rounded-xl bg-slate-200 ${className}`} />
}

export function FullPageSkeleton() {
  return (
    <main className="min-h-screen bg-slate-100">
      <div className="border-b bg-white"><div className="mx-auto flex max-w-7xl justify-between px-6 py-4"><Skeleton className="h-10 w-44" /><Skeleton className="h-10 w-28" /></div></div>
      <div className="mx-auto max-w-7xl space-y-7 px-6 py-10"><Skeleton className="h-48 w-full rounded-3xl" /><div className="grid gap-5 md:grid-cols-3"><Skeleton className="h-36" /><Skeleton className="h-36" /><Skeleton className="h-36" /></div></div>
    </main>
  )
}

export function TableSkeleton({ rows = 6 }) {
  return <div className="space-y-3 p-5">{Array.from({ length: rows }, (_, index) => <Skeleton key={index} className="h-14 w-full" />)}</div>
}

export function SettingsSkeleton() {
  return <div className="grid gap-6 lg:grid-cols-[1fr_340px]"><Skeleton className="h-[520px]" /><Skeleton className="h-96" /></div>
}
