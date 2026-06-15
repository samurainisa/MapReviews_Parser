<script setup lang="ts">
import { computed } from 'vue'
import type { Organization } from '../../types/organization'
import BaseAlert from '../shared/BaseAlert.vue'
import BaseLoader from '../shared/BaseLoader.vue'

const props = defineProps<{ organization: Organization }>()

const variant = computed<'info' | 'success' | 'warning' | 'danger'>(() => {
  switch (props.organization.parse_status) {
    case 'completed':
      return props.organization.is_partial ? 'warning' : 'success'
    case 'failed':
      return 'danger'
    default:
      return 'info'
  }
})

const isParsing = computed(
  () =>
    props.organization.parse_status === 'pending' ||
    props.organization.parse_status === 'processing',
)

const message = computed(() => {
  const o = props.organization
  switch (o.parse_status) {
    case 'pending':
      return 'Ссылка сохранена. Получение данных скоро начнётся.'
    case 'processing':
      return 'Получаем данные организации. Это может занять некоторое время.'
    case 'completed':
      return o.is_partial
        ? `Данные получены частично. Загружено ${o.loaded_reviews_count} отзывов из ${o.reviews_count}.`
        : 'Данные организации обновлены.'
    case 'failed':
      return o.last_error ?? 'Не удалось получить данные.'
    default:
      return o.parse_status_label
  }
})
</script>

<template>
  <BaseAlert :variant="variant">
    <BaseLoader v-if="isParsing" :label="message" />
    <template v-else>{{ message }}</template>
  </BaseAlert>
</template>
