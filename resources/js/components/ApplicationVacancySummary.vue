<template>
  <div>
    <h2 class="text-lg font-semibold text-[#005eb8]">Вакансия</h2>
    <p class="text-base font-semibold text-gray-900">{{ vacancyTitle }}</p>
    <p class="text-sm text-gray-600">Тип: {{ vacancyTypeLabel(application?.vacancy?.type) }}</p>
    <p v-if="application?.vacancy?.position?.name" class="text-sm text-gray-600">
      Должность: {{ application.vacancy.position.name }}
    </p>
    <p v-if="application?.vacancy?.position?.department?.name" class="text-sm text-gray-600">
      Подразделение: {{ application.vacancy.position.department.name }}
    </p>
    <p v-if="application?.pps_profile?.faculty_name" class="text-sm text-gray-600">
      Факультет: {{ application.pps_profile.faculty_name }}
    </p>
    <p v-if="application?.pps_profile?.department_name" class="text-sm text-gray-600">
      Кафедра: {{ application.pps_profile.department_name }}
    </p>
    <TeacherAuditLink
      v-if="isPpsApplication"
      :application="application"
      class="mt-3"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue';
import TeacherAuditLink from './TeacherAuditLink.vue';
import { vacancyTypeLabel } from '../utils/vacancyTypes';

const props = defineProps({
  application: {
    type: Object,
    required: true,
  },
});

const vacancyTitle = computed(() => props.application?.vacancy?.title || props.application?.pps_profile?.desired_position || 'Вакансия не указана');
const isPpsApplication = computed(() => props.application?.vacancy?.type === 'pps');
</script>
