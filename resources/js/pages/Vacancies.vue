<template>
  <Layout>
    <div class="max-w-4xl mx-auto py-8 px-4">
      <h1 class="text-2xl md:text-3xl font-bold text-center mb-6 text-[#005eb8]">Актуальные вакансии</h1>

      <div class="flex justify-center mb-6 gap-2 flex-wrap">
        <button
          v-for="type in types"
          :key="type.value"
          @click="filterType = type.value"
          :class="
            filterType === type.value
              ? 'bg-[#005eb8] text-white'
              : 'border border-[#005eb8] text-[#005eb8] hover:bg-[#005eb8] hover:text-white'
          "
          class="px-4 py-2 rounded-lg transition font-medium cursor-pointer"
        >
          {{ type.label }}
        </button>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div
          v-for="vacancy in filteredVacancies"
          :key="vacancy.id"
          class="bg-white rounded-xl shadow border border-gray-100 p-4 flex flex-col justify-between"
        >
          <div>
            <h2 class="text-xl font-semibold mb-2 text-[#005eb8]">{{ vacancy.title }}</h2>
            <p class="text-gray-700 mb-2">{{ vacancy.description }}</p>
            <p class="text-sm text-gray-500">Тип: {{ vacancy.type === 'staff' ? 'Сотрудники' : 'ППС' }}</p>
            <p class="text-sm text-gray-500">Должность: {{ vacancy.position?.name || 'Не указана' }}</p>
          </div>
          <button
            @click="applyToVacancy(vacancy)"
            class="mt-4 inline-block bg-[#005eb8] hover:bg-blue-700 text-white text-center font-semibold py-2 px-4 rounded-lg transition w-full"
          >
            Откликнуться
          </button>
        </div>
      </div>
    </div>
  </Layout>
</template>

<script setup>
import Layout from '../components/Layout.vue';
import { ref, computed, onMounted } from 'vue';
import { useAuthStore } from '../stores/useAuthStore';
import axios from 'axios';
import { useRouter } from 'vue-router';

const authStore = useAuthStore();
const router = useRouter();

const vacancies = ref([]);
const loading = ref(true);

const fetchVacancies = async () => {
  loading.value = true;
  try {
    const response = await axios.get('/api/vacancies');
    vacancies.value = response.data;
  } catch (error) {
    console.error('Ошибка загрузки вакансий', error);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchVacancies();
});

const types = [
  { label: 'Все', value: 'all' },
  { label: 'Сотрудники', value: 'staff' },
  { label: 'ППС', value: 'pps' },
];
const filterType = ref('all');

const filteredVacancies = computed(() => {
  if (filterType.value === 'all') return vacancies.value;
  return vacancies.value.filter((v) => v.type === filterType.value);
});

const applyToVacancy = (vacancy) => {
  if (!authStore.user) {
    alert('Необходимо войти, чтобы откликнуться.');
    router.push('/login');
    return;
  }

  router.push({ path: '/apply', query: { vacancy_id: vacancy.id } });
};
</script>
