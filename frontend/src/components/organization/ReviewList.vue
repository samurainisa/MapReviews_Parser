<script setup lang="ts">
import type { Review } from '../../types/review'
import type { PaginationMeta } from '../../types/api'
import ReviewCard from './ReviewCard.vue'
import ReviewPagination from './ReviewPagination.vue'
import BaseLoader from '../shared/BaseLoader.vue'
import BaseAlert from '../shared/BaseAlert.vue'
import EmptyState from '../shared/EmptyState.vue'

defineProps<{
  reviews: Review[]
  meta: PaginationMeta | null
  loading: boolean
  error: string | null
}>()

const emit = defineEmits<{ changePage: [page: number] }>()
</script>

<template>
  <section class="reviews">
    <h2 class="reviews__title">
      Отзывы
    </h2>

    <BaseAlert
      v-if="error"
      variant="danger"
    >
      {{ error }}
    </BaseAlert>

    <BaseLoader
      v-else-if="loading && reviews.length === 0"
      label="Загружаем отзывы…"
    />

    <EmptyState
      v-else-if="reviews.length === 0"
      title="Отзывов пока нет"
    >
      Когда отзывы появятся в источнике, они отобразятся здесь.
    </EmptyState>

    <template v-else>
      <div
        class="reviews__list"
        :class="{ 'reviews__list--loading': loading }"
      >
        <ReviewCard
          v-for="review in reviews"
          :key="review.id"
          :review="review"
        />
      </div>
      <ReviewPagination
        v-if="meta"
        :current-page="meta.current_page"
        :last-page="meta.last_page"
        :loading="loading"
        @change="(page) => emit('changePage', page)"
      />
    </template>
  </section>
</template>

<style scoped>
.reviews {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.reviews__title {
  font-size: 18px;
}
.reviews__list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  transition: opacity 0.15s;
}
.reviews__list--loading {
  opacity: 0.6;
}
</style>
