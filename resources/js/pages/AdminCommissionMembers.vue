<template>
  <Layout>
    <div class="max-w-6xl mx-auto py-8 px-4 space-y-6">
      <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl md:text-3xl font-bold text-[#005eb8]">Члены комиссии</h1>
      </div>

      <section class="bg-white rounded-xl shadow border border-gray-100 p-4 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <input
            v-model="memberSearch"
            type="text"
            placeholder="Поиск по действующим членам комиссии"
            class="w-full border border-gray-300 rounded-lg px-4 py-2"
          />
          <input
            v-model="candidateSearch"
            type="text"
            placeholder="Поиск пользователей для добавления"
            class="w-full border border-gray-300 rounded-lg px-4 py-2"
          />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="border border-gray-200 rounded-lg p-3 space-y-3">
            <h2 class="font-semibold text-gray-800">Текущий состав комиссии</h2>

            <div v-if="loadingMembers" class="text-sm text-gray-500">Загрузка...</div>
            <div v-else-if="members.length === 0" class="text-sm text-gray-500">Состав комиссии пуст.</div>
            <div v-else class="space-y-2">
              <div
                v-for="member in members"
                :key="member.id"
                class="flex items-center justify-between gap-3 border border-gray-100 rounded-lg p-2"
              >
                <div class="text-sm">
                  <div class="font-medium text-gray-900">{{ member.user?.name }}</div>
                  <div class="text-gray-500">{{ member.user?.email }}</div>
                </div>
                <button
                  @click="removeMember(member.user_id)"
                  class="text-red-600 hover:text-red-700 text-sm font-semibold cursor-pointer"
                >
                  Удалить
                </button>
              </div>
            </div>
          </div>

          <div class="border border-gray-200 rounded-lg p-3 space-y-3">
            <h2 class="font-semibold text-gray-800">Добавить в комиссию</h2>

            <div v-if="loadingCandidates" class="text-sm text-gray-500">Загрузка...</div>
            <div v-else-if="candidates.length === 0" class="text-sm text-gray-500">Пользователи не найдены.</div>
            <div v-else class="space-y-2 max-h-96 overflow-auto pr-1">
              <div
                v-for="user in candidates"
                :key="user.id"
                class="flex items-center justify-between gap-3 border border-gray-100 rounded-lg p-2"
              >
                <div class="text-sm">
                  <div class="font-medium text-gray-900">{{ user.name }}</div>
                  <div class="text-gray-500">{{ user.email }}</div>
                </div>
                <button
                  @click="addMember(user.id)"
                  class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-3 py-1.5 rounded-lg transition cursor-pointer"
                >
                  Добавить
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
import { onMounted, onUnmounted, ref, watch } from 'vue';
import axios from 'axios';
import Layout from '../components/Layout.vue';

const members = ref([]);
const candidates = ref([]);
const loadingMembers = ref(false);
const loadingCandidates = ref(false);
const memberSearch = ref('');
const candidateSearch = ref('');

const errorText = (error) => error?.response?.data?.message || 'Ошибка. Попробуйте снова.';

const fetchMembers = async (q = '') => {
  loadingMembers.value = true;
  try {
    const response = await axios.get('/api/admin/commission-members', { params: { q } });
    members.value = response.data;
  } catch (error) {
    alert(errorText(error));
  } finally {
    loadingMembers.value = false;
  }
};

const fetchCandidates = async (q = '') => {
  loadingCandidates.value = true;
  try {
    const response = await axios.get('/api/admin/commission-candidates', { params: { q } });
    candidates.value = response.data;
  } catch (error) {
    alert(errorText(error));
  } finally {
    loadingCandidates.value = false;
  }
};

const addMember = async (userId) => {
  try {
    await axios.post('/api/admin/commission-members', { user_id: userId });
    await Promise.all([fetchMembers(memberSearch.value.trim()), fetchCandidates(candidateSearch.value.trim())]);
  } catch (error) {
    alert(errorText(error));
  }
};

const removeMember = async (userId) => {
  if (!confirm('Удалить пользователя из комиссии?')) return;

  try {
    await axios.delete(`/api/admin/commission-members/${userId}`);
    await Promise.all([fetchMembers(memberSearch.value.trim()), fetchCandidates(candidateSearch.value.trim())]);
  } catch (error) {
    alert(errorText(error));
  }
};

let memberTimer = null;
watch(memberSearch, (value) => {
  if (memberTimer) clearTimeout(memberTimer);
  memberTimer = setTimeout(() => fetchMembers(value.trim()), 300);
});

let candidateTimer = null;
watch(candidateSearch, (value) => {
  if (candidateTimer) clearTimeout(candidateTimer);
  candidateTimer = setTimeout(() => fetchCandidates(value.trim()), 300);
});

onUnmounted(() => {
  if (memberTimer) clearTimeout(memberTimer);
  if (candidateTimer) clearTimeout(candidateTimer);
});

onMounted(async () => {
  await Promise.all([fetchMembers(), fetchCandidates()]);
});
</script>

