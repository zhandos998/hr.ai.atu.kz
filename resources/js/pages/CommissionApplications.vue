<template>
  <Layout>
    <div class="max-w-6xl mx-auto py-8 px-4 space-y-6">
      <h1 class="text-2xl md:text-3xl font-bold text-[#005eb8] text-center">Голосование комиссии</h1>

      <div v-if="loading" class="text-gray-500 text-center">Загрузка...</div>
      <div v-else-if="applications.length === 0" class="text-gray-600 text-center">
        Нет заявок для голосования.
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
          </div>

          <div class="grid grid-cols-2 md:grid-cols-5 gap-2 text-sm">
            <div class="bg-blue-50 rounded-lg p-2 text-center">
              <div class="text-gray-500">Всего членов</div>
              <div class="font-semibold">{{ app.vote_summary?.total_members || 0 }}</div>
            </div>
            <div class="bg-emerald-50 rounded-lg p-2 text-center">
              <div class="text-gray-500">За</div>
              <div class="font-semibold text-emerald-700">{{ app.vote_summary?.hire || 0 }}</div>
            </div>
            <div class="bg-red-50 rounded-lg p-2 text-center">
              <div class="text-gray-500">Против</div>
              <div class="font-semibold text-red-700">{{ app.vote_summary?.reject || 0 }}</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-2 text-center">
              <div class="text-gray-500">Проголосовали</div>
              <div class="font-semibold">{{ app.vote_summary?.voted || 0 }}</div>
            </div>
            <div class="bg-amber-50 rounded-lg p-2 text-center">
              <div class="text-gray-500">Ожидается</div>
              <div class="font-semibold">{{ app.vote_summary?.pending || 0 }}</div>
            </div>
          </div>

          <div class="text-sm">
            Итог голосования:
            <span
              v-if="app.vote_summary?.result === 'approved'"
              class="font-semibold text-emerald-700"
            >
              Взять на работу
            </span>
            <span
              v-else-if="app.vote_summary?.result === 'rejected'"
              class="font-semibold text-red-700"
            >
              Не брать
            </span>
            <span v-else class="font-semibold text-gray-700">Ожидание голосов</span>
          </div>

          <div class="border border-gray-200 rounded-lg p-3 space-y-2">
            <div class="text-sm font-semibold text-gray-800">Ваш голос</div>
            <div v-if="app.vote_summary?.my_vote" class="text-sm text-gray-700">
              Текущий выбор:
              <span class="font-semibold">{{ app.vote_summary.my_vote.decision === 'hire' ? 'За' : 'Против' }}</span>
            </div>
            <textarea
              v-model="comments[app.id]"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
              placeholder="Комментарий (необязательно)"
              rows="2"
            />
            <div class="flex flex-wrap gap-2">
              <button
                @click="vote(app.id, 'hire')"
                class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition cursor-pointer"
              >
                Голосовать: За
              </button>
              <button
                @click="vote(app.id, 'reject')"
                class="bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition cursor-pointer"
              >
                Голосовать: Против
              </button>
            </div>
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
const comments = ref({});
const loading = ref(true);

const errorText = (error) => error?.response?.data?.message || 'Ошибка. Попробуйте снова.';

const fetchQueue = async () => {
  loading.value = true;
  try {
    const response = await axios.get('/api/commission/applications');
    applications.value = response.data;
  } catch (error) {
    alert(errorText(error));
  } finally {
    loading.value = false;
  }
};

const vote = async (applicationId, decision) => {
  try {
    await axios.post(`/api/commission/applications/${applicationId}/vote`, {
      decision,
      comment: comments.value[applicationId] || null,
    });
    await fetchQueue();
  } catch (error) {
    alert(errorText(error));
  }
};

onMounted(fetchQueue);
</script>
