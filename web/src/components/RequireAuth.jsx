import { Navigate, useLocation } from 'react-router-dom'
import { useAuth } from '../lib/auth.jsx'

/**
 * Client-side guard. It is a convenience, not the security boundary — every
 * API endpoint is independently protected by the server's partner-auth filter,
 * so a tampered bundle still cannot read partner data.
 */
export default function RequireAuth({ children }) {
  const { partner, loading } = useAuth()
  const location = useLocation()

  if (loading) {
    return (
      <div className="grid h-screen place-items-center text-sm text-neutral-500">
        Loading…
      </div>
    )
  }

  if (!partner) {
    return <Navigate to="/login" replace state={{ from: location.pathname }} />
  }

  return children
}
