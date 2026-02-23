<template>
  <Layout>
    <div class="max-w-5xl mx-auto py-8 px-4 space-y-6">
      <h1 class="text-2xl md:text-3xl font-bold text-center text-[#005eb8]">
        Департаменты и должности
      </h1>

      <div class="bg-white rounded-xl shadow border border-gray-100 p-4">
        <input
          v-model="search"
          type="text"
          placeholder="Поиск по департаменту, должности, обязанностям и требованиям"
          class="w-full border border-gray-300 rounded-lg px-4 py-2"
        />
      </div>

      <div v-if="loading" class="text-center text-gray-500">Загрузка...</div>
      <div v-else-if="filteredDepartments.length === 0" class="text-center text-gray-600">Ничего не найдено.</div>

      <div v-else class="space-y-3">
        <div
          v-for="department in filteredDepartments"
          :key="department.id"
          class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden"
        >
          <button
            @click="toggleDepartment(department.id)"
            class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-gray-50 transition cursor-pointer"
          >
            <div>
              <div class="font-semibold text-[#005eb8]">{{ department.name }}</div>
              <div class="text-xs text-gray-500">Должностей: {{ department.positions?.length || 0 }}</div>
            </div>
            <span class="text-[#005eb8] text-lg">{{ expanded[department.id] ? '−' : '+' }}</span>
          </button>

          <div v-if="expanded[department.id]" class="border-t border-gray-100 p-4">
            <p v-if="department.description" class="text-sm text-gray-700 mb-3 whitespace-pre-line">
              {{ department.description }}
            </p>

            <div v-if="!department.positions || department.positions.length === 0" class="text-sm text-gray-500">
              В этом департаменте пока нет должностей.
            </div>
            <ul v-else class="space-y-2">
              <li
                v-for="position in department.positions"
                :key="position.id"
                class="border border-gray-200 rounded-lg px-3 py-2"
              >
                <div class="font-medium text-gray-800">{{ position.name }}</div>
                <div v-if="position.duties" class="text-sm text-gray-700 mt-1 whitespace-pre-line">
                  <span class="font-semibold">Должностные обязанности:</span> {{ position.duties }}
                </div>
                <div v-if="position.qualification" class="text-sm text-gray-700 mt-1 whitespace-pre-line">
                  <span class="font-semibold">Требования к квалификации:</span> {{ position.qualification }}
                </div>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </Layout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import Layout from '../components/Layout.vue';

const departments = ref([]);
const loading = ref(true);
const expanded = ref({});
const search = ref('');

const errorText = (error) => error?.response?.data?.message || 'Ошибка. Попробуйте снова.';

const filteredDepartments = computed(() => {
  const q = search.value.trim().toLowerCase();
  if (!q) return departments.value;

  return departments.value
    .map((department) => {
      const departmentName = (department.name || '').toLowerCase();
      const departmentDescription = (department.description || '').toLowerCase();
      const departmentMatched = departmentName.includes(q) || departmentDescription.includes(q);

      const filteredPositions = (department.positions || []).filter((position) => {
        const name = (position.name || '').toLowerCase();
        const duties = (position.duties || '').toLowerCase();
        const qualification = (position.qualification || '').toLowerCase();
        return name.includes(q) || duties.includes(q) || qualification.includes(q);
      });

      if (departmentMatched) {
        return department;
      }

      if (filteredPositions.length > 0) {
        return { ...department, positions: filteredPositions };
      }

      return null;
    })
    .filter(Boolean);
});

const fetchDepartments = async () => {
  loading.value = true;
  try {
    const response = await axios.get('/api/admin/departments');
    departments.value = response.data;
  } catch (error) {
    alert(errorText(error));
  } finally {
    loading.value = false;
  }
};

const toggleDepartment = (departmentId) => {
  expanded.value = {
    ...expanded.value,
    [departmentId]: !expanded.value[departmentId],
  };
};

onMounted(fetchDepartments);
</script>
