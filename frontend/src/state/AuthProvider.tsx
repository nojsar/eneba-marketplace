import { useEffect, useState, type ReactNode } from 'react'
import { apiGet, apiPost } from '../api/client'
import { AuthContext, type Auth, type User } from './authContext'

/**
 * Knows who is logged in and shares it with the whole app.
 *
 * The session itself is an httpOnly cookie that JavaScript cannot read (so an XSS bug
 * cannot steal it). The app therefore asks the API "who am I?" once per page load,
 * and keeps the answer here.
 */
export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    apiGet<{ user: User | null }>('/auth/me')
      .then((data) => setUser(data.user))
      .catch(() => setUser(null)) // API unreachable: act as logged out; protected pages send people to log in
      .finally(() => setLoading(false))
  }, [])

  const auth: Auth = {
    user,
    loading,
    login: async (email, password) => {
      const data = await apiPost<{ user: User }>('/auth/login', { email, password })
      setUser(data.user)
    },
    register: async (email, password) => {
      const data = await apiPost<{ user: User }>('/auth/register', { email, password })
      setUser(data.user)
    },
    logout: async () => {
      await apiPost('/auth/logout')
      setUser(null)
    },
  }

  return <AuthContext value={auth}>{children}</AuthContext>
}
