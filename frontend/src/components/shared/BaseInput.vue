<script setup lang="ts">
defineProps<{
  label?: string
  type?: string
  placeholder?: string
  error?: string | null
  autocomplete?: string
  disabled?: boolean
}>()

const model = defineModel<string>({ required: true })
</script>

<template>
  <label class="field">
    <span v-if="label" class="field__label">{{ label }}</span>
    <input
      v-model="model"
      class="field__input"
      :class="{ 'field__input--error': error }"
      :type="type ?? 'text'"
      :placeholder="placeholder"
      :autocomplete="autocomplete"
      :disabled="disabled"
    />
    <span v-if="error" class="field__error">{{ error }}</span>
  </label>
</template>

<style scoped>
.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.field__label {
  font-size: 14px;
  font-weight: 600;
  color: var(--text);
}
.field__input {
  padding: 10px 12px;
  font-size: 15px;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  background: var(--surface);
  color: var(--text);
  transition: border-color 0.15s;
}
.field__input:focus {
  outline: none;
  border-color: var(--primary);
}
.field__input--error {
  border-color: var(--danger);
}
.field__error {
  font-size: 13px;
  color: var(--danger);
}
</style>
