<template>
  <Layout>
    <template #adminHeaderActions>
      <div class="text-sm text-gray-500">
        {{ applicationArchiveMode === 'archived' ? t('admin.actions.archivedApplications') : t('admin.actions.totalApplications') }}:
        <span class="font-semibold text-gray-700">{{ applicationArchiveTotal }}</span>
      </div>
      <button
        v-if="applicationArchiveMode === 'active'"
        type="button"
        class="inline-flex items-center rounded-md bg-[#2f5fb8] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#244f9d]"
        @click="toggleCreateForm"
      >
        {{ showCreateForm ? t('admin.actions.hideForm') : t('admin.actions.addApplication') }}
      </button>
    </template>

    <div class="max-w-7xl mx-auto py-8 px-4 space-y-6">
      <section
        v-if="showCreateForm && applicationArchiveMode === 'active'"
        class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm space-y-4"
      >
        <div>
          <h2 class="text-xl font-semibold text-[#005eb8]">{{ t('admin.applications.createTitle') }}</h2>
          <p class="text-sm text-gray-500">{{ t('admin.applications.createDescription') }}</p>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">{{ t('admin.applications.candidateName') }}</label>
            <input
              v-model="createForm.full_name"
              type="text"
              :placeholder="t('admin.applications.candidatePlaceholder')"
              class="w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
            />
          </div>

          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">{{ t('admin.applications.applicationType') }}</label>
            <select
              v-model="createForm.vacancy_type"
              class="w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
            >
              <option
                v-for="type in vacancyTypeOptions"
                :key="type.value"
                :value="type.value"
              >
                {{ type.label }}
              </option>
            </select>
          </div>

          <div v-if="createForm.vacancy_type === 'pps'" class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">{{ t('admin.applications.faculty') }}</label>
            <select
              v-model="createForm.faculty_name"
              :disabled="facultyOptions.length === 0"
              class="w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100 disabled:bg-gray-100 disabled:text-gray-500"
            >
              <option value="">{{ t('admin.applications.chooseFaculty') }}</option>
              <option
                v-for="faculty in facultyOptions"
                :key="faculty.id"
                :value="faculty.name"
              >
                {{ faculty.name }}
              </option>
            </select>
            <p v-if="facultyOptions.length === 0" class="text-xs text-amber-700">
              {{ t('admin.applications.noFaculties') }}
            </p>
          </div>

          <div v-if="createForm.vacancy_type === 'pps'" class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">{{ t('admin.applications.department') }}</label>
            <select
              v-model="createForm.department_name"
              :disabled="!createForm.faculty_name || departmentOptions.length === 0"
              class="w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100 disabled:bg-gray-100 disabled:text-gray-500"
            >
              <option value="">{{ t('admin.applications.chooseDepartment') }}</option>
              <option
                v-for="department in departmentOptions"
                :key="department.id"
                :value="department.name"
              >
                {{ department.name }}
              </option>
            </select>
            <p v-if="createForm.faculty_name && departmentOptions.length === 0" class="text-xs text-amber-700">
              {{ t('admin.applications.noDepartmentsForFaculty') }}
            </p>
          </div>

          <div v-if="createForm.vacancy_type === 'staff'" class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">{{ t('admin.applications.staffDepartment') }}</label>
            <div class="relative">
              <input
                v-model="staffDepartmentSearch"
                type="text"
                :placeholder="t('admin.applications.staffDepartmentPlaceholder')"
                :disabled="staffDepartmentOptions.length === 0"
                autocomplete="off"
                class="w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100 disabled:bg-gray-100 disabled:text-gray-500"
                @focus="staffDepartmentDropdownOpen = true"
                @input="handleStaffDepartmentInput"
                @keydown.down.prevent="focusStaffDepartmentSuggestion"
                @blur="closeStaffDepartmentDropdown"
              />
              <div
                v-if="staffDepartmentDropdownOpen && staffDepartmentSuggestions.length"
                class="absolute z-30 mt-1 max-h-64 w-full overflow-auto rounded-xl border border-gray-200 bg-white py-1 shadow-lg"
              >
                <button
                  v-for="department in staffDepartmentSuggestions"
                  :key="department.id"
                  type="button"
                  class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-blue-50 hover:text-[#005eb8]"
                  @mousedown.prevent="selectStaffDepartment(department)"
                >
                  <span class="font-medium">{{ department.name }}</span>
                  <span v-if="department.positions?.length" class="ml-2 text-xs text-gray-400">
                    {{ department.positions.length }} {{ t('admin.applications.positionsShort') }}
                  </span>
                </button>
              </div>
            </div>
            <p v-if="staffDepartmentOptions.length === 0" class="text-xs text-amber-700">
              {{ t('admin.applications.noDepartmentsWithPositions') }}
            </p>
            <p v-else-if="staffDepartmentSearch && staffDepartmentSuggestions.length === 0" class="text-xs text-amber-700">
              {{ t('admin.applications.departmentsNotFound') }}
            </p>
          </div>

          <div v-if="createForm.vacancy_type === 'staff'" class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">{{ t('admin.applications.position') }}</label>
            <div class="relative">
              <input
                v-model="staffPositionSearch"
                type="text"
                :placeholder="t('admin.applications.staffPositionPlaceholder')"
                :disabled="!createForm.department_id || staffPositionOptions.length === 0"
                autocomplete="off"
                class="w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100 disabled:bg-gray-100 disabled:text-gray-500"
                @focus="staffPositionDropdownOpen = true"
                @input="handleStaffPositionInput"
                @keydown.down.prevent="focusStaffPositionSuggestion"
                @blur="closeStaffPositionDropdown"
              />
              <div
                v-if="staffPositionDropdownOpen && staffPositionSuggestions.length"
                class="absolute z-30 mt-1 max-h-64 w-full overflow-auto rounded-xl border border-gray-200 bg-white py-1 shadow-lg"
              >
                <button
                  v-for="position in staffPositionSuggestions"
                  :key="position.id"
                  type="button"
                  class="block w-full px-4 py-2 text-left text-sm font-medium text-gray-700 hover:bg-blue-50 hover:text-[#005eb8]"
                  @mousedown.prevent="selectStaffPosition(position)"
                >
                  {{ position.name }}
                </button>
              </div>
            </div>
            <p v-if="createForm.department_id && staffPositionOptions.length === 0" class="text-xs text-amber-700">
              {{ t('admin.applications.noPositionsInDepartment') }}
            </p>
            <p v-else-if="createForm.department_id && staffPositionSearch && staffPositionSuggestions.length === 0" class="text-xs text-amber-700">
              {{ t('admin.applications.positionsNotFound') }}
            </p>
          </div>

          <div v-if="createForm.vacancy_type === 'pps'" class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">{{ t('admin.applications.ppsPosition') }}</label>
            <div class="relative">
              <input
                v-model="ppsPositionSearch"
                type="text"
                :placeholder="t('admin.applications.ppsPositionPlaceholder')"
                :disabled="ppsPositionOptions.length === 0"
                autocomplete="off"
                class="w-full rounded-xl border border-gray-300 px-4 py-2.5 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100 disabled:bg-gray-100 disabled:text-gray-500"
                @focus="ppsPositionDropdownOpen = true"
                @input="handlePpsPositionInput"
                @keydown.down.prevent="focusPpsPositionSuggestion"
                @blur="closePpsPositionDropdown"
              />
              <div
                v-if="ppsPositionDropdownOpen && ppsPositionSuggestions.length"
                class="absolute z-30 mt-1 max-h-64 w-full overflow-auto rounded-xl border border-gray-200 bg-white py-1 shadow-lg"
              >
                <button
                  v-for="vacancy in ppsPositionSuggestions"
                  :key="vacancy.id"
                  type="button"
                  class="block w-full px-4 py-2 text-left text-sm font-medium text-gray-700 hover:bg-blue-50 hover:text-[#005eb8]"
                  @mousedown.prevent="selectPpsPosition(vacancy)"
                >
                  {{ vacancy.title }}
                </button>
              </div>
            </div>
            <p v-if="ppsPositionOptions.length === 0" class="text-xs text-amber-700">
              {{ t('admin.applications.noPpsPositions') }}
            </p>
            <p v-else-if="ppsPositionSearch && ppsPositionSuggestions.length === 0" class="text-xs text-amber-700">
              {{ t('admin.applications.ppsPositionsNotFound') }}
            </p>
          </div>

          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">{{ t('admin.applications.resume') }}</label>
            <input
              type="file"
              accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
              class="w-full rounded-xl border border-gray-300 px-4 py-2.5 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:font-medium file:text-[#005eb8] hover:file:bg-blue-100"
              @change="handleCreateResumeUpload"
            />
            <p class="text-xs text-gray-400">
              {{ createForm.resume?.name || t('admin.applications.resumeOptional') }}
            </p>
          </div>
        </div>

        <div v-if="createForm.full_name || selectedVacancy || selectedStaffDepartment || selectedStaffPosition || createForm.faculty_name || createForm.department_name" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3 text-sm">
          <div class="rounded-xl bg-slate-50 px-4 py-3">
            <div class="text-xs uppercase tracking-wide text-gray-400">{{ t('admin.applications.candidate') }}</div>
            <div class="mt-1 font-medium text-gray-800">{{ createForm.full_name || t('common.notSpecified') }}</div>
          </div>
          <div class="rounded-xl bg-slate-50 px-4 py-3">
            <div class="text-xs uppercase tracking-wide text-gray-400">{{ t('admin.applications.type') }}</div>
            <div class="mt-1 font-medium text-gray-800">{{ vacancyTypeLabel(createForm.vacancy_type) }}</div>
          </div>
          <div v-if="createForm.vacancy_type === 'pps'" class="rounded-xl bg-slate-50 px-4 py-3">
            <div class="text-xs uppercase tracking-wide text-gray-400">{{ t('admin.applications.faculty') }}</div>
            <div class="mt-1 font-medium text-gray-800">{{ createForm.faculty_name || t('common.notSpecified') }}</div>
          </div>
          <div v-if="createForm.vacancy_type === 'pps'" class="rounded-xl bg-slate-50 px-4 py-3">
            <div class="text-xs uppercase tracking-wide text-gray-400">{{ t('admin.applications.department') }}</div>
            <div class="mt-1 font-medium text-gray-800">{{ createForm.department_name || t('admin.applications.notSpecifiedFemale') }}</div>
          </div>
          <div v-if="createForm.vacancy_type === 'staff'" class="rounded-xl bg-slate-50 px-4 py-3">
            <div class="text-xs uppercase tracking-wide text-gray-400">{{ t('admin.applications.staffDepartment') }}</div>
            <div class="mt-1 font-medium text-gray-800">{{ selectedStaffDepartment?.name || t('admin.applications.notSelected') }}</div>
          </div>
          <div v-if="createForm.vacancy_type === 'staff'" class="rounded-xl bg-slate-50 px-4 py-3">
            <div class="text-xs uppercase tracking-wide text-gray-400">{{ t('admin.applications.position') }}</div>
            <div class="mt-1 font-medium text-gray-800">{{ selectedStaffPosition?.name || t('admin.applications.notSelectedFemale') }}</div>
          </div>
          <div v-if="createForm.vacancy_type === 'pps'" class="rounded-xl bg-slate-50 px-4 py-3 md:col-span-2 xl:col-span-2">
            <div class="text-xs uppercase tracking-wide text-gray-400">{{ t('admin.applications.ppsPosition') }}</div>
            <div class="mt-1 font-medium text-gray-800">{{ selectedVacancy?.title || t('admin.applications.notSelectedFemale') }}</div>
          </div>
        </div>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
          <button
            type="button"
            class="rounded-xl border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
            @click="resetCreateForm"
          >
            {{ t('common.clear') }}
          </button>
          <button
            type="button"
            :disabled="creatingApplication"
            class="rounded-xl bg-[#005eb8] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-blue-300"
            @click="createApplication"
          >
            {{ creatingApplication ? t('admin.applications.creating') : t('admin.applications.create') }}
          </button>
        </div>
      </section>

      <div v-if="loading" class="text-center text-gray-500">{{ t('common.loading') }}</div>
      <div v-else class="space-y-5">
        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            :class="applicationArchiveMode === 'active'
              ? 'bg-[#005eb8] text-white border-[#005eb8]'
              : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
            class="inline-flex items-center rounded-xl border px-4 py-2 text-sm font-semibold transition"
            @click="setApplicationArchiveMode('active')"
          >
            {{ t('admin.applications.active') }}
          </button>
          <button
            type="button"
            :class="applicationArchiveMode === 'archived'
              ? 'bg-[#005eb8] text-white border-[#005eb8]'
              : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
            class="inline-flex items-center rounded-xl border px-4 py-2 text-sm font-semibold transition"
            @click="setApplicationArchiveMode('archived')"
          >
            {{ t('admin.applications.archive') }}
          </button>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <button
            v-for="type in applicationTypeTabs"
            :key="type.value"
            :class="activeVacancyType === type.value
              ? 'bg-[#005eb8] text-white border-[#005eb8]'
              : 'bg-white text-[#005eb8] border-[#005eb8] hover:bg-[#005eb8] hover:text-white'"
            class="inline-flex items-center gap-2 border rounded-xl px-4 py-2 text-sm font-semibold transition"
            @click="setActiveVacancyType(type.value)"
          >
            <span>{{ type.label }}</span>
            <span
              :class="activeVacancyType === type.value ? 'bg-white/20 text-white' : 'bg-blue-50 text-[#005eb8]'"
              class="inline-flex min-w-7 justify-center rounded-full px-2 py-0.5 text-xs"
            >
              {{ type.count }}
            </span>
          </button>
        </div>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-7">
          <div class="text-sm text-gray-500 md:self-center">
            {{ t('admin.applications.shown') }}:
            <span class="font-semibold text-gray-700">
              {{ paginationTotal ? `${paginationStart}-${paginationEnd}` : 0 }}
            </span>
            <span class="text-gray-400"> / {{ paginationTotal }}</span>
            <span class="text-gray-400"> / {{ activeVacancyTypeCount }}</span>
          </div>
          <input
            v-model="applicationSearch"
            type="text"
            :placeholder="t('admin.applications.searchPlaceholder')"
            class="w-full rounded-xl border border-gray-300 px-4 py-2 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100 xl:col-span-2"
          />
          <select
            v-model="stageFilters.resume"
            class="w-full rounded-xl border border-gray-300 px-4 py-2 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
          >
            <option value="">{{ t('admin.applications.resumeAll') }}</option>
            <option
              v-for="option in stageFilterOptions.resume"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }}
            </option>
          </select>
          <select
            v-model="stageFilters.documents"
            class="w-full rounded-xl border border-gray-300 px-4 py-2 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
          >
            <option value="">{{ t('admin.applications.documentsAll') }}</option>
            <option
              v-for="option in stageFilterOptions.documents"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }}
            </option>
          </select>
          <select
            v-model="stageFilters.hiring"
            class="w-full rounded-xl border border-gray-300 px-4 py-2 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
          >
            <option value="">{{ t('admin.applications.hiringAll') }}</option>
            <option
              v-for="option in stageFilterOptions.hiring"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }}
            </option>
          </select>
          <select
            v-model="stageFilters.compliance"
            class="w-full rounded-xl border border-gray-300 px-4 py-2 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
          >
            <option value="">{{ t('admin.applications.complianceAll') }}</option>
            <option
              v-for="option in stageFilterOptions.compliance"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }}
            </option>
          </select>
        </div>

        <div
          v-if="filteredApplications.length > 0"
          class="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm shadow-sm sm:flex-row sm:items-center sm:justify-between"
        >
          <div class="text-gray-500">
            {{ t('admin.applications.page') }} <span class="font-semibold text-gray-700">{{ paginationPage }}</span>
            {{ t('admin.applications.of') }} <span class="font-semibold text-gray-700">{{ paginationTotalPages }}</span>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <label class="inline-flex items-center gap-2 text-gray-500">
              <span>{{ t('admin.applications.perPage') }}</span>
              <select
                v-model.number="paginationPerPage"
                class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-700 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100"
              >
                <option
                  v-for="option in paginationPerPageOptions"
                  :key="option"
                  :value="option"
                >
                  {{ option }}
                </option>
              </select>
            </label>
            <button
              type="button"
              :disabled="paginationPage <= 1"
              class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
              @click="goToPreviousPage"
            >
              {{ t('admin.applications.previous') }}
            </button>
            <button
              type="button"
              :disabled="paginationPage >= paginationTotalPages"
              class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
              @click="goToNextPage"
            >
              {{ t('admin.applications.next') }}
            </button>
          </div>
        </div>

        <div
          v-if="filteredApplications.length === 0"
          class="rounded-2xl border border-dashed border-gray-300 bg-white px-4 py-10 text-center text-gray-600"
        >
          {{ emptyApplicationsMessage }}
        </div>

        <div v-else class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                  <th class="px-4 py-3">
                    <TableSortButton
                      :label="t('admin.applications.candidate')"
                      column="candidate"
                      :sort-by="sortBy"
                      :sort-direction="sortDirection"
                      @sort="setSort"
                    />
                  </th>
                  <th class="px-4 py-3">
                    <TableSortButton
                      :label="t('admin.applications.expectedPosition')"
                      column="vacancy"
                      :sort-by="sortBy"
                      :sort-direction="sortDirection"
                      @sort="setSort"
                    />
                  </th>
                  <th class="px-4 py-3">
                    <TableSortButton
                      :label="t('admin.applications.facultyDepartment')"
                      column="profile"
                      :sort-by="sortBy"
                      :sort-direction="sortDirection"
                      @sort="setSort"
                    />
                  </th>
                  <th class="px-4 py-3">
                    <TableSortButton
                      :label="t('admin.applications.statuses')"
                      column="statuses"
                      :sort-by="sortBy"
                      :sort-direction="sortDirection"
                      @sort="setSort"
                    />
                  </th>
                  <th class="px-4 py-3">
                    <TableSortButton
                      :label="t('admin.applications.votes')"
                      column="votes"
                      :sort-by="sortBy"
                      :sort-direction="sortDirection"
                      @sort="setSort"
                    />
                  </th>
                  <th class="px-4 py-3">
                    <TableSortButton
                      :label="t('admin.applications.date')"
                      column="created_at"
                      :sort-by="sortBy"
                      :sort-direction="sortDirection"
                      @sort="setSort"
                    />
                  </th>
                  <th class="px-4 py-3 text-right">{{ t('admin.applications.action') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 bg-white">
                <tr
                  v-for="app in paginatedApplications"
                  :key="app.id"
                  class="cursor-pointer transition hover:bg-blue-50/50 focus-within:bg-blue-50/50"
                  tabindex="0"
                  role="link"
                  @click="goToDetails(app.id)"
                  @keydown.enter.self.prevent="goToDetails(app.id)"
                  @keydown.space.self.prevent="goToDetails(app.id)"
                >
                  <td class="px-4 py-3 align-top">
                    <div class="max-w-[260px]">
                      <div class="font-semibold text-gray-900 whitespace-normal break-words">
                        {{ app.user?.name || t('admin.applications.candidateNotSpecified') }}
                      </div>
                      <div class="mt-0.5 truncate text-xs text-gray-400">
                        {{ visibleUserEmail(app.user) || t('admin.applications.emailNotSpecified') }}
                      </div>
                      <div class="mt-1 text-xs text-gray-400">{{ t('admin.applications.applicationId') }}: {{ app.id }}</div>
                    </div>
                  </td>
                  <td class="px-4 py-3 align-top">
                    <div class="max-w-[320px]">
                      <div class="font-medium text-gray-900 whitespace-normal break-words">
                        {{ vacancyTitle(app) }}
                      </div>
                      <div class="mt-1 inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-[#005eb8]">
                        {{ vacancyTypeLabel(app?.vacancy?.type) }}
                      </div>
                    </div>
                  </td>
                  <td class="px-4 py-3 align-top text-gray-600">
                    <template v-if="app?.vacancy?.type === 'pps'">
                      <div class="max-w-[260px] whitespace-normal break-words">
                        {{ app.pps_profile?.faculty_name || t('admin.applications.facultyNotSpecified') }}
                      </div>
                      <div class="mt-1 max-w-[260px] whitespace-normal break-words text-xs text-gray-400">
                        {{ app.pps_profile?.department_name || t('admin.applications.departmentNotSpecified') }}
                      </div>
                    </template>
                    <template v-else>
                      <div class="max-w-[220px] whitespace-normal break-words">
                        {{ app?.vacancy?.position?.name || t('admin.applications.positionNotSpecified') }}
                      </div>
                    </template>
                  </td>
                  <td class="px-4 py-3 align-top">
                    <div class="flex items-center gap-2">
                      <span
                        v-for="stage in applicationStageOrder"
                        :key="`${app.id}-${stage}`"
                        class="group relative flex h-7 w-7 items-center justify-center"
                        :title="stageTooltip(stage, app)"
                      >
                        <span :class="stageDotClass(stage, app)"></span>
                        <span class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 hidden -translate-x-1/2 whitespace-nowrap rounded-lg bg-gray-900 px-2.5 py-1.5 text-xs font-medium normal-case tracking-normal text-white shadow-lg group-hover:block">
                          {{ stageTooltip(stage, app) }}
                        </span>
                      </span>
                    </div>
                  </td>
                  <td class="px-4 py-3 align-top text-gray-600">
                    <span class="font-medium text-gray-900">{{ app.vote_summary?.voted ?? 0 }}</span>
                    <span class="text-gray-400"> / {{ app.vote_summary?.total_members ?? 0 }}</span>
                  </td>
                  <td class="whitespace-nowrap px-4 py-3 align-top text-gray-600">
                    <div>{{ formatDate(app.created_at) }}</div>
                    <div class="mt-2 space-y-1">
                      <div class="text-xs text-gray-400">
                        {{ t('admin.applications.voting') }}: {{ formatDate(app.voting_started_at, t('admin.applications.notAssigned')) }}
                      </div>
                      <div
                        v-if="applicationArchiveMode === 'active'"
                        class="flex flex-wrap items-center gap-1"
                      >
                        <input
                          v-model="votingDateDrafts[app.id]"
                          type="date"
                          :disabled="isVotingDateSaving(app.id)"
                          class="w-36 rounded-lg border border-gray-300 px-2 py-1 text-xs text-gray-700 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-gray-100"
                          @click.stop
                          @keydown.stop
                          @input.stop
                        />
                        <input
                          v-model="votingOrderDrafts[app.id]"
                          type="number"
                          min="1"
                          step="1"
                          placeholder="№"
                          :disabled="isVotingDateSaving(app.id)"
                          class="w-16 rounded-lg border border-gray-300 px-2 py-1 text-xs text-gray-700 focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-gray-100"
                          @click.stop
                          @keydown.stop
                          @input.stop
                        />
                        <button
                          v-if="votingScheduleDraftChanged(app)"
                          type="button"
                          :disabled="isVotingDateSaving(app.id)"
                          class="rounded-lg bg-[#005eb8] px-2 py-1 text-xs font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-blue-300"
                          @click.stop="updateApplicationVotingDate(app)"
                        >
                          {{ t('common.save') }}
                        </button>
                        <button
                          v-if="votingScheduleDraftChanged(app)"
                          type="button"
                          :disabled="isVotingDateSaving(app.id)"
                          class="rounded-lg border border-gray-300 px-2 py-1 text-xs font-semibold text-gray-600 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
                          @click.stop="resetVotingScheduleDraft(app)"
                        >
                          {{ t('common.cancel') }}
                        </button>
                      </div>
                      <div
                        v-if="isVotingDateSaving(app.id)"
                        class="text-xs text-gray-400"
                      >
                        {{ t('admin.applications.saving') }}
                      </div>
                    </div>
                    <div v-if="applicationArchiveMode === 'archived'" class="mt-1 text-xs text-gray-400">
                      {{ t('admin.applications.archive') }}: {{ formatDate(app.archived_at) }}
                    </div>
                    <div v-if="applicationArchiveMode === 'archived' && app.archived_by?.name" class="mt-1 text-xs text-gray-400">
                      {{ app.archived_by.name }}
                    </div>
                  </td>
                  <td class="whitespace-nowrap px-4 py-3 align-top text-right">
                    <div class="flex flex-wrap justify-end gap-2">
                      <select
                        v-if="applicationArchiveMode === 'active'"
                        :value="quickHiringSelectValue(app)"
                        :disabled="!canQuickUpdateHiring(app) || isHiringQuickSaving(app.id)"
                        :title="quickHiringTitle(app)"
                        class="w-44 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 transition focus:border-[#005eb8] focus:outline-none focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-400"
                        @click.stop
                        @mousedown.stop
                        @change.stop="quickUpdateHiringStatus(app, $event.target.value)"
                      >
                        <option value="" disabled>{{ t('admin.applications.hiringSelect') }}</option>
                        <option
                          v-for="option in quickHiringStatusOptions(app)"
                          :key="option.value"
                          :value="option.value"
                        >
                          {{ option.label }}
                        </option>
                      </select>
                      <button
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold text-[#005eb8] transition hover:bg-blue-50"
                        @click.stop="goToDetails(app.id)"
                      >
                        {{ t('admin.applications.open') }}
                      </button>
                      <button
                        v-if="applicationArchiveMode === 'active'"
                        type="button"
                        :disabled="isArchivingApplication(app.id)"
                        class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60"
                        @click.stop="archiveApplication(app)"
                      >
                        {{ isArchivingApplication(app.id) ? t('admin.applications.archiving') : t('admin.applications.archiveAction') }}
                      </button>
                      <button
                        v-else
                        type="button"
                        :disabled="isUnarchivingApplication(app.id)"
                        class="rounded-lg border border-emerald-200 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-60"
                        @click.stop="unarchiveApplication(app)"
                      >
                        {{ isUnarchivingApplication(app.id) ? t('admin.applications.returning') : t('admin.applications.unarchive') }}
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div
            v-if="paginationTotalPages > 1"
            class="flex flex-col gap-3 border-t border-gray-200 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between"
          >
            <div class="text-gray-500">
              {{ paginationStart }}-{{ paginationEnd }} {{ t('admin.applications.of') }} {{ paginationTotal }}
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <button
                type="button"
                :disabled="paginationPage <= 1"
                class="rounded-lg border border-gray-300 px-3 py-1.5 font-semibold text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                @click="goToPreviousPage"
              >
                {{ t('admin.applications.previous') }}
              </button>
              <button
                v-for="page in visiblePaginationPages"
                :key="page"
                type="button"
                :class="page === paginationPage
                  ? 'border-[#005eb8] bg-[#005eb8] text-white'
                  : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
                class="min-w-9 rounded-lg border px-3 py-1.5 font-semibold transition"
                @click="setPaginationPage(page)"
              >
                {{ page }}
              </button>
              <button
                type="button"
                :disabled="paginationPage >= paginationTotalPages"
                class="rounded-lg border border-gray-300 px-3 py-1.5 font-semibold text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                @click="goToNextPage"
              >
                {{ t('admin.applications.next') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Layout>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import Layout from '../components/Layout.vue';
import TableSortButton from '../components/TableSortButton.vue';
import { useDialogStore } from '../stores/useDialogStore';
import { useI18n } from '../i18n';
import {
  applicationStageOrder,
  stageLabel,
  stageOptions,
  stageStatusField,
  normalizedStageStatus,
} from '../utils/applicationStages';
import {
  vacancyTypeLabel,
  vacancyTypeTabs as vacancyTypeTabValues,
} from '../utils/vacancyTypes';
import { sortRows } from '../utils/tableSort';

const route = useRoute();
const router = useRouter();
const dialogStore = useDialogStore();
const { locale, t } = useI18n();

const applications = ref([]);
const vacancies = ref([]);
const departments = ref([]);
const loading = ref(true);
const creatingApplication = ref(false);
const archivingApplicationIds = ref([]);
const unarchivingApplicationIds = ref([]);
const hiringQuickSavingIds = ref([]);
const votingDateSavingIds = ref([]);
const votingDateDrafts = ref({});
const votingOrderDrafts = ref({});
const applicationArchiveMode = ref(String(route.query.archived) === '1' ? 'archived' : 'active');
const showCreateForm = ref(false);
const applicationSearch = ref('');
const ppsPositionSearch = ref('');
const staffDepartmentSearch = ref('');
const staffPositionSearch = ref('');
const ppsPositionDropdownOpen = ref(false);
const staffDepartmentDropdownOpen = ref(false);
const staffPositionDropdownOpen = ref(false);
const stageFilters = ref({
  resume: '',
  documents: '',
  compliance: '',
  hiring: '',
});
const sortBy = ref('created_at');
const sortDirection = ref('desc');
const paginationPage = ref(1);
const paginationPerPage = ref(25);
const paginationPerPageOptions = [10, 25, 50, 100];
const serverPaginationMeta = ref(null);
const applicationTypeCounts = ref({});
let applicationsFetchDebounceTimer = null;
const createForm = ref({
  full_name: '',
  vacancy_type: vacancyTypeTabValues.includes(String(route.query.type)) ? String(route.query.type) : 'pps',
  faculty_name: '',
  department_name: '',
  department_id: '',
  position_id: '',
  vacancy_id: '',
  resume: null,
});

const normalizeVacancyType = (value) => (
  vacancyTypeTabValues.includes(String(value)) ? String(value) : 'pps'
);

const activeVacancyType = ref(normalizeVacancyType(route.query.type));
const hasServerPagination = computed(() => Boolean(serverPaginationMeta.value));

const applicationsByVacancyType = computed(() => Object.fromEntries(
  vacancyTypeTabValues.map((type) => [
    type,
    applications.value.filter((app) => app?.vacancy?.type === type),
  ]),
));

const applicationTypeCount = (type) => {
  const serverCount = applicationTypeCounts.value?.[type];

  if (serverCount !== undefined && serverCount !== null) {
    return Number(serverCount) || 0;
  }

  return applicationsByVacancyType.value[type]?.length || 0;
};

const activeVacancyTypeCount = computed(() => applicationTypeCount(activeVacancyType.value));
const applicationArchiveTotal = computed(() => (
  hasServerPagination.value
    ? vacancyTypeTabValues.reduce((total, type) => total + applicationTypeCount(type), 0)
    : applications.value.length
));

const applicationTypeTabs = computed(() => vacancyTypeTabValues.map((type) => ({
  value: type,
  label: vacancyTypeLabel(type),
  count: applicationTypeCount(type),
})));

const vacancyTypeOptions = computed(() => vacancyTypeTabValues.map((type) => ({
  value: type,
  label: vacancyTypeLabel(type),
})));

const activeVacancyTypeLabel = computed(() => vacancyTypeLabel(activeVacancyType.value));
const emptyApplicationsMessage = computed(() => (
  applicationArchiveMode.value === 'archived'
    ? t('admin.applications.emptyArchived', { type: activeVacancyTypeLabel.value })
    : t('admin.applications.emptyActive', { type: activeVacancyTypeLabel.value })
));
const quickHiringStatusOptions = (app) => {
  const options = [
    { value: 'voting', label: t('stage.hiring.status.voting') },
  ];

  if (app?.vacancy?.type === 'pps') {
    return [
      ...options,
      { value: 'hired_1_year', label: t('stage.hiring.status.hired_1_year') },
      { value: 'hired_3_year', label: t('stage.hiring.status.hired_3_year') },
    ];
  }

  return [
    ...options,
    { value: 'hired', label: t('stage.hiring.status.hired') },
  ];
};
const quickHiringStatusValues = (app) => new Set(quickHiringStatusOptions(app).map((option) => option.value));

const stageFilterOptions = computed(() => Object.fromEntries(
  applicationStageOrder.map((stage) => [stage, stageOptions(stage, { vacancy: { type: activeVacancyType.value } })]),
));

const filteredApplications = computed(() => {
  if (hasServerPagination.value) {
    return applications.value;
  }

  const q = applicationSearch.value.trim().toLowerCase();
  const rows = (applicationsByVacancyType.value[activeVacancyType.value] || []).filter((app) => {
    const matchesStages = applicationStageOrder.every((stage) => {
      const selectedStatus = stageFilters.value[stage];
      return !selectedStatus || stageStatusValue(stage, app) === selectedStatus;
    });

    if (!matchesStages) return false;
    if (!q) return true;

    return applicationSearchValues(app).some((value) => value.includes(q));
  });

  return sortRows(rows, applicationSortValue, sortDirection.value);
});
const paginationTotal = computed(() => (
  hasServerPagination.value
    ? Number(serverPaginationMeta.value?.total || 0)
    : filteredApplications.value.length
));
const paginationTotalPages = computed(() => (
  hasServerPagination.value
    ? Math.max(1, Number(serverPaginationMeta.value?.last_page || 1))
    : Math.max(1, Math.ceil(paginationTotal.value / paginationPerPage.value))
));
const normalizedPaginationPage = computed(() => Math.min(Math.max(1, paginationPage.value), paginationTotalPages.value));
const paginationStartIndex = computed(() => (normalizedPaginationPage.value - 1) * paginationPerPage.value);
const paginatedApplications = computed(() => (
  hasServerPagination.value
    ? filteredApplications.value
    : filteredApplications.value.slice(
      paginationStartIndex.value,
      paginationStartIndex.value + paginationPerPage.value,
    )
));
const paginationStart = computed(() => (
  paginationTotal.value === 0 ? 0 : paginationStartIndex.value + 1
));
const paginationEnd = computed(() => Math.min(
  paginationStartIndex.value + paginationPerPage.value,
  paginationTotal.value,
));
const visiblePaginationPages = computed(() => {
  const total = paginationTotalPages.value;
  const current = normalizedPaginationPage.value;
  const start = Math.max(1, current - 2);
  const end = Math.min(total, start + 4);
  const normalizedStart = Math.max(1, end - 4);

  return Array.from(
    { length: end - normalizedStart + 1 },
    (_, index) => normalizedStart + index,
  );
});
const normalizeVacancyMatchText = (value) => String(value || '')
  .trim()
  .toLowerCase()
  .replace(/[«»"'`]/g, '')
  .replace(/\s+/g, ' ');
const ppsVacancyTitles = [
  'Ассистент',
  'Ассистент-профессор',
  'Ассистент-профессор-исследователь',
  'Ассоциированный профессор',
  'Ассоциированный профессор – исследователь',
  'Заведующий кафедрой (для выпускающих кафедр)',
  'Заведующий кафедрой (для не выпускающих кафедр)',
  'Исполняющий обязанности (и.о.) заведующий кафедрой',
  'Сеньор-лектор',
  'Профессор',
  'Профессор-исследователь',
  'Исполняющий обязанности (и.о.) ассоциированного профессора',
  'Исполняющий обязанности (и.о.) ассоциированного профессора – исследователя',
  'Исполняющий обязанности (и.о.) профессора',
  'Исполняющий обязанности (и.о.) профессора-исследователя',
];
const ppsVacancyTitleOrder = new Map(
  ppsVacancyTitles.map((title, index) => [normalizeVacancyMatchText(title), index]),
);
const filteredVacancies = computed(() => {
  const typeVacancies = vacancies.value.filter((vacancy) => vacancy?.type === createForm.value.vacancy_type);

  if (createForm.value.vacancy_type !== 'pps') {
    return typeVacancies
      .slice()
      .sort((left, right) => String(left?.title || '').localeCompare(String(right?.title || ''), 'ru'));
  }

  const uniqueByTitle = new Map();

  for (const vacancy of typeVacancies) {
    const titleKey = normalizeVacancyMatchText(vacancy?.title);
    if (!ppsVacancyTitleOrder.has(titleKey)) {
      continue;
    }

    const current = uniqueByTitle.get(titleKey);
    if (!current || (!vacancy?.position_id && current?.position_id)) {
      uniqueByTitle.set(titleKey, vacancy);
    }
  }

  return Array.from(uniqueByTitle.values())
    .sort((left, right) => (
      ppsVacancyTitleOrder.get(normalizeVacancyMatchText(left?.title))
      - ppsVacancyTitleOrder.get(normalizeVacancyMatchText(right?.title))
    ));
});

const selectedVacancy = computed(() => vacancies.value.find((vacancy) => String(vacancy.id) === createForm.value.vacancy_id) || null);
const ppsPositionOptions = computed(() => filteredVacancies.value);
const filteredPpsPositionOptions = computed(() => {
  const query = normalizeVacancyMatchText(ppsPositionSearch.value);
  const filtered = query
    ? ppsPositionOptions.value.filter((vacancy) => normalizeVacancyMatchText(vacancy?.title).includes(query))
    : ppsPositionOptions.value;

  if (
    selectedVacancy.value
    && !filtered.some((vacancy) => String(vacancy.id) === String(selectedVacancy.value.id))
  ) {
    return [selectedVacancy.value, ...filtered];
  }

  return filtered;
});
const ppsPositionSuggestions = computed(() => filteredPpsPositionOptions.value);
const staffDepartmentOptions = computed(() => (
  (departments.value || [])
    .filter((department) => (
      !isChairDepartment(department)
      && Array.isArray(department?.positions)
      && department.positions.length > 0
    ))
    .slice()
    .sort((left, right) => String(left?.name || '').localeCompare(String(right?.name || ''), 'ru'))
));
const selectedStaffDepartment = computed(() => (
  staffDepartmentOptions.value.find((department) => String(department.id) === createForm.value.department_id) || null
));
const filteredStaffDepartmentOptions = computed(() => {
  const query = normalizeVacancyMatchText(staffDepartmentSearch.value);
  const filtered = query
    ? staffDepartmentOptions.value.filter((department) => normalizeVacancyMatchText(department?.name).includes(query))
    : staffDepartmentOptions.value;

  if (
    selectedStaffDepartment.value
    && !filtered.some((department) => String(department.id) === String(selectedStaffDepartment.value.id))
  ) {
    return [selectedStaffDepartment.value, ...filtered];
  }

  return filtered;
});
const staffDepartmentSuggestions = computed(() => filteredStaffDepartmentOptions.value.slice(0, 12));
const staffPositionOptions = computed(() => (
  (selectedStaffDepartment.value?.positions || [])
    .slice()
    .sort((left, right) => String(left?.name || '').localeCompare(String(right?.name || ''), 'ru'))
));
const selectedStaffPosition = computed(() => (
  staffPositionOptions.value.find((position) => String(position.id) === createForm.value.position_id) || null
));
const filteredStaffPositionOptions = computed(() => {
  const query = normalizeVacancyMatchText(staffPositionSearch.value);
  const filtered = query
    ? staffPositionOptions.value.filter((position) => normalizeVacancyMatchText(position?.name).includes(query))
    : staffPositionOptions.value;

  if (
    selectedStaffPosition.value
    && !filtered.some((position) => String(position.id) === String(selectedStaffPosition.value.id))
  ) {
    return [selectedStaffPosition.value, ...filtered];
  }

  return filtered;
});
const staffPositionSuggestions = computed(() => filteredStaffPositionOptions.value.slice(0, 12));
const departmentChildrenByParentId = computed(() => {
  const grouped = new Map();

  for (const department of departments.value || []) {
    const parentId = Number(department?.parent_id || 0);
    if (!parentId) continue;

    if (!grouped.has(parentId)) {
      grouped.set(parentId, []);
    }

    grouped.get(parentId).push(department);
  }

  for (const items of grouped.values()) {
    items.sort((left, right) => String(left?.name || '').localeCompare(String(right?.name || ''), 'ru'));
  }

  return grouped;
});
const hasDescribedFaculties = computed(() => (
  (departments.value || []).some((department) => String(department?.description || '').trim().toLowerCase() === 'факультет')
));
const compareFacultyNames = (left, right) => {
  const leftName = String(left?.name || '').trim();
  const rightName = String(right?.name || '').trim();

  if (leftName === 'Другое' && rightName !== 'Другое') return 1;
  if (rightName === 'Другое' && leftName !== 'Другое') return -1;

  return leftName.localeCompare(rightName, 'ru');
};
const facultyOptions = computed(() => (
  (departments.value || [])
    .filter((department) => (
      !department?.parent_id
      && (hasDescribedFaculties.value
        ? String(department?.description || '').trim().toLowerCase() === 'факультет'
        : departmentChildrenByParentId.value.has(Number(department?.id)))
    ))
    .slice()
    .sort(compareFacultyNames)
));
const selectedFaculty = computed(() => (
  facultyOptions.value.find((faculty) => faculty.name === createForm.value.faculty_name) || null
));
const hasDescribedChairs = computed(() => (
  (departments.value || []).some((department) => String(department?.description || '').trim().toLowerCase() === 'кафедра')
));
const isChairDepartment = (department) => {
  const description = String(department?.description || '').trim().toLowerCase();
  const name = String(department?.name || '').trim().toLowerCase();

  return description === 'кафедра' || name.startsWith('кафедра');
};
const departmentOptions = computed(() => {
  if (!selectedFaculty.value) {
    return [];
  }

  return (departmentChildrenByParentId.value.get(Number(selectedFaculty.value.id)) || [])
    .filter((department) => (hasDescribedChairs.value ? isChairDepartment(department) : true))
    .slice()
    .sort((left, right) => String(left?.name || '').localeCompare(String(right?.name || ''), 'ru'));
});
const errorText = (error) => error?.response?.data?.message || t('validation.genericError');

const isArchivingApplication = (applicationId) => archivingApplicationIds.value.includes(applicationId);
const isUnarchivingApplication = (applicationId) => unarchivingApplicationIds.value.includes(applicationId);
const isHiringQuickSaving = (applicationId) => hiringQuickSavingIds.value.includes(applicationId);
const isVotingDateSaving = (applicationId) => votingDateSavingIds.value.includes(applicationId);

const votingDateInputValue = (app) => {
  const value = String(app?.voting_date || '').slice(0, 10);

  return /^\d{4}-\d{2}-\d{2}$/.test(value) ? value : '';
};

const votingDateDraftValue = (app) => (
  votingDateDrafts.value[app?.id] ?? votingDateInputValue(app)
);

const votingOrderInputValue = (app) => {
  const value = Number(app?.voting_order);

  return Number.isInteger(value) && value > 0 ? String(value) : '';
};

const votingOrderDraftValue = (app) => (
  votingOrderDrafts.value[app?.id] ?? votingOrderInputValue(app)
);

const votingScheduleDraftChanged = (app) => (
  String(votingDateDraftValue(app) || '') !== votingDateInputValue(app)
  || String(votingOrderDraftValue(app) || '') !== votingOrderInputValue(app)
);

const resetVotingScheduleDraft = (app) => {
  if (!app?.id) return;

  votingDateDrafts.value = {
    ...votingDateDrafts.value,
    [app.id]: votingDateInputValue(app),
  };
  votingOrderDrafts.value = {
    ...votingOrderDrafts.value,
    [app.id]: votingOrderInputValue(app),
  };
};

const syncVotingDateDrafts = () => {
  votingDateDrafts.value = Object.fromEntries(
    (applications.value || []).map((app) => [app.id, votingDateInputValue(app)])
  );
  votingOrderDrafts.value = Object.fromEntries(
    (applications.value || []).map((app) => [app.id, votingOrderInputValue(app)])
  );
};

const canQuickUpdateHiring = (app) => (
  applicationArchiveMode.value === 'active'
  && app?.compliance_status === 'clear'
);

const quickHiringTitle = (app) => (
  canQuickUpdateHiring(app)
    ? t('admin.applications.quickHiringTitle')
    : t('admin.applications.quickHiringDisabledTitle')
);

const quickHiringSelectValue = (app) => {
  const value = normalizedStageStatus('hiring', app?.hiring_status, app);

  return quickHiringStatusValues(app).has(value) ? value : '';
};

const replaceApplicationInList = (updatedApplication) => {
  if (!updatedApplication?.id) return;

  applications.value = applications.value.map((app) => (
    app.id === updatedApplication.id ? updatedApplication : app
  ));
  resetVotingScheduleDraft(updatedApplication);
};

const quickUpdateHiringStatus = async (app, status) => {
  if (!app?.id || !quickHiringStatusValues(app).has(status)) {
    return;
  }

  if (status === quickHiringSelectValue(app)) {
    return;
  }

  if (!canQuickUpdateHiring(app)) {
    alert(t('admin.applications.quickHiringDisabled'));
    return;
  }

  hiringQuickSavingIds.value = [...hiringQuickSavingIds.value, app.id];

  try {
    const response = await axios.put(`/api/admin/applications/${app.id}`, {
      stage: 'hiring',
      stage_status: status,
      comment: null,
    });

    replaceApplicationInList(response.data?.application);
    alert(response.data?.message || t('admin.applications.hiringStatusUpdated'));
  } catch (error) {
    alert(errorText(error));
  } finally {
    hiringQuickSavingIds.value = hiringQuickSavingIds.value.filter((id) => id !== app.id);
  }
};

const updateApplicationVotingDate = async (app) => {
  if (!app?.id) {
    return;
  }

  const draftDate = String(votingDateDraftValue(app) || '').trim();
  if (draftDate && !/^\d{4}-\d{2}-\d{2}$/.test(draftDate)) {
    alert(t('admin.applications.invalidVotingDate'));
    resetVotingScheduleDraft(app);
    return;
  }

  const draftOrder = String(votingOrderDraftValue(app) || '').trim();
  if (draftOrder && (!/^\d+$/.test(draftOrder) || Number(draftOrder) < 1)) {
    alert(t('admin.applications.invalidVotingOrder'));
    resetVotingScheduleDraft(app);
    return;
  }

  const normalizedDate = draftDate || null;
  const normalizedOrder = draftOrder ? Number(draftOrder) : null;
  if (
    (votingDateInputValue(app) || null) === normalizedDate
    && (votingOrderInputValue(app) ? Number(votingOrderInputValue(app)) : null) === normalizedOrder
  ) {
    return;
  }

  votingDateSavingIds.value = [...votingDateSavingIds.value, app.id];

  try {
    const response = await axios.put(`/api/admin/applications/${app.id}/voting-date`, {
      voting_date: normalizedDate,
      voting_order: normalizedOrder,
    });

    replaceApplicationInList(response.data?.application);
  } catch (error) {
    alert(errorText(error));
  } finally {
    votingDateSavingIds.value = votingDateSavingIds.value.filter((id) => id !== app.id);
  }
};

const archiveApplication = async (app) => {
  if (!app?.id) return;

  const candidateName = app.user?.name || t('admin.applications.applicationNumber', { id: app.id });
  const confirmed = await dialogStore.openConfirm(
    t('admin.applications.archiveConfirmMessage', { name: candidateName }),
    {
      title: t('admin.applications.archiveConfirmTitle'),
      confirmText: t('admin.applications.archiveAction'),
      cancelText: t('common.cancel'),
    },
  );

  if (!confirmed) {
    return;
  }

  archivingApplicationIds.value = [...archivingApplicationIds.value, app.id];

  try {
    const response = await axios.put(`/api/admin/applications/${app.id}/archive`);
    await fetchApplications();
    alert(response.data?.message || t('admin.applications.archivedMessage'));
  } catch (error) {
    alert(errorText(error));
  } finally {
    archivingApplicationIds.value = archivingApplicationIds.value.filter((id) => id !== app.id);
  }
};

const unarchiveApplication = async (app) => {
  if (!app?.id) return;

  const candidateName = app.user?.name || t('admin.applications.applicationNumber', { id: app.id });
  const confirmed = await dialogStore.openConfirm(
    t('admin.applications.unarchiveConfirmMessage', { name: candidateName }),
    {
      title: t('admin.applications.unarchiveConfirmTitle'),
      confirmText: t('admin.applications.unarchive'),
      cancelText: t('common.cancel'),
    },
  );

  if (!confirmed) {
    return;
  }

  unarchivingApplicationIds.value = [...unarchivingApplicationIds.value, app.id];

  try {
    const response = await axios.put(`/api/admin/applications/${app.id}/unarchive`);
    await fetchApplications();
    alert(response.data?.message || t('admin.applications.unarchivedMessage'));
  } catch (error) {
    alert(errorText(error));
  } finally {
    unarchivingApplicationIds.value = unarchivingApplicationIds.value.filter((id) => id !== app.id);
  }
};

const isTechnicalCandidateEmail = (email) => String(email || '').toLowerCase().endsWith('@hr-ai.invalid');
const visibleUserEmail = (user) => {
  const email = String(user?.email || '').trim();
  return email && !isTechnicalCandidateEmail(email) ? email : '';
};

const applicationSearchValues = (app) => [
  app.user?.name,
  visibleUserEmail(app.user),
  vacancyTitle(app),
  vacancyTypeLabel(app?.vacancy?.type),
  app.pps_profile?.faculty_name,
  app.pps_profile?.department_name,
  app?.vacancy?.position?.name,
].map((value) => String(value || '').toLowerCase());

const stageShortTitle = (stage) => {
  if (stage === 'resume') return t('stage.resume.short');
  if (stage === 'documents') return t('stage.documents.short');
  if (stage === 'compliance') return t('stage.compliance.short');
  if (stage === 'hiring') return t('stage.hiring.short');
  return stage;
};

const stageStatusValue = (stage, app) => normalizedStageStatus(
  stage,
  app?.[stageStatusField(stage)],
  app,
);

const stageTooltipLabel = (stage, app) => {
  const status = stageStatusValue(stage, app);
  if (stage === 'resume' && status === 'accepted') return t('stage.resume.status.accepted_female');

  return stageLabel(stage, app?.[stageStatusField(stage)], app);
};

const stageTooltip = (stage, app) => `${stageShortTitle(stage)}: ${stageTooltipLabel(stage, app)}`;

const stageDotClass = (stage, app) => {
  const status = stageStatusValue(stage, app);
  const base = 'block h-2.5 w-2.5 rounded-full ring-4 transition group-hover:scale-110';

  if (['accepted', 'clear', 'hired', 'hired_1_year', 'hired_3_year'].includes(status)) {
    return `${base} bg-emerald-500 ring-emerald-100`;
  }

  if (['rejected', 'flagged'].includes(status)) {
    return `${base} bg-red-500 ring-red-100`;
  }

  if (['pending', 'voting'].includes(status)) {
    return `${base} bg-amber-400 ring-amber-100`;
  }

  if (status === 'awaiting_upload') {
    return `${base} bg-sky-500 ring-sky-100`;
  }

  return `${base} bg-slate-300 ring-slate-100`;
};

const vacancyTitle = (app) => {
  if (app?.vacancy?.type === 'pps') {
    return app?.pps_profile?.desired_position || app?.vacancy?.title || t('admin.applications.positionNotSpecified');
  }

  return app?.vacancy?.position?.name || app?.vacancy?.title || t('admin.applications.positionNotSpecified');
};

const applicationProfileLabel = (app) => {
  if (app?.vacancy?.type === 'pps') {
    return `${app.pps_profile?.faculty_name || ''} ${app.pps_profile?.department_name || ''}`.trim();
  }

  return app?.vacancy?.position?.name || '';
};

const applicationSortValue = (app) => {
  if (sortBy.value === 'candidate') return app.user?.name || '';
  if (sortBy.value === 'vacancy') return vacancyTitle(app);
  if (sortBy.value === 'profile') return applicationProfileLabel(app);
  if (sortBy.value === 'statuses') return applicationStageOrder.map((stage) => stageTooltipLabel(stage, app)).join(' ');
  if (sortBy.value === 'votes') return Number(app.vote_summary?.voted || 0);
  if (sortBy.value === 'created_at') return Date.parse(app.created_at || '') || 0;
  return app.id || 0;
};

const setSort = (column) => {
  if (sortBy.value === column) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    return;
  }

  sortBy.value = column;
  sortDirection.value = ['created_at', 'votes'].includes(column) ? 'desc' : 'asc';
};

const setPaginationPage = (page) => {
  paginationPage.value = Math.min(Math.max(1, Number(page) || 1), paginationTotalPages.value);
};

const goToPreviousPage = () => {
  setPaginationPage(paginationPage.value - 1);
};

const goToNextPage = () => {
  setPaginationPage(paginationPage.value + 1);
};

const formatDate = (value, fallback = t('admin.applications.notSpecifiedFemale')) => {
  if (!value) return fallback;

  return new Date(value).toLocaleDateString(locale.value === 'kk' ? 'kk-KZ' : 'ru-RU', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

const syncApplicationsQuery = (overrides = {}) => {
  const query = {
    ...route.query,
    type: activeVacancyType.value,
    ...overrides,
  };

  if (applicationArchiveMode.value === 'archived') {
    query.archived = '1';
  } else {
    delete query.archived;
  }

  router.replace({
    query,
  });
};

const syncVacancyTypeQuery = (type) => {
  syncApplicationsQuery({ type });
};

const setActiveVacancyType = (type) => {
  if (activeVacancyType.value === type) return;

  activeVacancyType.value = type;
  paginationPage.value = 1;
  syncVacancyTypeQuery(type);
};

const setApplicationArchiveMode = (mode) => {
  if (applicationArchiveMode.value === mode) return;

  applicationArchiveMode.value = mode;
  showCreateForm.value = false;
  applicationSearch.value = '';
  paginationPage.value = 1;
  syncApplicationsQuery();
};

const syncActiveVacancyType = () => {
  if (hasServerPagination.value) {
    return;
  }

  if (applications.value.length === 0) {
    return;
  }

  if (applicationsByVacancyType.value[activeVacancyType.value]?.length) {
    return;
  }

  const fallbackType = vacancyTypeTabValues.find((type) => applicationsByVacancyType.value[type]?.length);
  activeVacancyType.value = fallbackType || 'pps';
  paginationPage.value = 1;
  syncVacancyTypeQuery(activeVacancyType.value);
};

const detailsRoute = (applicationId) => ({
  name: 'AdminApplicationDetails',
  params: { id: applicationId },
  query: {
    type: activeVacancyType.value,
    ...(applicationArchiveMode.value === 'archived' ? { archived: '1' } : {}),
  },
});

const goToDetails = (applicationId) => {
  router.push(detailsRoute(applicationId));
};

const resetCreateForm = () => {
  ppsPositionSearch.value = '';
  staffDepartmentSearch.value = '';
  staffPositionSearch.value = '';
  ppsPositionDropdownOpen.value = false;
  staffDepartmentDropdownOpen.value = false;
  staffPositionDropdownOpen.value = false;
  createForm.value = {
    full_name: '',
    vacancy_type: activeVacancyType.value,
    faculty_name: '',
    department_name: '',
    department_id: '',
    position_id: '',
    vacancy_id: '',
    resume: null,
  };
};

const toggleCreateForm = () => {
  showCreateForm.value = !showCreateForm.value;

  if (showCreateForm.value) {
    resetCreateForm();
  }
};

const handleCreateResumeUpload = (event) => {
  createForm.value.resume = event.target.files?.[0] || null;
};

const selectPpsPosition = (vacancy) => {
  createForm.value.vacancy_id = String(vacancy.id);
  ppsPositionSearch.value = vacancy.title || '';
  ppsPositionDropdownOpen.value = false;
};

const selectStaffDepartment = (department) => {
  createForm.value.department_id = String(department.id);
  createForm.value.position_id = '';
  staffDepartmentSearch.value = department.name || '';
  staffPositionSearch.value = '';
  staffDepartmentDropdownOpen.value = false;
  staffPositionDropdownOpen.value = false;
};

const selectStaffPosition = (position) => {
  createForm.value.position_id = String(position.id);
  staffPositionSearch.value = position.name || '';
  staffPositionDropdownOpen.value = false;
};

const handleStaffDepartmentInput = () => {
  staffDepartmentDropdownOpen.value = true;

  if (
    selectedStaffDepartment.value
    && normalizeVacancyMatchText(staffDepartmentSearch.value) !== normalizeVacancyMatchText(selectedStaffDepartment.value.name)
  ) {
    createForm.value.department_id = '';
    createForm.value.position_id = '';
    staffPositionSearch.value = '';
  }
};

const handlePpsPositionInput = () => {
  ppsPositionDropdownOpen.value = true;

  if (
    selectedVacancy.value
    && normalizeVacancyMatchText(ppsPositionSearch.value) !== normalizeVacancyMatchText(selectedVacancy.value.title)
  ) {
    createForm.value.vacancy_id = '';
  }
};

const handleStaffPositionInput = () => {
  staffPositionDropdownOpen.value = true;

  if (
    selectedStaffPosition.value
    && normalizeVacancyMatchText(staffPositionSearch.value) !== normalizeVacancyMatchText(selectedStaffPosition.value.name)
  ) {
    createForm.value.position_id = '';
  }
};

const closePpsPositionDropdown = () => {
  window.setTimeout(() => {
    ppsPositionDropdownOpen.value = false;
  }, 120);
};

const closeStaffDepartmentDropdown = () => {
  window.setTimeout(() => {
    staffDepartmentDropdownOpen.value = false;
  }, 120);
};

const closeStaffPositionDropdown = () => {
  window.setTimeout(() => {
    staffPositionDropdownOpen.value = false;
  }, 120);
};

const focusStaffDepartmentSuggestion = () => {
  staffDepartmentDropdownOpen.value = true;
};

const focusPpsPositionSuggestion = () => {
  ppsPositionDropdownOpen.value = true;
};

const focusStaffPositionSuggestion = () => {
  staffPositionDropdownOpen.value = true;
};

const buildApplicationsRequestParams = () => ({
  archived: applicationArchiveMode.value === 'archived' ? 1 : undefined,
  type: activeVacancyType.value,
  page: paginationPage.value,
  per_page: paginationPerPage.value,
  q: applicationSearch.value.trim() || undefined,
  sort_by: sortBy.value,
  sort_direction: sortDirection.value,
  resume_status: stageFilters.value.resume || undefined,
  documents_status: stageFilters.value.documents || undefined,
  compliance_status: stageFilters.value.compliance || undefined,
  hiring_status: stageFilters.value.hiring || undefined,
});

const applyApplicationsPayload = (payload) => {
  if (Array.isArray(payload)) {
    applications.value = payload;
    serverPaginationMeta.value = null;
    applicationTypeCounts.value = {};
    return;
  }

  const counts = payload?.counts || {};

  applications.value = Array.isArray(payload?.data) ? payload.data : [];
  serverPaginationMeta.value = payload?.meta || null;
  applicationTypeCounts.value = Object.fromEntries(
    vacancyTypeTabValues.map((type) => [type, Number(counts[type] || 0)]),
  );
};

const scheduleFetchApplications = (delay = 250) => {
  if (applicationsFetchDebounceTimer) {
    clearTimeout(applicationsFetchDebounceTimer);
  }

  applicationsFetchDebounceTimer = setTimeout(() => {
    applicationsFetchDebounceTimer = null;
    fetchApplications();
  }, delay);
};

const fetchApplications = async () => {
  if (applicationsFetchDebounceTimer) {
    clearTimeout(applicationsFetchDebounceTimer);
    applicationsFetchDebounceTimer = null;
  }

  loading.value = true;

  try {
    const response = await axios.get('/api/admin/applications', {
      params: buildApplicationsRequestParams(),
    });
    applyApplicationsPayload(response.data);
    syncVotingDateDrafts();
    syncActiveVacancyType();
  } catch (error) {
    alert(errorText(error));
  } finally {
    loading.value = false;
  }
};

const fetchVacancies = async () => {
  try {
    const response = await axios.get('/api/admin/vacancies');
    vacancies.value = response.data;
  } catch (error) {
    alert(errorText(error));
  }
};

const fetchDepartments = async () => {
  try {
    const response = await axios.get('/api/admin/departments');
    departments.value = response.data || [];
  } catch (error) {
    departments.value = [];
    alert(errorText(error));
  }
};

const createApplication = async () => {
  if (!createForm.value.full_name.trim()) {
    alert(t('admin.applications.validation.enterCandidate'));
    return;
  }

  if (createForm.value.vacancy_type === 'pps' && !createForm.value.faculty_name.trim()) {
    alert(t('admin.applications.validation.chooseFaculty'));
    return;
  }

  if (createForm.value.vacancy_type === 'pps' && !createForm.value.department_name.trim()) {
    alert(t('admin.applications.validation.chooseDepartment'));
    return;
  }

  if (createForm.value.vacancy_type === 'staff' && !createForm.value.department_id) {
    alert(t('admin.applications.validation.chooseStaffDepartment'));
    return;
  }

  if (createForm.value.vacancy_type === 'staff' && !createForm.value.position_id) {
    alert(t('admin.applications.validation.choosePosition'));
    return;
  }

  if (createForm.value.vacancy_type === 'pps' && !createForm.value.vacancy_id) {
    alert(t('admin.applications.validation.choosePpsPosition'));
    return;
  }

  creatingApplication.value = true;

  const formData = new FormData();
  formData.append('full_name', createForm.value.full_name.trim());
  formData.append('vacancy_type', createForm.value.vacancy_type);

  if (createForm.value.vacancy_type === 'pps') {
    formData.append('vacancy_id', createForm.value.vacancy_id);
    formData.append('faculty_name', createForm.value.faculty_name.trim());
    formData.append('department_name', createForm.value.department_name.trim());
  } else {
    formData.append('department_id', createForm.value.department_id);
    formData.append('position_id', createForm.value.position_id);
  }

  if (createForm.value.resume) {
    formData.append('resume', createForm.value.resume);
  }

  try {
    const response = await axios.post('/api/admin/applications', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    const createdApplication = response.data?.application;
    if (createdApplication) {
      resetVotingScheduleDraft(createdApplication);

      if (vacancyTypeTabValues.includes(String(createdApplication?.vacancy?.type))) {
        activeVacancyType.value = String(createdApplication.vacancy.type);
        syncVacancyTypeQuery(activeVacancyType.value);
      }

      paginationPage.value = 1;
      await fetchApplications();
    }

    resetCreateForm();
    showCreateForm.value = false;
    alert(response.data?.message || t('admin.applications.createdMessage'));
  } catch (error) {
    alert(errorText(error));
  } finally {
    creatingApplication.value = false;
  }
};

watch(() => createForm.value.vacancy_type, (type) => {
  const isSelectedVacancyVisible = filteredVacancies.value.some(
    (vacancy) => String(vacancy.id) === createForm.value.vacancy_id,
  );

  if (!isSelectedVacancyVisible) {
    createForm.value.vacancy_id = '';
    ppsPositionSearch.value = '';
  }

  if (type !== 'pps') {
    createForm.value.faculty_name = '';
    createForm.value.department_name = '';
    createForm.value.vacancy_id = '';
    ppsPositionSearch.value = '';
    ppsPositionDropdownOpen.value = false;
  }

  if (type !== 'staff') {
    createForm.value.department_id = '';
    createForm.value.position_id = '';
    staffDepartmentSearch.value = '';
    staffPositionSearch.value = '';
  }

  if (activeVacancyType.value !== type) {
    activeVacancyType.value = type;
    syncVacancyTypeQuery(type);
  }
});

watch(() => createForm.value.faculty_name, () => {
  const isSelectedDepartmentVisible = departmentOptions.value.some(
    (department) => department.name === createForm.value.department_name,
  );

  if (!isSelectedDepartmentVisible) {
    createForm.value.department_name = '';
  }
});

watch(() => createForm.value.department_id, () => {
  staffPositionSearch.value = '';

  const isSelectedPositionVisible = staffPositionOptions.value.some(
    (position) => String(position.id) === createForm.value.position_id,
  );

  if (!isSelectedPositionVisible) {
    createForm.value.position_id = '';
  }
});

watch([
  applicationSearch,
  activeVacancyType,
  applicationArchiveMode,
  sortBy,
  sortDirection,
  paginationPerPage,
  () => stageFilters.value.resume,
  () => stageFilters.value.documents,
  () => stageFilters.value.compliance,
  () => stageFilters.value.hiring,
], () => {
  if (paginationPage.value !== 1) {
    paginationPage.value = 1;
    return;
  }

  scheduleFetchApplications();
});

watch(paginationPage, () => {
  if (applicationsFetchDebounceTimer) {
    clearTimeout(applicationsFetchDebounceTimer);
    applicationsFetchDebounceTimer = null;
  }

  fetchApplications();
});

watch(paginationTotalPages, (totalPages) => {
  if (paginationPage.value > totalPages) {
    paginationPage.value = totalPages;
  }

  if (paginationPage.value < 1) {
    paginationPage.value = 1;
  }
});

onMounted(async () => {
  await Promise.all([
    fetchApplications(),
    fetchVacancies(),
    fetchDepartments(),
  ]);
});
</script>
