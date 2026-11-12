import { Link, Navigate, useSearchParams } from 'react-router'
import { AuthForm } from '../components/AuthForm'
import { safeNextPath } from '../lib/safeNextPath'
import { useAuth } from '../state/authContext'

export function LoginPage() {
  const { user, login } = useAuth()
  const [params] = useSearchParams()

  // Logged in (already, or just now via the form): continue to where the visitor was going.
  if (user !== null) {
    return <Navigate to={safeNextPath(params.get('next'))} replace />
  }

  return (
    <AuthForm
      title="Log in"
      submitLabel="Log in"
      passwordAutoComplete="current-password"
      onSubmit={login}
      footer={
        <>
          {/* Keeps ?next= so the visitor still lands where they were going after signing up */}
          New here? <Link to={params.size > 0 ? `/register?${params}` : '/register'}>Create an account</Link>
        </>
      }
    />
  )
}
