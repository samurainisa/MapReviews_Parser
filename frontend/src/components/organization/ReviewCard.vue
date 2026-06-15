<script setup lang="ts">
import { computed } from 'vue'
import type { Review } from '../../types/review'

const props = defineProps<{ review: Review }>()

const author = computed(() => props.review.author_name || 'Анонимный пользователь.')

const date = computed(() => {
  if (!props.review.review_date) return 'Дата не указана.'
  return new Date(props.review.review_date).toLocaleDateString('ru-RU', { dateStyle: 'long' })
})

const text = computed(() => props.review.text || 'Пользователь оставил оценку без текста.')

const stars = computed(() => {
  const r = props.review.rating
  if (!r) return null
  return '★'.repeat(r) + '☆'.repeat(Math.max(0, 5 - r))
})
</script>

<template>
  <article class="review">
    <div class="review__head">
      <span class="review__author">{{ author }}</span>
      <span v-if="stars" class="review__stars" :title="`${review.rating} из 5`">{{ stars }}</span>
    </div>
    <!-- Текст выводится через интерполяцию Vue (экранируется), без v-html. -->
    <p class="review__text" :class="{ 'review__text--muted': !review.text }">{{ text }}</p>
    <span class="review__date">{{ date }}</span>
  </article>
</template>

<style scoped>
.review {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 16px 18px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
}
.review__head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}
.review__author {
  font-weight: 600;
}
.review__stars {
  color: #f5a623;
  letter-spacing: 1px;
  white-space: nowrap;
}
.review__text {
  margin: 0;
  line-height: 1.5;
  white-space: pre-line;
}
.review__text--muted {
  color: var(--text-muted);
  font-style: italic;
}
.review__date {
  font-size: 13px;
  color: var(--text-muted);
}
</style>
