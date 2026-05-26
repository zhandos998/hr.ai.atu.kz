<template>
  <Layout>
    <div class="max-w-7xl mx-auto py-8 px-4 space-y-6">
      <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
          <h1 class="text-2xl md:text-3xl font-bold text-[#005eb8]">Управление вакансиями</h1>
          <p class="text-sm text-gray-500">Список вакансий, типы, должности и быстрые действия.</p>
        </div>
        <router-link
          to="/admin/vacancies/create"
          class="inline-flex items-center justify-center rounded-xl bg-[#005eb8] px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
        >
          Добавить вакансию
        </router-link>
      </div>

      <section class="space-y-4">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
          <div class="text-sm text-gray-500 md:self-center">
            Показано: <span class="font-semibold text-gray-700">{{ filteredVacancies.length }}</span>
            <span class="text-gray-400"> / {{ vacancies.length }}</span>
          </div>
          <input
            v-model="search"
            type="text"
            placeholder="Поиск по вакансии, должности или описанию"
            class="w-full rounded-xl border border-gray-300 px-4 py-2 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
          />
          <select
            v-model="typeFilter"
            class="w-full rounded-xl border border-gray-300 px-4 py-2 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
          >
            <option value="">Все типы</option>
            <option
              v-for="type in vacancyTypeOptions"
              :key="type.value"
              :value="type.value"
            >
              {{ type.label }}
            </option>
          </select>
          <select
            v-model="departmentFilter"
            class="w-full rounded-xl border border-gray-300 px-4 py-2 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
          >
            <option value="">Все подразделения</option>
            <option
              v-for="department in departmentOptions"
              :key="department.id"
              :value="department.id"
            >
              {{ department.full_name }}
            </option>
          </select>
        </div>

      <div v-if="loading" class="text-center text-gray-500">Загрузка...</div>

      <div
        v-else-if="vacancies.length === 0"
        class="rounded-2xl border border-dashed border-gray-300 bg-white px-4 py-10 text-center text-gray-600"
      >
        Вакансий пока нет.
      </div>

      <div
        v-else-if="filteredVacancies.length === 0"
        class="rounded-2xl border border-dashed border-gray-300 bg-white px-4 py-10 text-center text-gray-600"
      >
        Ничего не найдено.
      </div>

      <div v-else class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
              <tr>
                <th class="px-4 py-3">
                  <TableSortButton
                    label="Вакансия"
                    column="title"
                    :sort-by="sortBy"
                    :sort-direction="sortDirection"
                    @sort="setSort"
                  />
                </th>
                <th class="px-4 py-3">
                  <TableSortButton
                    label="Тип"
                    column="type"
                    :sort-by="sortBy"
                    :sort-direction="sortDirection"
                    @sort="setSort"
                  />
                </th>
                <th class="px-4 py-3">
                  <TableSortButton
                    label="Подразделение / должность"
                    column="department"
                    :sort-by="sortBy"
                    :sort-direction="sortDirection"
                    @sort="setSort"
                  />
                </th>
                <th class="px-4 py-3">
                  <TableSortButton
                    label="Описание"
                    column="description"
                    :sort-by="sortBy"
                    :sort-direction="sortDirection"
                    @sort="setSort"
                  />
                </th>
                <th class="px-4 py-3">
                  <TableSortButton
                    label="Дата"
                    column="created_at"
                    :sort-by="sortBy"
                    :sort-direction="sortDirection"
                    @sort="setSort"
                  />
                </th>
                <th class="px-4 py-3 text-right">Действия</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
              <template v-for="vacancy in filteredVacancies" :key="vacancy.id">
                <tr v-if="editId !== vacancy.id" class="transition hover:bg-blue-50/50">
                  <td class="px-4 py-3 align-top">
                    <div class="max-w-[260px] font-semibold text-gray-900 whitespace-normal break-words">
                      {{ vacancy.title }}
                    </div>
                    <div class="mt-1 text-xs text-gray-400">ID: {{ vacancy.id }}</div>
                  </td>
                  <td class="px-4 py-3 align-top">
                    <span class="inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-[#005eb8]">
                      {{ vacancyTypeLabel(vacancy.type) }}
                    </span>
                  </td>
                  <td class="px-4 py-3 align-top text-gray-600">
                    <div class="max-w-[280px] whitespace-normal break-words">
                      {{ vacancyDepartmentName(vacancy) || 'Подразделение не указано' }}
                    </div>
                    <div class="mt-1 max-w-[280px] whitespace-normal break-words text-xs text-gray-400">
                      {{ vacancy.position?.name || 'Должность не указана' }}
                    </div>
                  </td>
                  <td class="px-4 py-3 align-top text-gray-600">
                    <div class="max-w-[360px] line-clamp-3 whitespace-normal break-words">
                      {{ vacancy.description || 'Описание не указано' }}
                    </div>
                  </td>
                  <td class="whitespace-nowrap px-4 py-3 align-top text-gray-600">
                    {{ formatDate(vacancy.created_at) }}
                  </td>
                  <td class="whitespace-nowrap px-4 py-3 align-top text-right">
                    <div class="flex justify-end gap-3">
                      <button
                        type="button"
                        class="font-semibold text-[#005eb8] hover:text-blue-700"
                        @click="startEdit(vacancy)"
                      >
                        Редактировать
                      </button>
                      <button
                        type="button"
                        class="font-semibold text-red-600 hover:text-red-700"
                        @click="deleteVacancy(vacancy.id)"
                      >
                        Удалить
                      </button>
                    </div>
                  </td>
                </tr>

                <tr v-else>
                  <td colspan="6" class="bg-slate-50 px-4 py-4">
                    <form class="space-y-4" @submit.prevent="updateVacancy(vacancy.id)">
                      <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                        <label class="space-y-2">
                          <span class="text-sm font-medium text-gray-700">Название вакансии</span>
                          <input
                            v-model="editVacancy.title"
                            type="text"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
                          />
                        </label>

                        <label class="space-y-2">
                          <span class="text-sm font-medium text-gray-700">Тип</span>
                          <input
                            :value="vacancyTypeLabel(editVacancy.type)"
                            disabled
                            class="w-full rounded-lg border border-gray-300 bg-gray-100 px-4 py-2 text-gray-600"
                          />
                        </label>

                        <label class="space-y-2">
                          <span class="text-sm font-medium text-gray-700">Подразделение</span>
                          <select
                            v-model="editVacancy.department_id"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
                            @change="editVacancy.position_id = ''"
                          >
                            <option disabled value="">Выберите подразделение</option>
                            <option
                              v-for="department in departmentOptions"
                              :key="department.id"
                              :value="department.id"
                            >
                              {{ department.full_name }}
                            </option>
                          </select>
                        </label>

                        <label class="space-y-2">
                          <span class="text-sm font-medium text-gray-700">Должность</span>
                          <select
                            v-model="editVacancy.position_id"
                            :disabled="!editVacancy.department_id"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100 disabled:bg-gray-100 disabled:text-gray-500"
                          >
                            <option disabled value="">Выберите должность</option>
                            <option
                              v-for="position in filteredEditPositions"
                              :key="position.id"
                              :value="position.id"
                            >
                              {{ positionLabel(position) }}
                            </option>
                          </select>
                        </label>

                        <label class="space-y-2 xl:col-span-2">
                          <span class="text-sm font-medium text-gray-700">Описание</span>
                          <textarea
                            v-model="editVacancy.description"
                            rows="4"
                            class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
                          ></textarea>
                        </label>
                      </div>

                      <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button
                          type="button"
                          class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-100"
                          @click="cancelEdit"
                        >
                          Отмена
                        </button>
                        <button
                          type="submit"
                          class="rounded-lg bg-[#005eb8] px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                        >
                          Сохранить
                        </button>
                      </div>
                    </form>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
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
import TableSortButton from '../components/TableSortButton.vue';
import {
  vacancyTypeLabel,
  vacancyTypeTabs as vacancyTypeTabValues,
} from '../utils/vacancyTypes';
import { collectDepartmentSubtreeIds, decorateDepartments } from '../utils/departments';
import { sortRows } from '../utils/tableSort';

const vacancies = ref([]);
const positions = ref([]);
const departments = ref([]);
const loading = ref(true);
const search = ref('');
const typeFilter = ref('');
const departmentFilter = ref('');
const sortBy = ref('created_at');
const sortDirection = ref('desc');
const editId = ref(null);
const editVacancy = ref({ title: '', description: '', type: '', department_id: '', position_id: '' });

const errorText = (error) => error?.response?.data?.message || 'Ошибка. Попробуйте снова.';

const departmentOptions = computed(() => decorateDepartments(departments.value));
const vacancyTypeOptions = computed(() => vacancyTypeTabValues.map((type) => ({
  value: type,
  label: vacancyTypeLabel(type),
})));

const departmentName = (departmentId) => {
  const department = departmentOptions.value.find((item) => item.id === Number(departmentId));
  return department?.full_name || department?.name || '';
};

const positionLabel = (position) => {
  const prefix = departmentName(position.department_id);
  return prefix ? `${prefix} - ${position.name}` : position.name;
};

const vacancyDepartmentName = (vacancy) => departmentName(vacancy?.position?.department_id);

const filteredVacancies = computed(() => {
  const q = search.value.trim().toLowerCase();
  const subtreeIds = departmentFilter.value
    ? collectDepartmentSubtreeIds(departmentFilter.value, departments.value)
    : null;

  const rows = vacancies.value.filter((vacancy) => {
    if (typeFilter.value && vacancy.type !== typeFilter.value) return false;

    const vacancyDepartmentId = Number(vacancy?.position?.department_id || 0);
    if (subtreeIds && !subtreeIds.has(vacancyDepartmentId)) return false;

    if (!q) return true;
    const values = [
      vacancy.title,
      vacancy.description,
      vacancyTypeLabel(vacancy.type),
      vacancyDepartmentName(vacancy),
      vacancy.position?.name,
    ].map((value) => String(value || '').toLowerCase());

    return values.some((value) => value.includes(q));
  });

  return sortRows(rows, vacancySortValue, sortDirection.value);
});

const vacancySortValue = (vacancy) => {
  if (sortBy.value === 'title') return vacancy.title || '';
  if (sortBy.value === 'type') return vacancyTypeLabel(vacancy.type);
  if (sortBy.value === 'department') return `${vacancyDepartmentName(vacancy)} ${vacancy.position?.name || ''}`;
  if (sortBy.value === 'description') return vacancy.description || '';
  if (sortBy.value === 'created_at') return Date.parse(vacancy.created_at || '') || 0;
  return vacancy.id || 0;
};

const setSort = (column) => {
  if (sortBy.value === column) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    return;
  }

  sortBy.value = column;
  sortDirection.value = column === 'created_at' ? 'desc' : 'asc';
};

const formatDate = (value) => {
  if (!value) return 'Не указана';

  return new Date(value).toLocaleDateString('ru-RU', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

const fetchVacancies = async () => {
  loading.value = true;
  try {
    const response = await axios.get('/api/admin/vacancies');
    vacancies.value = response.data;
  } catch (error) {
    alert(errorText(error));
  } finally {
    loading.value = false;
  }
};

const fetchPositions = async () => {
  try {
    const response = await axios.get('/api/admin/positions');
    positions.value = response.data;
  } catch (error) {
    alert(errorText(error));
  }
};

const fetchDepartments = async () => {
  try {
    const response = await axios.get('/api/admin/departments');
    departments.value = response.data;
  } catch (error) {
    alert(errorText(error));
  }
};

const filteredEditPositions = computed(() => {
  if (!editVacancy.value.department_id) return [];
  return positions.value.filter((position) => position.department_id === Number(editVacancy.value.department_id));
});

const startEdit = (vacancy) => {
  editId.value = vacancy.id;
  editVacancy.value = {
    title: vacancy.title,
    description: vacancy.description,
    type: vacancy.type,
    department_id: vacancy.position?.department_id || '',
    position_id: vacancy.position_id || '',
  };
};

const cancelEdit = () => {
  editId.value = null;
  editVacancy.value = { title: '', description: '', type: '', department_id: '', position_id: '' };
};

const updateVacancy = async (id) => {
  if (!editVacancy.value.title.trim()) {
    alert('Введите название вакансии.');
    return;
  }

  if (!editVacancy.value.department_id) {
    alert('Выберите подразделение.');
    return;
  }

  if (!editVacancy.value.position_id) {
    alert('Выберите должность.');
    return;
  }

  try {
    await axios.put(`/api/admin/vacancies/${id}`, {
      ...editVacancy.value,
      position_id: Number(editVacancy.value.position_id),
    });
    cancelEdit();
    await fetchVacancies();
  } catch (error) {
    alert(errorText(error));
  }
};

const deleteVacancy = async (id) => {
  if (!confirm('Удалить вакансию?')) return;
  try {
    await axios.delete(`/api/admin/vacancies/${id}`);
    await fetchVacancies();
  } catch (error) {
    alert(errorText(error));
  }
};

onMounted(async () => {
  await Promise.all([fetchDepartments(), fetchPositions(), fetchVacancies()]);
});
</script>
