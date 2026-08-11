import { Navigate, Route, Routes } from 'react-router-dom'
import Layout from './components/Layout.jsx'
import RequireAuth from './components/RequireAuth.jsx'
import Login from './pages/Login.jsx'
import Dashboard from './pages/Dashboard.jsx'
import Profile from './pages/Profile.jsx'
import { useAuth } from './lib/auth.jsx'

export default function App() {
  const { loading } = useAuth()

  if (loading) {
    return (
      <div className="grid h-screen place-items-center text-sm text-neutral-500">
        Loading Partner Portal…
      </div>
    )
  }

  return (
    <Routes>
      {/* There is deliberately no /signup or /register route. */}
      <Route path="/login" element={<Login />} />
      <Route element={<RequireAuth><Layout /></RequireAuth>}>
        <Route index element={<Dashboard />} />
        <Route path="/profile" element={<Profile />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
