import { createContext, useContext } from 'react'

export type User = { id: number; email: string; createdAt: string }

/** What every component can ask about the visitor, via useAuth(). */
export type Auth = {
  /** null = not logged in */
  user: User | null
  /** true until the API has answered "who am I?" for this page load */
  loading: boolean
  login: (email: string, password: string) => Promise<void>
  register: (email: string, password: string) => Promise<void>
  logout: () => Promise<void>
}

export const AuthContext = createContext<Auth | null>(null)

export function useAuth(): Auth {
  const auth = useContext(AuthContext)
  if (auth === null) {
    throw new Error('useAuth() must be used inside <AuthProvider>')
  }
  return auth
}
