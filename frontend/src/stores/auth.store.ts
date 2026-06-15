import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { http, ensureCsrfCookie } from '../app/axios'
import type { LoginPayload, User } from '../types/auth'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const ready = ref(false) // первичная проверка сессии завершена
  const loading = ref(false)

  const isAuthenticated = computed(() => user.value !== null)

  /** Восстановление сессии при старте приложения / навигации. */
  async function fetchUser(): Promise<void> {
    try {
      const { data } = await http.get<{ user: User }>('/api/me')
      user.value = data.user
    } catch {
      user.value = null
    } finally {
      ready.value = true
    }
  }

  async function login(payload: LoginPayload): Promise<void> {
    loading.value = true
    try {
      await ensureCsrfCookie()
      const { data } = await http.post<{ user: User }>('/api/login', payload)
      user.value = data.user
    } finally {
      loading.value = false
    }
  }

  async function logout(): Promise<void> {
    try {
      await http.post('/api/logout')
    } finally {
      user.value = null
    }
  }

  return { user, ready, loading, isAuthenticated, fetchUser, login, logout }
})
