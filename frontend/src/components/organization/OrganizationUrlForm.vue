<script setup lang="ts">
import { ref } from 'vue'
import { useOrganizationStore } from '../../stores/organization.store'
import BaseInput from '../shared/BaseInput.vue'
import BaseButton from '../shared/BaseButton.vue'

const props = defineProps<{ initialUrl?: string }>()
const emit = defineEmits<{ saved: [] }>()

const store = useOrganizationStore()
const url = ref(props.initialUrl ?? '')
const localError = ref<string | null>(null)

const YANDEX_HOST = /(^|\.)yandex\.(ru|com|kz|by|uz|com\.tr)$/i

/** Лёгкая клиентская валидация для быстрого фидбека (финал — на backend). */
function validate(value: string): string | null {
  const trimmed = value.trim()
  if (!trimmed) return 'Введите ссылку на карточку организации.'
  if (trimmed.length > 2048) return 'Ссылка слишком длинная.'
  let parsed: URL
  try {
    parsed = new URL(trimmed)
  } catch {
    return 'Введите корректную ссылку.'
  }
  if (!YANDEX_HOST.test(parsed.hostname)) return 'Ссылка должна вести на Яндекс.Карты.'
  return null
}

async function onSubmit() {
  if (store.saving) return
  localError.value = validate(url.value)
  if (localError.value) return

  const ok = await store.saveUrl(url.value.trim())
  if (ok) emit('saved')
}
</script>

<template>
  <form
    class="url-form"
    @submit.prevent="onSubmit"
  >
    <BaseInput
      v-model="url"
      label="Ссылка на карточку организации в Яндекс.Картах"
      placeholder="https://yandex.ru/maps/org/example/1234567890/"
      :error="localError ?? store.formError"
      :disabled="store.saving"
    />
    <BaseButton
      type="submit"
      :loading="store.saving"
    >
      Сохранить и получить отзывы
    </BaseButton>
  </form>
</template>

<style scoped>
.url-form {
  display: flex;
  flex-direction: column;
  gap: 14px;
  align-items: flex-start;
}
.url-form :deep(.field) {
  width: 100%;
}
</style>
