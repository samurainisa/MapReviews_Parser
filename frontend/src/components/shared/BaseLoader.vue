<script setup lang="ts">
defineProps<{ label?: string }>()
</script>

<template>
  <div class="loader">
    <span
      class="loader__spinner"
      aria-hidden="true"
    />
    <span
      v-if="label"
      class="loader__label"
    >{{ label }}</span>
  </div>
</template>

<style scoped>
.loader {
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--text-muted);
  font-size: 14px;
}
.loader__spinner {
  width: 18px;
  height: 18px;
  border: 2px solid var(--border);
  border-top-color: var(--primary);
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
