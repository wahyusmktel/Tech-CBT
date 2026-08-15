import { useEffect, useState } from 'react'
import api, { TOKEN_KEY } from '../api/client'
import { AuthContext } from './auth-context'

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    async function restoreSession() {
      if (!sessionStorage.getItem(TOKEN_KEY)) { setIsLoading(false); return }
      try {
        const response = await api.get('/auth/me')
        setUser(response.data)
      } catch {
        sessionStorage.removeItem(TOKEN_KEY)
      } finally {
        setIsLoading(false)
      }
    }
    restoreSession()
  }, [])

  function saveSession(data) { sessionStorage.setItem(TOKEN_KEY, data.token); setUser(data.user) }
  function clearSession() { sessionStorage.removeItem(TOKEN_KEY); setUser(null) }

  return <AuthContext.Provider value={{ user, isLoading, saveSession, clearSession }}>{children}</AuthContext.Provider>
}
