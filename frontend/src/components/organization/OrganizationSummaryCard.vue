<script setup lang="ts">
import { computed } from 'vue'
import type { Organization } from '../../types/organization'

const props = defineProps<{ organization: Organization }>()

const formattedDate = computed(() => {
  if (!props.organization.last_parsed_at) return null
  return new Date(props.organization.last_parsed_at).toLocaleString('ru-RU', {
    dateStyle: 'medium',
    timeStyle: 'short',
  })
})

const heading = computed(
  () => props.organization.title ?? props.organization.normalized_url ?? 'Организация',
)
</script>

<template>
  <section class="summary">
    <header class="summary__head">
      <h2 class="summary__title">{{ heading }}</h2>
      <p v-if="organization.address" class="summary__address">{{ organization.address }}</p>
      <a class="summary__link" :href="organization.normalized_url ?? organization.source_url" target="_blank" rel="noopener">
        Открыть в Яндекс.Картах
      </a>
    </header>

    <div class="summary__stats">
      <div class="stat">
        <span class="stat__value">{{ organization.rating ?? '—' }}</span>
        <span class="stat__label">Средний рейтинг</span>
      </div>
      <div class="stat">
        <span class="stat__value">{{ organization.ratings_count ?? '—' }}</span>
        <span class="stat__label">Оценок</span>
      </div>
      <div class="stat">
        <span class="stat__value">{{ organization.reviews_count ?? '—' }}</span>
        <span class="stat__label">Отзывов</span>
      </div>
      <div class="stat">
        <span class="stat__value">{{ organization.loaded_reviews_count }}</span>
        <span class="stat__label">Загружено</span>
      </div>
    </div>

    <p v-if="formattedDate" class="summary__meta">
      Последнее обновление: {{ formattedDate }}
    </p>
  </section>
</template>

<style scoped>
.summary {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  padding: 22px;
  display: flex;
  flex-direction: column;
  gap: 18px;
}
.summary__title {
  font-size: 19px;
}
.summary__address {
  margin: 6px 0 0;
  color: var(--text-muted);
  font-size: 14px;
}
.summary__link {
  display: inline-block;
  margin-top: 8px;
  font-size: 13px;
}
.summary__stats {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
}
.stat {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 14px;
  background: var(--bg);
  border-radius: var(--radius);
  text-align: center;
}
.stat__value {
  font-size: 22px;
  font-weight: 700;
}
.stat__label {
  font-size: 12px;
  color: var(--text-muted);
}
.summary__meta {
  margin: 0;
  font-size: 13px;
  color: var(--text-muted);
}
@media (max-width: 560px) {
  .summary__stats {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
