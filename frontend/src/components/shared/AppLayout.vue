<script setup lang="ts">
import { useAuthStore } from '../../stores/auth.store'
import { useRouter } from 'vue-router'
import BaseButton from './BaseButton.vue'

const auth = useAuthStore()
const router = useRouter()

async function onLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="layout">
    <header class="layout__header">
      <div class="layout__inner">
        <span class="layout__brand">Yandex Maps Reviews Connector</span>
        <div
          v-if="auth.user"
          class="layout__user"
        >
          <span class="layout__email">{{ auth.user.email }}</span>
          <BaseButton
            variant="secondary"
            @click="onLogout"
          >
            Выйти
          </BaseButton>
        </div>
      </div>
    </header>
    <main class="layout__main">
      <slot />
    </main>
  </div>
</template>

<style scoped>
.layout__header {
  background: var(--surface);
  border-bottom: 1px solid var(--border);
}
.layout__inner,
.layout__main {
  max-width: 880px;
  margin: 0 auto;
  padding: 0 20px;
}
.layout__inner {
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.layout__brand {
  font-weight: 700;
}
.layout__user {
  display: flex;
  align-items: center;
  gap: 14px;
}
.layout__email {
  font-size: 14px;
  color: var(--text-muted);
}
.layout__main {
  padding-top: 28px;
  padding-bottom: 60px;
}
</style>
