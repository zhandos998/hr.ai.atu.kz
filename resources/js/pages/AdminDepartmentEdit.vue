<template>
  <Layout>
    <div class="max-w-3xl mx-auto py-8 px-4 space-y-6">
      <div class="flex justify-end">
        <router-link
          to="/admin/departments"
          class="border border-gray-300 text-gray-700 hover:bg-gray-100 font-semibold py-2 px-4 rounded-lg transition"
        >
          {{ t('common.back') }}
        </router-link>
      </div>

      <div v-if="loading" class="text-center text-gray-500">{{ t('common.loading') }}</div>

      <section v-else class="bg-white rounded-xl shadow border border-gray-100 p-4 space-y-4">
        <form @submit.prevent="updateDepartment" class="space-y-3">
          <label class="space-y-2 block">
            <span class="text-sm font-medium text-gray-700">{{ t('admin.departmentForm.parent') }}</span>
            <select
              v-model="form.parent_id"
              class="w-full border border-gray-300 rounded-lg px-4 py-2"
            >
              <option value="">{{ t('admin.departmentForm.noParent') }}</option>
              <option
                v-for="department in availableParentOptions"
                :key="department.id"
                :value="department.id"
              >
                {{ department.full_name }}
              </option>
            </select>
          </label>

          <label class="space-y-2 block">
            <span class="text-sm font-medium text-gray-700">{{ t('admin.departmentForm.name') }}</span>
            <input
              v-model="form.name"
              type="text"
              :placeholder="t('admin.departmentForm.namePlaceholder')"
              class="w-full border border-gray-300 rounded-lg px-4 py-2"
            />
          </label>

          <label class="space-y-2 block">
            <span class="text-sm font-medium text-gray-700">{{ t('admin.departmentForm.description') }}</span>
            <textarea
              v-model="form.description"
              rows="4"
              :placeholder="t('admin.departmentForm.descriptionPlaceholder')"
              class="w-full border border-gray-300 rounded-lg px-4 py-2"
            ></textarea>
          </label>

          <button
            type="submit"
            :disabled="submitting"
            class="bg-[#005eb8] hover:bg-blue-700 text-white font-semibold py-2 rounded-lg w-full transition cursor-pointer disabled:opacity-70"
          >
            {{ submitting ? t('common.saving') : t('admin.departmentForm.saveChanges') }}
          </button>
        </form>
      </section>
    </div>
  </Layout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import Layout from '../components/Layout.vue';
import { collectDepartmentSubtreeIds, decorateDepartments } from '../utils/departments';
import { useI18n } from '../i18n';

const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const loading = ref(true);
const submitting = ref(false);
const departments = ref([]);
const form = ref({ name: '', description: '', parent_id: '' });

const departmentId = computed(() => Number(route.params.id));
const decoratedDepartments = computed(() => decorateDepartments(departments.value));
const currentDepartment = computed(() => decoratedDepartments.value.find((department) => Number(department.id) === departmentId.value) || null);
const errorText = (error) => error?.response?.data?.message || t('validation.genericError');

const availableParentOptions = computed(() => {
  const excludedIds = collectDepartmentSubtreeIds(departmentId.value, departments.value);
  return decoratedDepartments.value.filter((department) => !excludedIds.has(Number(department.id)));
});

const fetchDepartments = async () => {
  loading.value = true;

  try {
    const response = await axios.get('/api/admin/departments');
    departments.value = response.data;

    if (!currentDepartment.value) {
      alert(t('admin.departmentForm.notFound'));
      router.push('/admin/departments');
      return;
    }

    form.value = {
      name: currentDepartment.value.name || '',
      description: currentDepartment.value.description || '',
      parent_id: currentDepartment.value.parent_id || '',
    };
  } catch (error) {
    alert(errorText(error));
  } finally {
    loading.value = false;
  }
};

const updateDepartment = async () => {
  if (!form.value.name.trim()) {
    alert(t('admin.departmentForm.validationName'));
    return;
  }

  submitting.value = true;

  try {
    await axios.put(`/api/admin/departments/${departmentId.value}`, form.value);
    alert(t('admin.departmentForm.updated'));
    router.push('/admin/departments');
  } catch (error) {
    alert(errorText(error));
  } finally {
    submitting.value = false;
  }
};

onMounted(fetchDepartments);
</script>
