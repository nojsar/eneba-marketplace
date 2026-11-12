import { Link, Navigate, useSearchParams } from 'react-router'
import { AuthForm } from '../components/AuthForm'
import { safeNextPath } from '../lib/safeNextPath'
import { useAuth } from '../state/authContext'

export function RegisterPage() {
  const { user, register } = useAuth()
  const [params] = useSearchParams()

  // Registering also logs in, so the visitor continues straight to where they were going.
  if (user !== null) {
    return <Navigate to={safeNextPath(params.get('next'))} replace />
  }

  return (
    <AuthForm
      title="Create an account"
      submitLabel="Create account"
      passwordAutoComplete="new-password"
      onSubmit={register}
      footer={
        <>
          Already have an account? <Link to={params.size > 0 ? `/login?${params}` : '/login'}>Log in</Link>
        </>
      }
    />
  )
}
