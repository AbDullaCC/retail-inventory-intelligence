import { api } from '../lib/api'
import type { ApiItem, AuthToken, User } from '../types'

export interface RegisterPayload {
  name: string
  email: string
  password: string
  password_confirmation: string
}

export const authApi = {
  login: (email: string, password: string): Promise<AuthToken> =>
    api.post<ApiItem<AuthToken>>('/auth/login', { email, password }).then((r) => r.data.data),

  register: (payload: RegisterPayload): Promise<AuthToken> =>
    api.post<ApiItem<AuthToken>>('/auth/register', payload).then((r) => r.data.data),

  me: (): Promise<User> => api.get<ApiItem<User>>('/auth/me').then((r) => r.data.data),

  logout: (): Promise<void> => api.post('/auth/logout').then(() => undefined),
}
