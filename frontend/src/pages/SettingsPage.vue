<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useOrganizationStore } from '../stores/organization.store'
import { useReviews } from '../composables/useReviews'
import { usePolling } from '../composables/usePolling'
import AppLayout from '../components/shared/AppLayout.vue'
import OrganizationUrlForm from '../components/organization/OrganizationUrlForm.vue'
import OrganizationParseStatus from '../components/organization/OrganizationParseStatus.vue'
import OrganizationSummaryCard from '../components/organization/OrganizationSummaryCard.vue'
import ReviewList from '../components/organization/ReviewList.vue'
import BaseButton from '../components/shared/BaseButton.vue'
import BaseLoader from '../components/shared/BaseLoader.vue'
import EmptyState from '../components/shared/EmptyState.vue'

const route = useRoute()
const router = useRouter()
const store = useOrganizationStore()
const { organization, loading, saving, isParsing } = storeToRefs(store)
const { reviews, meta, loading: reviewsLoading, error: reviewsError, fetchPage } = useReviews()

/** Безопасный разбор ?page=: query-значение может быть string | string[] | null. */
function parsePage(value: unknown): number {
  const raw = Array.isArray(value) ? value[0] : value
  const n = typeof raw === 'string' ? Number.parseInt(raw, 10) : NaN
  return Number.isInteger(n) && n >= 1 ? n : 1
}

const currentPage = computed(() => parsePage(route.query.page))

const { start: startPolling } = usePolling(async () => {
  await store.fetch()
  if (organization.value && !isParsing.value) {
    if (organization.value.parse_status === 'completed') {
      await loadReviews(1)
    }
    return true // статус финальный — остановить polling
  }
  return false
}, 4000)

async function loadReviews(page: number) {
  await fetchPage(page)
}

function onChangePage(page: number) {
  router.replace({ query: { ...route.query, page } })
}

function onSaved() {
  startPolling()
}

async function onRefresh() {
  const ok = await store.refresh()
  if (ok) startPolling()
}

// Пагинация через query-параметр: реагируем на смену ?page=.
watch(currentPage, (page) => {
  if (organization.value?.parse_status === 'completed') {
    loadReviews(page)
  }
})

onMounted(async () => {
  await store.fetch()
  if (!organization.value) return

  if (isParsing.value) {
    startPolling()
  } else if (organization.value.parse_status === 'completed') {
    await loadReviews(currentPage.value)
  }
})
</script>

<template>
  <AppLayout>
    <div class="settings">
      <section class="settings__form-block">
        <h1 class="settings__title">
          Карточка организации
        </h1>
        <p class="settings__lead">
          Вставьте ссылку на карточку организации в Яндекс.Картах — мы получим
          рейтинг, счётчики и отзывы.
        </p>
        <OrganizationUrlForm
          :initial-url="organization?.source_url"
          @saved="onSaved"
        />
      </section>

      <BaseLoader
        v-if="loading && !organization"
        label="Загружаем данные…"
      />

      <EmptyState
        v-else-if="!organization"
        title="Организация ещё не подключена"
      >
        Добавьте ссылку на карточку организации в Яндекс.Картах, чтобы получить
        отзывы и рейтинг.
      </EmptyState>

      <template v-else>
        <OrganizationParseStatus :organization="organization" />

        <div
          v-if="organization.parse_status === 'completed' || organization.parse_status === 'failed'"
          class="settings__actions"
        >
          <BaseButton
            variant="secondary"
            :loading="saving"
            @click="onRefresh"
          >
            Обновить отзывы
          </BaseButton>
        </div>

        <template v-if="organization.parse_status === 'completed'">
          <OrganizationSummaryCard :organization="organization" />
          <ReviewList
            :reviews="reviews"
            :meta="meta"
            :loading="reviewsLoading"
            :error="reviewsError"
            @change-page="onChangePage"
          />
        </template>
      </template>
    </div>
  </AppLayout>
</template>

<style scoped>
.settings {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.settings__title {
  font-size: 24px;
}
.settings__lead {
  margin: 8px 0 18px;
  color: var(--text-muted);
}
.settings__form-block {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  padding: 24px;
}
.settings__actions {
  display: flex;
  justify-content: flex-end;
}
</style>
