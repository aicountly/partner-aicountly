import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import clsx from 'clsx'
import { useAuth } from '../lib/auth.jsx'

const navItem = ({ isActive }) =>
  clsx(
    'rounded-lg px-3 py-1.5 text-sm font-medium',
    isActive ? 'bg-aicountly-50 text-aicountly-800' : 'text-neutral-700 hover:bg-neutral-100',
  )

export default function Layout() {
  const { partner, logout } = useAuth()
  const navigate = useNavigate()

  async function onLogout() {
    await logout()
    navigate('/login', { replace: true })
  }

  return (
    <div className="min-h-screen">
      <header className="sticky top-0 z-10 border-b border-neutral-200 bg-white">
        <div className="mx-auto flex max-w-5xl items-center justify-between gap-4 px-5 py-3">
          <div className="flex items-center gap-3">
            <div className="grid h-9 w-9 place-items-center rounded-lg bg-aicountly-600 text-sm font-bold text-white">
              PA
            </div>
            <div className="leading-tight">
              <div className="text-sm font-semibold">AICOUNTLY</div>
              <div className="-mt-0.5 text-xs font-medium text-aicountly-700">Partner Portal</div>
            </div>
          </div>

          <nav className="flex items-center gap-1.5">
            <NavLink to="/" end className={navItem}>Dashboard</NavLink>
            <NavLink to="/profile" className={navItem}>Profile</NavLink>
            <button type="button" className="partner-btn-secondary ml-1.5" onClick={onLogout}>
              Logout
            </button>
          </nav>
        </div>
      </header>

      <main className="mx-auto max-w-5xl px-5 py-7">
        <Outlet context={{ partner }} />
      </main>

      <footer className="mx-auto max-w-5xl px-5 pb-8 text-xs text-neutral-400">
        &copy; {new Date().getFullYear()} AICOUNTLY · Partner details are maintained by your AICOUNTLY representative.
      </footer>
    </div>
  )
}
