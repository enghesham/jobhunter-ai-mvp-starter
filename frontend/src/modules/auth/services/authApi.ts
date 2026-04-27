import api from '@/app/services/api'
import type { AuthPayload, AuthUser, LoginInput, RegisterInput } from '@/modules/auth/types'

type UnknownRecord = Record<string, unknown>

function asRecord(value: unknown): UnknownRecord {
  return typeof value === 'object' && value !== null ? (value as UnknownRecord) : {}
}

function extractToken(payload: unknown): string | null {
  const root = asRecord(payload)
  const data = asRecord(root.data)
  const token = data.token ?? root.token ?? root.access_token ?? data.access_token

  return typeof token === 'string' && token !== '' ? token : null
}

function extractUser(payload: unknown): AuthUser | null {
  const root = asRecord(payload)
  const data = asRecord(root.data)
  const user = asRecord(data.user ?? root.user ?? data)

  if (typeof user.id !== 'number' || typeof user.name !== 'string' || typeof user.email !== 'string') {
    return null
  }

  return {
    id: user.id,
    name: user.name,
    email: user.email,
  }
}

function normalizeAuthPayload(payload: unknown): AuthPayload {
  const token = extractToken(payload)
  const user = extractUser(payload)

  if (!token || !user) {
    throw new Error('Authentication response is missing user or token data.')
  }

  return { user, token }
}

export async function loginRequest(input: LoginInput): Promise<AuthPayload> {
  const response = await api.post('/auth/login', input)

  return normalizeAuthPayload(response.data)
}

export async function registerRequest(input: RegisterInput): Promise<AuthPayload> {
  const response = await api.post('/auth/register', input)

  return normalizeAuthPayload(response.data)
}

export async function meRequest(): Promise<AuthUser> {
  const response = await api.get('/auth/me')
  const user = extractUser(response.data)

  if (!user) {
    throw new Error('Unable to resolve authenticated user.')
  }

  return user
}

export async function logoutRequest(): Promise<void> {
  await api.post('/auth/logout')
}
