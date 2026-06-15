import axios from 'axios'

/**
 * Axios-инстанс для cookie-based Sanctum.
 *
 * withCredentials — отправляем session/XSRF cookie на бэкенд.
 * withXSRFToken   — axios читает cookie XSRF-TOKEN и подставляет заголовок
 *                   X-XSRF-TOKEN (нужно для не-GET запросов между доменами).
 */
const baseURL = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8080'

export const http = axios.create({
  baseURL,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
  },
})

/**
 * Получить CSRF-cookie перед изменяющими запросами (login/logout/settings).
 */
export async function ensureCsrfCookie(): Promise<void> {
  await http.get('/sanctum/csrf-cookie')
}
