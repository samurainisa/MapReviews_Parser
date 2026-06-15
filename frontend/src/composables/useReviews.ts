import { ref } from 'vue'
import { http } from '../app/axios'
import type { Review } from '../types/review'
import type { PaginationMeta } from '../types/api'

/**
 * Загрузка отзывов из собственного API с серверной пагинацией по 50.
 */
export function useReviews() {
  const reviews = ref<Review[]>([])
  const meta = ref<PaginationMeta | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchPage(page: number): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const { data } = await http.get<{ data: Review[]; meta: PaginationMeta }>(
        '/api/organization/reviews',
        { params: { page } },
      )
      reviews.value = data.data
      meta.value = data.meta
    } catch {
      error.value = 'Не удалось загрузить отзывы.'
      reviews.value = []
    } finally {
      loading.value = false
    }
  }

  return { reviews, meta, loading, error, fetchPage }
}
