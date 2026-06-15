<script setup lang="ts">
import BaseButton from '../shared/BaseButton.vue'

const props = defineProps<{
  currentPage: number
  lastPage: number
  loading?: boolean
}>()

const emit = defineEmits<{ change: [page: number] }>()

function go(page: number) {
  if (page < 1 || page > props.lastPage || page === props.currentPage) return
  emit('change', page)
}
</script>

<template>
  <nav v-if="lastPage > 1" class="pagination">
    <BaseButton
      variant="secondary"
      :disabled="loading || currentPage <= 1"
      @click="go(currentPage - 1)"
    >
      Назад
    </BaseButton>
    <span class="pagination__info">Страница {{ currentPage }} из {{ lastPage }}</span>
    <BaseButton
      variant="secondary"
      :disabled="loading || currentPage >= lastPage"
      @click="go(currentPage + 1)"
    >
      Вперёд
    </BaseButton>
  </nav>
</template>

<style scoped>
.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding-top: 8px;
}
.pagination__info {
  font-size: 14px;
  color: var(--text-muted);
}
</style>
