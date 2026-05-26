<template>
  <Layout>
    <div class="max-w-7xl mx-auto py-8 px-4 space-y-6">
      <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
          <h1 class="text-2xl md:text-3xl font-bold text-[#005eb8]">Департаменты и подотделы</h1>
          <p class="text-sm text-gray-500">Структура подразделений, факультетов, кафедр и связанных должностей.</p>
        </div>
        <router-link
          to="/admin/departments/create"
          class="inline-flex items-center justify-center rounded-xl bg-[#005eb8] px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
        >
          Добавить департамент
        </router-link>
      </div>

      <section class="space-y-4">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
          <div class="text-sm text-gray-500 md:self-center">
            Показано: <span class="font-semibold text-gray-700">{{ filteredDepartments.length }}</span>
            <span class="text-gray-400"> / {{ departments.length }}</span>
          </div>
          <input
            v-model="search"
            type="text"
            placeholder="Поиск по названию или описанию"
            class="w-full rounded-xl border border-gray-300 px-4 py-2 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
          />
          <select
            v-model="typeFilter"
            class="w-full rounded-xl border border-gray-300 px-4 py-2 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
          >
            <option value="">Все типы</option>
            <option value="faculty">Факультеты</option>
            <option value="department">Кафедры</option>
            <option value="root">Только верхний уровень</option>
            <option value="child">Только подотделы</option>
          </select>
          <select
            v-model="parentFilter"
            class="w-full rounded-xl border border-gray-300 px-4 py-2 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
          >
            <option value="">Все родители</option>
            <option value="none">Без родителя</option>
            <option
              v-for="department in decoratedDepartments"
              :key="department.id"
              :value="department.id"
            >
              {{ department.full_name }}
            </option>
          </select>
        </div>

        <div v-if="loading" class="text-center text-gray-500">Загрузка...</div>

        <div
          v-else-if="filteredDepartments.length === 0"
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
                      label="Подразделение"
                      column="name"
                      :sort-by="sortBy"
                      :sort-direction="sortDirection"
                      @sort="setSort"
                    />
                  </th>
                  <th class="px-4 py-3">
                    <TableSortButton
                      label="Родитель"
                      column="parent"
                      :sort-by="sortBy"
                      :sort-direction="sortDirection"
                      @sort="setSort"
                    />
                  </th>
                  <th class="px-4 py-3">
                    <TableSortButton
                      label="Тип / описание"
                      column="type"
                      :sort-by="sortBy"
                      :sort-direction="sortDirection"
                      @sort="setSort"
                    />
                  </th>
                  <th class="px-4 py-3">
                    <TableSortButton
                      label="Должности"
                      column="positions"
                      :sort-by="sortBy"
                      :sort-direction="sortDirection"
                      @sort="setSort"
                    />
                  </th>
                  <th class="px-4 py-3">
                    <TableSortButton
                      label="Подотделы"
                      column="children"
                      :sort-by="sortBy"
                      :sort-direction="sortDirection"
                      @sort="setSort"
                    />
                  </th>
                  <th class="px-4 py-3 text-right">Действия</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 bg-white">
                <tr
                  v-for="department in filteredDepartments"
                  :key="department.id"
                  class="transition hover:bg-blue-50/50"
                >
                  <td class="px-4 py-3 align-top">
                    <div
                      class="max-w-[360px]"
                      :style="{ paddingLeft: `${department.level * 20}px` }"
                    >
                      <div class="font-semibold text-gray-900 whitespace-normal break-words">
                        {{ department.name }}
                      </div>
                      <div class="mt-1 text-xs text-gray-400 whitespace-normal break-words">
                        {{ department.full_name }}
                      </div>
                      <div class="mt-1 text-xs text-gray-400">ID: {{ department.id }}</div>
                    </div>
                  </td>
                  <td class="px-4 py-3 align-top text-gray-600">
                    <div class="max-w-[260px] whitespace-normal break-words">
                      {{ parentDepartmentName(department) || 'Нет' }}
                    </div>
                  </td>
                  <td class="px-4 py-3 align-top">
                    <div class="space-y-2">
                      <span :class="departmentTypeClass(department)">
                        {{ departmentTypeLabel(department) }}
                      </span>
                      <div
                        v-if="department.description"
                        class="max-w-[320px] whitespace-normal break-words text-gray-600"
                      >
                        {{ department.description }}
                      </div>
                    </div>
                  </td>
                  <td class="px-4 py-3 align-top text-gray-700">
                    {{ department.positions?.length || 0 }}
                  </td>
                  <td class="px-4 py-3 align-top text-gray-700">
                    {{ department.children?.length || 0 }}
                  </td>
                  <td class="whitespace-nowrap px-4 py-3 align-top text-right">
                    <div class="flex justify-end gap-3">
                      <router-link
                        :to="`/admin/departments/${department.id}/edit`"
                        class="font-semibold text-[#005eb8] hover:text-blue-700"
                      >
                        Редактировать
                      </router-link>
                      <button
                        type="button"
                        class="font-semibold text-red-600 hover:text-red-700"
                        @click="deleteDepartment(department.id)"
                      >
                        Удалить
                      </button>
                    </div>
                  </td>
                </tr>
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
  buildDepartmentTree,
  decorateDepartments,
  flattenDepartmentTree,
} from '../utils/departments';
import { sortRows } from '../utils/tableSort';

const departments = ref([]);
const loading = ref(true);
const search = ref('');
const typeFilter = ref('');
const parentFilter = ref('');
const sortBy = ref('name');
const sortDirection = ref('asc');

const errorText = (error) => error?.response?.data?.message || 'Ошибка. Попробуйте снова.';

const decoratedDepartments = computed(() => decorateDepartments(departments.value));
const departmentsById = computed(() => new Map(decoratedDepartments.value.map((department) => [department.id, department])));
const departmentTree = computed(() => buildDepartmentTree(decoratedDepartments.value));
const flatDepartments = computed(() => flattenDepartmentTree(departmentTree.value));

const filteredDepartments = computed(() => {
  const q = search.value.trim().toLowerCase();

  const rows = flatDepartments.value.filter((department) => {
    if (typeFilter.value && departmentTypeValue(department) !== typeFilter.value) return false;
    if (parentFilter.value === 'none' && department.parent_id) return false;
    if (parentFilter.value && parentFilter.value !== 'none' && Number(department.parent_id) !== Number(parentFilter.value)) return false;

    if (!q) return true;
    const fullName = (department.full_name || '').toLowerCase();
    const description = (department.description || '').toLowerCase();
    const parentName = parentDepartmentName(department).toLowerCase();
    return [fullName, description, parentName].some((value) => value.includes(q));
  });

  return sortRows(rows, departmentSortValue, sortDirection.value);
});

const parentDepartmentName = (department) => {
  if (!department?.parent_id) return '';
  return departmentsById.value.get(Number(department.parent_id))?.full_name || '';
};

const departmentTypeValue = (department) => {
  const label = departmentTypeLabel(department).toLowerCase();
  if (label === 'факультет') return 'faculty';
  if (label === 'кафедра') return 'department';
  return department?.parent_id ? 'child' : 'root';
};

const departmentSortValue = (department) => {
  if (sortBy.value === 'name') return department.full_name || department.name || '';
  if (sortBy.value === 'parent') return parentDepartmentName(department) || '';
  if (sortBy.value === 'type') return departmentTypeLabel(department);
  if (sortBy.value === 'positions') return department.positions?.length || 0;
  if (sortBy.value === 'children') return department.children?.length || 0;
  return department.id || 0;
};

const setSort = (column) => {
  if (sortBy.value === column) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    return;
  }

  sortBy.value = column;
  sortDirection.value = ['positions', 'children'].includes(column) ? 'desc' : 'asc';
};

const departmentTypeLabel = (department) => {
  const description = String(department?.description || '').trim();
  if (description) return description;
  return department?.parent_id ? 'Подотдел' : 'Подразделение';
};

const departmentTypeClass = (department) => {
  const label = departmentTypeLabel(department).toLowerCase();
  const base = 'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold';

  if (label === 'факультет') return `${base} bg-indigo-50 text-indigo-700`;
  if (label === 'кафедра') return `${base} bg-emerald-50 text-emerald-700`;
  if (department?.parent_id) return `${base} bg-blue-50 text-blue-700`;
  return `${base} bg-slate-100 text-slate-700`;
};

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
