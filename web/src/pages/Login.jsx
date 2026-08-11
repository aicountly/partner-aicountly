import { useState } from 'react'
import { Navigate, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../lib/auth.jsx'

/**
 * Sign-in. There is deliberately no "create account" or "sign up" affordance —
 * partner accounts are issued from Engage only.
 */
export default function Login() {
  const { partner, login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()

  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState(null)
  const [submitting, setSubmitting] = useState(false)

  if (partner) {
    return <Navigate to={location.state?.from || '/'} replace />
  }

  async function onSubmit(e) {
    e.preventDefault()
    setError(null)
    setSubmitting(true)
    const result = await login(email, password)
    setSubmitting(false)

    if (result.ok) {
      navigate(location.state?.from || '/', { replace: true })
    } else {
      setError(result.error)
    }
  }

  return (
    <div className="min-h-screen bg-gradient-to-b from-aicountly-50 to-neutral-50">
      <main className="mx-auto w-full max-w-sm px-5 pt-20 pb-10">
        <div className="partner-card shadow-sm">
          <div className="mb-6 flex items-center gap-3">
            <div className="grid h-9 w-9 place-items-center rounded-lg bg-aicountly-600 text-sm font-bold text-white">
              PA
            </div>
            <div className="leading-tight">
              <div className="text-sm font-semibold">AICOUNTLY</div>
              <div className="-mt-0.5 text-xs font-medium text-aicountly-700">Partner Portal</div>
            </div>
          </div>

          <h1 className="text-xl font-semibold">Sign in</h1>
          <p className="mt-1 mb-5 text-sm text-neutral-500">
            Use the credentials issued to you by AICOUNTLY.
          </p>

          {error ? (
            <div className="partner-alert mb-4 border-red-200 bg-red-50 text-red-800" role="alert">
              {error}
            </div>
          ) : null}

          <form className="space-y-4" onSubmit={onSubmit}>
            <div>
              <label className="partner-label" htmlFor="email">Email</label>
              <input
                id="email"
                type="email"
                className="partner-input"
                autoComplete="username"
                required
                autoFocus
                value={email}
                onChange={(e) => setEmail(e.target.value)}
              />
            </div>

            <div>
              <label className="partner-label" htmlFor="password">Password</label>
              <input
                id="password"
                type="password"
                className="partner-input"
                autoComplete="current-password"
                required
                value={password}
                onChange={(e) => setPassword(e.target.value)}
              />
            </div>

            <button type="submit" className="partner-btn-primary w-full" disabled={submitting}>
              {submitting ? 'Signing in…' : 'Login'}
            </button>
          </form>
        </div>

        <p className="mt-4 text-center text-xs text-neutral-500">
          Partner accounts are issued by AICOUNTLY. Contact your AICOUNTLY representative if you need access.
        </p>
      </main>
    </div>
  )
}
