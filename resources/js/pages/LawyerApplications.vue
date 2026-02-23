<template>
  <Layout>
    <div class="max-w-6xl mx-auto py-8 px-4">
      <h1 class="text-2xl md:text-3xl font-bold text-center mb-6 text-[#005eb8]">Проверка заявок lawyer</h1>

      <div v-if="loading" class="text-center text-gray-500">Загрузка...</div>
      <div v-else-if="applications.length === 0" class="text-center text-gray-600">
        Заявок со статусом "Документы загружены" нет.
      </div>

      <div v-else class="space-y-4">
        <div
          v-for="app in applications"
          :key="app.id"
          class="bg-white rounded-xl shadow border border-gray-100 p-4 space-y-3"
        >
          <div>
            <h2 class="text-lg font-semibold text-[#005eb8]">{{ app.vacancy?.title || 'Заявка' }}</h2>
            <p class="text-sm text-gray-700">Кандидат: {{ app.user?.name }} ({{ app.user?.email }})</p>
            <p class="text-sm text-gray-700">Телефон: {{ app.user?.phone || 'Не указан' }}</p>
            <p class="text-sm text-gray-500">Дата подачи: {{ formatDate(app.created_at) }}</p>
          </div>

          <div class="flex flex-wrap gap-2">
            <a
              v-if="app.resume_url"
              :href="app.resume_url"
              target="_blank"
              rel="noopener"
              class="border border-[#005eb8] text-[#005eb8] hover:bg-[#005eb8] hover:text-white px-3 py-1 rounded transition"
            >
              Резюме
            </a>

            <template v-if="app.documents_map && Object.keys(app.documents_map).length">
              <a
                v-for="(doc, type) in app.documents_map"
                :key="type"
                :href="doc.url"
                target="_blank"
                rel="noopener"
                class="border border-emerald-600 text-emerald-700 hover:bg-emerald-600 hover:text-white px-3 py-1 rounded transition"
                :download="`${type}-${app.id}`"
              >
                {{ docLabel(type) }}
              </a>
            </template>
          </div>

          <div class="flex flex-wrap gap-2">
            <button
              @click="setStatus(app.id, 'corruption_not_found')"
              class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2 px-3 rounded-lg transition"
            >
              Не выявлены
            </button>
            <button
              @click="setStatus(app.id, 'corruption_found')"
              class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-3 rounded-lg transition"
            >
              Выявлены
            </button>
          </div>
        </div>
      </div>
    </div>
  </Layout>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import Layout from '../components/Layout.vue';

const applications = ref([]);
const loading = ref(true);
const docLabels = {
  id_card: 'Уд. личности',
  diploma: 'Диплом',
  articles: 'Статьи/публикации',
  address_certificate: 'Адресная справка',
};

const errorText = (error) => error?.response?.data?.message || 'Ошибка. Попробуйте снова.';
const parseDocType = (type) => {
  const raw = String(type);
  const match = raw.match(/^(.*)_(\d+)$/);
  if (!match) return { base: raw, index: null };
  return { base: match[1], index: Number(match[2]) };
};
const docLabel = (type) => {
  const parsed = parseDocType(type);
  const base = docLabels[parsed.base] || parsed.base;
  return parsed.index ? `${base} #${parsed.index}` : base;
};

const fetchQueue = async () => {
  loading.value = true;
  try {
    const response = await axios.get('/api/lawyer/applications');
    applications.value = response.data;
  } catch (error) {
    alert(errorText(error));
  } finally {
    loading.value = false;
  }
};

const setStatus = async (id, statusCode) => {
  try {
    await axios.put(`/api/lawyer/applications/${id}/corruption-status`, {
      status_code: statusCode,
    });
    await fetchQueue();
  } catch (error) {
    alert(errorText(error));
  }
};

const formatDate = (value) => new Date(value).toLocaleDateString('ru-RU', { year: 'numeric', month: 'long', day: 'numeric' });

onMounted(fetchQueue);
</script>
