import { useEffect, useState } from 'react'
import api, { apiError } from '../lib/api.js'

/**
 * Read-only. Partner details and portal passwords are maintained in Engage, so
 * this page shows the record but offers no way to edit it.
 */
export default function Profile() {
  const [profile, setProfile] = useState(null)
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let cancelled = false
    api.get('/v1/profile')
      .then((res) => { if (!cancelled) setProfile(res.data?.data ?? null) })
      .catch((err) => { if (!cancelled) setError(apiError(err)) })
      .finally(() => { if (!cancelled) setLoading(false) })
    return () => { cancelled = true }
  }, [])

  if (loading) return <div className="text-sm text-neutral-500">Loading…</div>

  if (error) {
    return <div className="partner-alert border-red-200 bg-red-50 text-red-800">{error}</div>
  }

  const rows = [
    ['Partner ID', profile?.partner_uid, true],
    ['Partner name', profile?.name],
    ['Contact person', profile?.contact_name],
    ['Email', profile?.email],
    ['Phone', profile?.phone],
    ['Partner type', profile?.partner_type],
    ['Website', profile?.website],
    ['Location', [profile?.city, profile?.country].filter(Boolean).join(', ')],
    ['Status', profile?.status],
    ['Last sign-in', profile?.last_login_at || 'This is your first sign-in'],
  ]

  return (
    <>
      <h1 className="text-xl font-semibold">Profile</h1>
      <p className="mt-1 mb-6 text-sm text-neutral-500">Your partner record as held by AICOUNTLY.</p>

      <section className="partner-card">
        <dl className="grid gap-3 sm:grid-cols-2">
          {rows.map(([label, value, mono]) => (
            <div key={label}>
              <dt className="text-xs text-neutral-500">{label}</dt>
              <dd className={mono ? 'break-all font-mono text-sm' : 'text-sm font-medium'}>
                {value || '—'}
              </dd>
            </div>
          ))}
        </dl>

        <p className="mt-5 text-xs text-neutral-500">
          Partner details and portal passwords are maintained by AICOUNTLY. To update anything on this page, or to
          have your password reset, contact your AICOUNTLY representative.
        </p>
      </section>
    </>
  )
}
