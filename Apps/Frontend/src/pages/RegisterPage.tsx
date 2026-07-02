import { useState } from 'react'
import type { FormEvent } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import toast from 'react-hot-toast'
import { useAuth } from '../context/AuthContext'
import { apiErrorMessage } from '../lib/api'
import { usePageTitle } from '../lib/usePageTitle'
import { Button, Field, Input } from '../components/ui'
import { AuthShell } from '../components/AuthShell'
import { LogoMark } from '../components/Logo'

export function RegisterPage() {
  usePageTitle('Create account')
  const { user, register } = useAuth()
  const navigate = useNavigate()
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [confirmation, setConfirmation] = useState('')
  const [submitting, setSubmitting] = useState(false)

  if (user) {
    return <Navigate to="/" replace />
  }

  const onSubmit = async (e: FormEvent) => {
    e.preventDefault()
    if (password !== confirmation) {
      toast.error('Passwords do not match.')
      return
    }
    setSubmitting(true)
    try {
      await register(name, email, password, confirmation)
      toast.success('Account created!')
      navigate('/')
    } catch (error) {
      toast.error(apiErrorMessage(error, 'Registration failed.'))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <AuthShell>
      <div className="mb-8 lg:hidden">
        <LogoMark className="h-10 w-10" />
      </div>
      <h1 className="text-2xl font-semibold tracking-tight text-slate-900">Create your account</h1>
      <p className="mt-1 text-sm text-slate-500">Start managing your retail inventory.</p>

      <form onSubmit={onSubmit} className="mt-8 space-y-4">
        <Field label="Name" htmlFor="name" required>
          <Input
            id="name"
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="Jane Doe"
            required
          />
        </Field>
        <Field label="Email" htmlFor="email" required>
          <Input
            id="email"
            type="email"
            autoComplete="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="you@example.com"
            required
          />
        </Field>
        <Field label="Password" htmlFor="password" hint="At least 8 characters." required>
          <Input
            id="password"
            type="password"
            autoComplete="new-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
        </Field>
        <Field label="Confirm password" htmlFor="confirmation" required>
          <Input
            id="confirmation"
            type="password"
            autoComplete="new-password"
            value={confirmation}
            onChange={(e) => setConfirmation(e.target.value)}
            required
          />
        </Field>
        <Button type="submit" className="w-full" loading={submitting}>
          Create account
        </Button>
      </form>

      <p className="mt-6 text-center text-sm text-slate-500">
        Already have an account?{' '}
        <Link to="/login" className="font-medium text-brand-600 hover:underline">
          Sign in
        </Link>
      </p>
    </AuthShell>
  )
}
