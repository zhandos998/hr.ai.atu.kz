<template>
  <button
    type="button"
    :class="[
      'group inline-flex items-center gap-1 font-semibold uppercase tracking-wide transition hover:text-[#005eb8]',
      align === 'right' ? 'w-full justify-end text-right' : 'text-left',
    ]"
    :title="`Сортировать: ${label}`"
    @click="$emit('sort', column)"
  >
    <span>{{ label }}</span>
    <span
      :class="[
        'inline-flex h-4 min-w-4 items-center justify-center rounded text-[10px] leading-none',
        isActive ? 'bg-blue-100 text-[#005eb8]' : 'text-gray-300 group-hover:text-[#005eb8]',
      ]"
    >
      {{ indicator }}
    </span>
  </button>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  label: {
    type: String,
    required: true,
  },
  column: {
    type: String,
    required: true,
  },
  sortBy: {
    type: String,
    required: true,
  },
  sortDirection: {
    type: String,
    required: true,
  },
  align: {
    type: String,
    default: 'left',
  },
});

defineEmits(['sort']);

const isActive = computed(() => props.sortBy === props.column);
const indicator = computed(() => {
  if (!isActive.value) return '↕';
  return props.sortDirection === 'asc' ? '↑' : '↓';
});
</script>
