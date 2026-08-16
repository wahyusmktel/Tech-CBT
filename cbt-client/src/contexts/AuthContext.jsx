import { useEffect, useState } from 'react'
import api, { TOKEN_KEY } from '../api/client'
import { AuthContext } from './auth-context'

const USER_KEY = 'teknoplek_cbt_user'

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    async function restoreSession() {
      if (!sessionStorage.getItem(TOKEN_KEY)) { setIsLoading(false); return }
      try {
        const storedUser = JSON.parse(sessionStorage.getItem(USER_KEY) ?? 'null')
        const response = await api.get(storedUser?.role === 'siswa' ? '/student/session' : '/auth/me')
        setUser(response.data)
      } catch {
        sessionStorage.removeItem(TOKEN_KEY)
        sessionStorage.removeItem(USER_KEY)
      } finally {
        setIsLoading(false)
      }
    }
    restoreSession()
  }, [])

  function saveSession(data) { sessionStorage.setItem(TOKEN_KEY, data.token); sessionStorage.setItem(USER_KEY, JSON.stringify(data.user)); setUser(data.user) }
  function clearSession() { sessionStorage.removeItem(TOKEN_KEY); sessionStorage.removeItem(USER_KEY); setUser(null) }
  function updateUser(value) { setUser((current) => { const next = typeof value === 'function' ? value(current) : value; sessionStorage.setItem(USER_KEY, JSON.stringify(next)); return next }) }

  return <AuthContext.Provider value={{ user, isLoading, saveSession, clearSession, updateUser }}>{children}</AuthContext.Provider>
}
