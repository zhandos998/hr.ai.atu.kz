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
          placeholder="Поиск по департаменту, подотделу, должности, обязанностям и требованиям"
          class="w-full border border-gray-300 rounded-lg px-4 py-2"
        />
      </div>

      <div v-if="loading" class="text-center text-gray-500">Загрузка...</div>
      <div v-else-if="filteredTree.length === 0" class="text-center text-gray-600">Ничего не найдено.</div>

      <div v-else class="space-y-3">
        <DepartmentStructureNode
          v-for="department in filteredTree"
          :key="department.id"
          :department="department"
          :expanded="expanded[department.id] || forceExpand"
          :expanded-map="expanded"
          :force-expand="forceExpand"
          @toggle="toggleDepartment"
        />
      </div>
    </div>
  </Layout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import Layout from '../components/Layout.vue';
import DepartmentStructureNode from '../components/DepartmentStructureNode.vue';
import { buildDepartmentTree, decorateDepartments } from '../utils/departments';

const departments = ref([]);
const loading = ref(true);
const expanded = ref({});
const search = ref('');

const errorText = (error) => error?.response?.data?.message || 'Ошибка. Попробуйте снова.';

const departmentTree = computed(() => buildDepartmentTree(decorateDepartments(departments.value)));
const forceExpand = computed(() => search.value.trim() !== '');

const filterTree = (items, q) => {
  if (!q) return items;

  return items
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

      const filteredChildren = filterTree(department.children || [], q);

      if (!departmentMatched && filteredPositions.length === 0 && filteredChildren.length === 0) {
        return null;
      }

      return {
        ...department,
        positions: departmentMatched ? department.positions : filteredPositions,
        children: filteredChildren,
      };
    })
    .filter(Boolean);
};

const filteredTree = computed(() => filterTree(departmentTree.value, search.value.trim().toLowerCase()));

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
