import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  withCredentials: true,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

export async function ensureCsrfCookie(): Promise<void> {
  await axios.get('/sanctum/csrf-cookie', {
    withCredentials: true,
    baseURL: window.location.origin,
  })
}

export default api
