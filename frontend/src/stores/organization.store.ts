import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { AxiosError } from 'axios'
import { http, ensureCsrfCookie } from '../app/axios'
import type { Organization } from '../types/organization'
import type { ValidationErrorResponse } from '../types/api'

export const useOrganizationStore = defineStore('organization', () => {
  const organization = ref<Organization | null>(null)
  const loading = ref(false)
  const saving = ref(false)
  const formError = ref<string | null>(null)

  const isParsing = computed(() => {
    const status = organization.value?.parse_status
    return status === 'pending' || status === 'processing'
  })

  async function fetch(): Promise<void> {
    loading.value = true
    try {
      const { data } = await http.get<{ organization: Organization | null }>('/api/organization')
      organization.value = data.organization
    } finally {
      loading.value = false
    }
  }

  async function saveUrl(sourceUrl: string): Promise<boolean> {
    saving.value = true
    formError.value = null
    try {
      await ensureCsrfCookie()
      const { data } = await http.post<{ organization: Organization }>(
        '/api/organization/settings',
        { source_url: sourceUrl },
      )
      organization.value = data.organization
      return true
    } catch (e) {
      formError.value = extractError(e)
      return false
    } finally {
      saving.value = false
    }
  }

  async function refresh(): Promise<boolean> {
    saving.value = true
    formError.value = null
    try {
      await ensureCsrfCookie()
      const { data } = await http.post<{ organization: Organization }>('/api/organization/refresh')
      organization.value = data.organization
      return true
    } catch (e) {
      formError.value = extractError(e)
      return false
    } finally {
      saving.value = false
    }
  }

  function extractError(e: unknown): string {
    const err = e as AxiosError<ValidationErrorResponse>
    const data = err.response?.data
    if (data?.errors?.source_url?.length) return data.errors.source_url[0]
    if (data?.message) return data.message
    return 'Не удалось сохранить ссылку. Попробуйте ещё раз.'
  }

  return { organization, loading, saving, formError, isParsing, fetch, saveUrl, refresh }
})
