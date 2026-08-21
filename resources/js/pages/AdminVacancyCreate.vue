<template>
  <Layout>
    <div class="max-w-3xl mx-auto py-8 px-4 space-y-6">
      <div class="flex justify-end">
        <router-link
          to="/admin/vacancies"
          class="border border-gray-300 text-gray-700 hover:bg-gray-100 font-semibold py-2 px-4 rounded-lg transition"
        >
          {{ t('common.back') }}
        </router-link>
      </div>

      <section class="bg-white rounded-xl shadow border border-gray-100 p-4">
        <form @submit.prevent="createVacancy" class="space-y-4">
          <label class="space-y-2 block">
            <span class="text-sm font-medium text-gray-700">{{ t('admin.vacancies.title') }}</span>
            <input
              v-model="form.title"
              type="text"
              :placeholder="t('admin.vacancies.titlePlaceholder')"
              class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-[#005eb8] transition"
            />
          </label>

          <label class="space-y-2 block">
            <span class="text-sm font-medium text-gray-700">{{ t('admin.vacancies.description') }}</span>
            <textarea
              v-model="form.description"
              rows="5"
              :placeholder="t('admin.vacancies.descriptionPlaceholder')"
              class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-[#005eb8] transition"
            ></textarea>
          </label>

          <label class="space-y-2 block">
            <span class="text-sm font-medium text-gray-700">{{ t('admin.vacancies.type') }}</span>
            <select
              v-model="form.type"
              class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-[#005eb8] transition"
            >
              <option disabled value="">{{ t('admin.vacancies.chooseType') }}</option>
              <option value="staff">АУП</option>
              <option value="pps">ППС</option>
            </select>
          </label>

          <button
            type="submit"
            :disabled="submitting"
            class="bg-[#005eb8] hover:bg-blue-700 text-white font-semibold py-2 rounded-lg w-full transition cursor-pointer disabled:opacity-70"
          >
            {{ submitting ? t('common.saving') : t('admin.vacancies.saveVacancy') }}
          </button>
        </form>
      </section>
    </div>
  </Layout>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import Layout from '../components/Layout.vue';
import { useI18n } from '../i18n';

const router = useRouter();
const { t } = useI18n();
const submitting = ref(false);
const form = ref({ title: '', description: '', type: '' });

const errorText = (error) => error?.response?.data?.message || t('validation.genericError');

const createVacancy = async () => {
  if (!form.value.title.trim()) {
    alert(t('admin.vacancies.validationTitle'));
    return;
  }

  if (!form.value.description.trim()) {
    alert(t('admin.vacancies.validationDescription'));
    return;
  }

  if (!form.value.type) {
    alert(t('admin.vacancies.validationType'));
    return;
  }

  submitting.value = true;
  try {
    await axios.post('/api/admin/vacancies', {
      title: form.value.title,
      description: form.value.description,
      type: form.value.type,
    });
    alert(t('admin.vacancies.created'));
    router.push('/admin/vacancies');
  } catch (error) {
    alert(errorText(error));
  } finally {
    submitting.value = false;
  }
};
</script>
