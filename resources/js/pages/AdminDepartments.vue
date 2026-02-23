<template>
  <Layout>
    <div class="max-w-5xl mx-auto py-8 px-4 space-y-6">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <h1 class="text-2xl md:text-3xl font-bold text-[#005eb8]">Управление департаментами</h1>
        <router-link
          to="/admin/departments/create"
          class="bg-[#005eb8] hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition text-center"
        >
          Добавить департамент
        </router-link>
      </div>

      <section class="bg-white rounded-xl shadow border border-gray-100 p-4 space-y-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
          <h2 class="text-xl font-semibold text-[#005eb8]">Список департаментов</h2>
          <input
            v-model="search"
            type="text"
            placeholder="Поиск по названию или описанию"
            class="w-full md:w-96 border border-gray-300 rounded-lg px-4 py-2"
          />
        </div>

        <div v-if="loading" class="text-gray-500">Загрузка...</div>
        <div v-else-if="filteredDepartments.length === 0" class="text-gray-600">Ничего не найдено.</div>
        <div v-else class="space-y-3">
          <div
            v-for="department in filteredDepartments"
            :key="department.id"
            class="border border-gray-200 rounded-lg p-3"
          >
            <div v-if="editingId !== department.id" class="space-y-2">
              <div class="font-semibold text-[#005eb8]">{{ department.name }}</div>
              <div v-if="department.description" class="text-sm text-gray-700 whitespace-pre-line">
                {{ department.description }}
              </div>
              <div class="text-xs text-gray-500">Должностей: {{ department.positions?.length || 0 }}</div>
              <div class="flex gap-3">
                <button
                  @click="startEdit(department)"
                  class="text-[#005eb8] text-sm hover:underline cursor-pointer"
                >
                  Редактировать
                </button>
                <button
                  @click="deleteDepartment(department.id)"
                  class="text-red-600 text-sm hover:underline cursor-pointer"
                >
                  Удалить
                </button>
              </div>
            </div>

            <div v-else class="space-y-2">
              <input
                v-model="editDepartment.name"
                type="text"
                class="w-full border border-gray-300 rounded-lg px-4 py-2"
              />
              <textarea
                v-model="editDepartment.description"
                class="w-full border border-gray-300 rounded-lg px-4 py-2"
              />
              <div class="flex gap-2">
                <button
                  @click="updateDepartment(department.id)"
                  class="bg-[#005eb8] hover:bg-blue-700 text-white font-semibold py-2 rounded-lg w-full transition cursor-pointer"
                >
                  Сохранить
                </button>
                <button
                  @click="cancelEdit"
                  class="border border-gray-300 text-gray-700 hover:bg-gray-100 font-semibold py-2 rounded-lg w-full transition cursor-pointer"
                >
                  Отмена
                </button>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </Layout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import Layout from '../components/Layout.vue';

const departments = ref([]);
const loading = ref(true);
const search = ref('');

const editingId = ref(null);
const editDepartment = ref({ name: '', description: '' });

const errorText = (error) => error?.response?.data?.message || 'Ошибка. Попробуйте снова.';

const filteredDepartments = computed(() => {
  const q = search.value.trim().toLowerCase();
  if (!q) return departments.value;

  return departments.value.filter((department) => {
    const name = (department.name || '').toLowerCase();
    const description = (department.description || '').toLowerCase();
    return name.includes(q) || description.includes(q);
  });
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

const startEdit = (department) => {
  editingId.value = department.id;
  editDepartment.value = {
    name: department.name || '',
    description: department.description || '',
  };
};

const cancelEdit = () => {
  editingId.value = null;
  editDepartment.value = { name: '', description: '' };
};

const updateDepartment = async (id) => {
  if (!editDepartment.value.name.trim()) {
    alert('Введите название департамента.');
    return;
  }

  try {
    await axios.put(`/api/admin/departments/${id}`, editDepartment.value);
    cancelEdit();
    await fetchDepartments();
  } catch (error) {
    alert(errorText(error));
  }
};

const deleteDepartment = async (id) => {
  if (!confirm('Удалить департамент?')) return;

  try {
    await axios.delete(`/api/admin/departments/${id}`);
    await fetchDepartments();
  } catch (error) {
    alert(errorText(error));
  }
};

onMounted(fetchDepartments);
</script>
