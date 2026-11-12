import { useState, type FormEvent, type ReactNode } from 'react'
import styles from './AuthForm.module.css'

type Props = {
  title: string
  submitLabel: string
  /** Tells password managers whether to fill a saved password or suggest a new one. */
  passwordAutoComplete: 'current-password' | 'new-password'
  onSubmit: (email: string, password: string) => Promise<void>
  footer: ReactNode
}

/** The email + password form shared by the login and register pages. */
export function AuthForm({ title, submitLabel, passwordAutoComplete, onSubmit, footer }: Props) {
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    setError(null)
    setSubmitting(true)
    try {
      await onSubmit(String(form.get('email')), String(form.get('password')))
    } catch (err) {
      // The API's message, e.g. "Wrong email or password" or "An account with this email already exists".
      setError(err instanceof Error ? err.message : 'Something went wrong')
    } finally {
      setSubmitting(false)
    }
  }

  const isNewPassword = passwordAutoComplete === 'new-password'

  return (
    <section className={styles.card}>
      <h1 className={styles.title}>{title}</h1>

      <form className={styles.form} onSubmit={handleSubmit}>
        <label className={styles.field}>
          Email
          <input name="email" type="email" required autoComplete="email" className={styles.input} />
        </label>
        <label className={styles.field}>
          Password
          <input
            name="password"
            type="password"
            required
            minLength={isNewPassword ? 8 : undefined}
            maxLength={72}
            autoComplete={passwordAutoComplete}
            className={styles.input}
          />
          {isNewPassword && <span className={styles.hint}>At least 8 characters</span>}
        </label>

        {error && (
          <p className={styles.error} role="alert">
            {error}
          </p>
        )}

        <button type="submit" className={styles.submit} disabled={submitting}>
          {submitting ? 'Please wait…' : submitLabel}
        </button>
      </form>

      <p className={styles.footer}>{footer}</p>
    </section>
  )
}
