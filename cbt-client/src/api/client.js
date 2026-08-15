import axios from 'axios'

export const TOKEN_KEY = 'teknoplek_cbt_token'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api/v1',
  headers: { Accept: 'application/json' },
  timeout: 15000,
})

api.interceptors.request.use((config) => {
  const token = sessionStorage.getItem(TOKEN_KEY)
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

export function getErrorMessage(error, fallback = 'Terjadi kesalahan. Silakan coba lagi.') {
  const validationErrors = error.response?.data?.errors
  if (validationErrors) return Object.values(validationErrors).flat()[0]
  return error.response?.data?.message ?? fallback
}

export default api
