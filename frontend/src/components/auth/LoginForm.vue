<script setup lang="ts">
import { reactive, ref } from 'vue'
import { AxiosError } from 'axios'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth.store'
import type { ValidationErrorResponse } from '../../types/api'
import BaseInput from '../shared/BaseInput.vue'
import BaseButton from '../shared/BaseButton.vue'
import BaseAlert from '../shared/BaseAlert.vue'

const auth = useAuthStore()
const router = useRouter()

const form = reactive({ email: 'test@example.com', password: 'password' })
const error = ref<string | null>(null)

async function onSubmit() {
  if (auth.loading) return
  error.value = null
  try {
    await auth.login({ email: form.email, password: form.password })
    router.push({ name: 'settings' })
  } catch (e) {
    const err = e as AxiosError<ValidationErrorResponse>
    error.value =
      err.response?.data?.errors?.email?.[0] ??
      err.response?.data?.message ??
      'Не удалось войти. Попробуйте ещё раз.'
  }
}
</script>

<template>
  <form
    class="login-form"
    @submit.prevent="onSubmit"
  >
    <h1 class="login-form__title">
      Вход
    </h1>
    <p class="login-form__hint">
      Войдите, чтобы подключить карточку организации.
    </p>

    <BaseAlert
      v-if="error"
      variant="danger"
    >
      {{ error }}
    </BaseAlert>

    <BaseInput
      v-model="form.email"
      label="Email"
      type="email"
      autocomplete="username"
      placeholder="test@example.com"
      :disabled="auth.loading"
    />
    <BaseInput
      v-model="form.password"
      label="Пароль"
      type="password"
      autocomplete="current-password"
      placeholder="••••••••"
      :disabled="auth.loading"
    />

    <BaseButton
      type="submit"
      :loading="auth.loading"
    >
      Войти
    </BaseButton>
  </form>
</template>

<style scoped>
.login-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
  width: 100%;
  max-width: 380px;
  padding: 32px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
}
.login-form__title {
  font-size: 22px;
}
.login-form__hint {
  margin: -6px 0 0;
  color: var(--text-muted);
  font-size: 14px;
}
</style>
