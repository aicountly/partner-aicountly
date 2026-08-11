import { Link } from 'react-router-dom'
import { useAuth } from '../lib/auth.jsx'

export default function Dashboard() {
  const { partner } = useAuth()

  return (
    <>
      <h1 className="text-xl font-semibold">Welcome, {partner?.name || 'Partner'}</h1>
      <p className="mt-1 mb-6 text-sm text-neutral-500">
        You are signed in to the AICOUNTLY Partner Portal.
      </p>

      <div className="grid gap-4 sm:grid-cols-2">
        <section className="partner-card">
          <h2 className="mb-4 text-sm font-semibold">Your partner account</h2>
          <dl className="grid gap-3">
            <Field label="Partner ID" mono>{partner?.partner_uid || '—'}</Field>
            <Field label="Partner name">{partner?.name || '—'}</Field>
            <Field label="Email">{partner?.email || '—'}</Field>
            <Field label="Status">
              <span className="partner-pill border-aicountly-200 bg-aicountly-50 text-aicountly-800">
                {(partner?.status || 'active').replace(/^./, (c) => c.toUpperCase())}
              </span>
            </Field>
          </dl>
          <Link to="/profile" className="partner-btn-secondary mt-4">View profile</Link>
        </section>

        <section className="partner-card">
          <h2 className="mb-4 text-sm font-semibold">What&rsquo;s next</h2>
          <p className="text-sm text-neutral-500">
            This is the first release of the Partner Portal. Partner programme features will appear here as they
            are released. Your partner details and portal access are maintained by your AICOUNTLY representative —
            contact them if anything needs to change.
          </p>
        </section>
      </div>
    </>
  )
}

function Field({ label, children, mono }) {
  return (
    <div>
      <dt className="text-xs text-neutral-500">{label}</dt>
      <dd className={mono ? 'break-all font-mono text-sm' : 'text-sm font-medium'}>{children}</dd>
    </div>
  )
}
