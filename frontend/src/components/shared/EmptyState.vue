<script setup lang="ts">
defineProps<{ title?: string }>()
</script>

<template>
  <div class="empty">
    <p v-if="title" class="empty__title">{{ title }}</p>
    <p class="empty__text"><slot /></p>
  </div>
</template>

<style scoped>
.empty {
  padding: 28px 20px;
  text-align: center;
  border: 1px dashed var(--border);
  border-radius: var(--radius);
  background: var(--surface);
}
.empty__title {
  margin: 0 0 6px;
  font-weight: 600;
}
.empty__text {
  margin: 0;
  color: var(--text-muted);
  font-size: 14px;
}
</style>
