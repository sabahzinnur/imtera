<template>
  <AppLayout>
    <h1 class="text-xl font-semibold text-gray-800 mb-6">Подключение площадок</h1>

    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 max-w-xl">
      <h2 class="font-semibold text-gray-700 mb-4">Подключить Яндекс</h2>

      <div v-if="$page.props.flash?.success"
           class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-2 text-sm mb-4">
        {{ $page.props.flash.success }}
      </div>

      <form @submit.prevent="save">
        <label class="block text-sm text-gray-500 mb-1">
          Укажите ссылку на Яндекс, пример
          <span class="text-blue-400 text-xs">
            https://yandex.ru/maps/org/samoye_populyarnoye_kafe/1010501395/reviews/
          </span>
        </label>

        <input
          v-model="form.maps_url"
          type="url"
          placeholder="https://yandex.ru/maps/org/samoye_populyarnoye_kafe/1010501395/reviews/"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 mb-1"
          :class="{ 'border-red-400': errors.maps_url }"
        />

        <p v-if="errors.maps_url" class="text-red-500 text-xs mb-2">{{ errors.maps_url }}</p>

        <button
          type="submit" :disabled="loading"
          class="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-medium px-5 py-2 rounded-lg transition mt-3"
        >
          {{ loading ? 'Сохранение...' : 'Сохранить' }}
        </button>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { settings } from '@/routes'

const props   = defineProps({ setting: Object })
const form    = ref({ maps_url: props.setting?.maps_url ?? '' })
const errors  = ref({})
const loading = ref(false)

function save() {
  errors.value  = {}
  loading.value = true

  router.post(settings.url(), form.value, {
    onError:  (e) => { errors.value = e },
    onFinish: () => { loading.value = false },
  })
}
</script>
