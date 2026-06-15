<script setup lang="ts">
withDefaults(
  defineProps<{ variant?: 'info' | 'success' | 'warning' | 'danger' }>(),
  { variant: 'info' },
)
</script>

<template>
  <div
    class="alert"
    :class="`alert--${variant}`"
    role="alert"
  >
    <slot />
  </div>
</template>

<style scoped>
.alert {
  padding: 12px 14px;
  border-radius: var(--radius);
  font-size: 14px;
  line-height: 1.45;
}
.alert--info {
  background: #eef3fe;
  color: #234a9c;
}
.alert--success {
  background: var(--success-bg);
  color: var(--success);
}
.alert--warning {
  background: var(--warning-bg);
  color: var(--warning);
}
.alert--danger {
  background: var(--danger-bg);
  color: var(--danger);
}
</style>
