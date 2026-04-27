export interface AuthUser {
  id: number
  name: string
  email: string
}

export interface AuthPayload {
  user: AuthUser
  token: string
}

export interface LoginInput {
  email: string
  password: string
  device_name?: string
}

export interface RegisterInput {
  name: string
  email: string
  password: string
  password_confirmation: string
}
