import axios from 'axios'

const baseURL = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'

export const api = axios.create({
  baseURL,
  headers: { Accept: 'application/json' },
})

const TOKEN_KEY = 'retail.token'

export const tokenStore = {
  get: (): string | null => localStorage.getItem(TOKEN_KEY),
  set: (token: string): void => localStorage.setItem(TOKEN_KEY, token),
  clear: (): void => localStorage.removeItem(TOKEN_KEY),
}

// Attach the bearer token to every request.
api.interceptors.request.use((config) => {
  const token = tokenStore.get()
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// On an expired/invalid token, drop it and bounce to the login screen.
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (axios.isAxiosError(error) && error.response?.status === 401) {
      tokenStore.clear()
      if (!window.location.pathname.startsWith('/login')) {
        window.location.assign('/login')
      }
    }
    return Promise.reject(error)
  },
)

interface ApiErrorBody {
  message?: string
  errors?: Record<string, string[]>
}

/**
 * Extract a human-friendly message from an axios error, preferring the first
 * field validation error, then the top-level message.
 */
export function apiErrorMessage(error: unknown, fallback = 'Something went wrong.'): string {
  if (axios.isAxiosError(error)) {
    const body = error.response?.data as ApiErrorBody | undefined
    if (body?.errors) {
      const first = Object.values(body.errors)[0]
      if (first && first.length > 0) {
        return first[0]
      }
    }
    if (body?.message) {
      return body.message
    }
    if (error.code === 'ERR_NETWORK') {
      return 'Cannot reach the API. Is the Laravel server running?'
    }
  }
  return fallback
}
