<template>
  <div class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden">
    <button
      type="button"
      class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-gray-50 transition cursor-pointer"
      @click="$emit('toggle', department.id)"
    >
      <div>
        <div class="font-semibold text-[#005eb8]">{{ department.name }}</div>
        <div class="text-xs text-gray-500">
          Должностей: {{ department.positions?.length || 0 }} • Подотделов: {{ department.children?.length || 0 }}
        </div>
      </div>
      <span class="text-[#005eb8] text-lg">{{ expanded ? '−' : '+' }}</span>
    </button>

    <div v-if="expanded" class="border-t border-gray-100 p-4 space-y-4">
      <p v-if="department.description" class="text-sm text-gray-700 whitespace-pre-line">
        {{ department.description }}
      </p>

      <div v-if="department.positions?.length" class="space-y-2">
        <div class="text-sm font-semibold text-gray-700">Должности</div>
        <ul class="space-y-2">
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
      <div v-else class="text-sm text-gray-500">В этом подразделении пока нет должностей.</div>

      <div v-if="department.children?.length" class="space-y-3 pl-4 border-l-2 border-blue-100">
        <div class="text-sm font-semibold text-gray-700">Подотделы</div>
        <DepartmentStructureNode
          v-for="child in department.children"
          :key="child.id"
          :department="child"
          :expanded="expandedMap[child.id] || forceExpand"
          :expanded-map="expandedMap"
          :force-expand="forceExpand"
          @toggle="$emit('toggle', $event)"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
defineOptions({
  name: 'DepartmentStructureNode',
});

defineProps({
  department: {
    type: Object,
    required: true,
  },
  expanded: {
    type: Boolean,
    default: false,
  },
  expandedMap: {
    type: Object,
    required: true,
  },
  forceExpand: {
    type: Boolean,
    default: false,
  },
});

defineEmits(['toggle']);
</script>
