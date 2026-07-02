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

export function LoginPage() {
  usePageTitle('Sign in')
  const { user, login } = useAuth()
  const navigate = useNavigate()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [submitting, setSubmitting] = useState(false)

  if (user) {
    return <Navigate to="/" replace />
  }

  const onSubmit = async (e: FormEvent) => {
    e.preventDefault()
    setSubmitting(true)
    try {
      await login(email, password)
      toast.success('Welcome back!')
      navigate('/')
    } catch (error) {
      toast.error(apiErrorMessage(error, 'Login failed.'))
    } finally {
      setSubmitting(false)
    }
  }

  const fillDemo = () => {
    setEmail('demo@retail.test')
    setPassword('password')
  }

  return (
    <AuthShell>
      <div className="mb-8 lg:hidden">
        <LogoMark className="h-10 w-10" />
      </div>
      <h1 className="text-2xl font-semibold tracking-tight text-slate-900">Welcome back</h1>
      <p className="mt-1 text-sm text-slate-500">Sign in to your inventory workspace.</p>

      <form onSubmit={onSubmit} className="mt-8 space-y-4">
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
        <Field label="Password" htmlFor="password" required>
          <Input
            id="password"
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            placeholder="••••••••"
            required
          />
        </Field>
        <Button type="submit" className="w-full" loading={submitting}>
          Sign in
        </Button>
      </form>

      <button
        type="button"
        onClick={fillDemo}
        className="mt-4 w-full rounded-lg border border-dashed border-slate-300 px-3 py-2 text-center text-xs text-slate-500 transition-colors hover:border-brand-400 hover:text-slate-700"
      >
        Use demo credentials — <span className="font-mono">demo@retail.test / password</span>
      </button>

      <p className="mt-6 text-center text-sm text-slate-500">
        No account?{' '}
        <Link to="/register" className="font-medium text-brand-600 hover:underline">
          Create one
        </Link>
      </p>
    </AuthShell>
  )
}
