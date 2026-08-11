import axios from 'axios'

/**
 * Resolve the API base URL. In production the SPA and the API are the same
 * origin (partner.aicountly.com and partner.aicountly.com/api), which is what
 * keeps the session cookie first-party — so a relative '/api' is the default.
 */
export function resolveApiBaseUrl(raw) {
  const fallback = '/api'
  const value = (raw ?? '').trim()

  if (!value || value === '/') return fallback

  if (/^https?:\/\//i.test(value)) {
    const url = new URL(value)
    let path = url.pathname.replace(/\/+$/, '') || ''
    if (path === '' || path === '/') path = '/api'
    else if (!path.endsWith('/api')) path = `${path}/api`
    return `${url.origin}${path}`
  }

  const path = value.replace(/\/+$/, '')
  return path.startsWith('/') ? path : `/${path}`
}

const CSRF_HEADER = 'X-CSRF-TOKEN'

const api = axios.create({
  baseURL: resolveApiBaseUrl(import.meta.env.VITE_API_URL),
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
  timeout: 30_000,
  // Send the HttpOnly session cookie with every request.
  withCredentials: true,
})

/**
 * CSRF handling.
 *
 * The token lives in the server-side session and is published on every
 * response as X-CSRF-TOKEN, so it is never placed in a JavaScript-readable
 * cookie (which would mean giving up HttpOnly on the session). We remember the
 * latest value and echo it back on state-changing requests. CodeIgniter
 * rotates the token after each verified write, hence reading it from every
 * response rather than caching once.
 */
let csrfToken = null

api.interceptors.response.use(
  (res) => {
    const fresh = res.headers?.[CSRF_HEADER.toLowerCase()]
    if (fresh) csrfToken = fresh
    return res
  },
  (err) => {
    const fresh = err.response?.headers?.[CSRF_HEADER.toLowerCase()]
    if (fresh) csrfToken = fresh
    return Promise.reject(err)
  },
)

api.interceptors.request.use(async (config) => {
  const method = (config.method || 'get').toLowerCase()
  if (method === 'get' || method === 'head' || method === 'options') {
    return config
  }

  // First write of the session: fetch a token before sending it.
  if (!csrfToken) {
    try {
      await api.get('/health')
    } catch {
      /* the write below will surface any real problem */
    }
  }

  if (csrfToken) {
    config.headers.set
      ? config.headers.set(CSRF_HEADER, csrfToken)
      : (config.headers[CSRF_HEADER] = csrfToken)
  }

  return config
})

export function apiError(err) {
  return err?.response?.data?.error
      || err?.response?.data?.message
      || err?.message
      || 'Something went wrong.'
}

/** Field-level validation errors, when the API returned them. */
export function fieldErrors(err) {
  const details = err?.response?.data?.details
  return details && typeof details === 'object' ? details : null
}

export default api
