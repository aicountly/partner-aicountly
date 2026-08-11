import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import api, { apiError } from './api.js'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [partner, setPartner] = useState(null)
  const [loading, setLoading] = useState(true)

  /**
   * Ask the API who we are. The server re-reads the Partner Master on every
   * call, so a partner deactivated or deleted in Engage drops out here.
   */
  const refresh = useCallback(async () => {
    try {
      const res = await api.get('/v1/me')
      setPartner(res.data?.data ?? null)
    } catch {
      setPartner(null)
    }
  }, [])

  useEffect(() => {
    refresh().finally(() => setLoading(false))
  }, [refresh])

  const login = useCallback(async (email, password) => {
    try {
      const res = await api.post('/v1/auth/login', { email, password })
      setPartner(res.data?.data?.partner ?? null)
      return { ok: true }
    } catch (err) {
      return { ok: false, error: apiError(err) }
    }
  }, [])

  const logout = useCallback(async () => {
    try {
      await api.post('/v1/auth/logout')
    } finally {
      setPartner(null)
    }
  }, [])

  const value = useMemo(
    () => ({ partner, loading, login, logout, refresh }),
    [partner, loading, login, logout, refresh],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used inside <AuthProvider>')
  return ctx
}
