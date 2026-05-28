<template>
    <Layout>
        <div class="max-w-7xl mx-auto py-8 px-4 space-y-6">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="space-y-2">
                    <router-link
                        :to="backRoute"
                        class="inline-flex items-center gap-2 text-sm font-medium text-[#005eb8] hover:underline"
                    >
                        <span>←</span>
                        <span>К списку заявок</span>
                    </router-link>
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold text-[#005eb8]">Заявка №{{ application?.id || route.params.id }}</h1>
                        <p
                            v-if="application"
                            class="text-sm text-gray-500"
                        >
                            {{ vacancyTypeLabel(application?.vacancy?.type) }} • {{ vacancyTitle(application) }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="application"
                    :class="summaryClass(application.status?.code) + ' inline-flex px-3 py-1 rounded-full text-sm font-medium'"
                >
                    {{ summaryLabel(application.status?.code) }}
                </div>
            </div>

            <div
                v-if="loading"
                class="text-center text-gray-500"
            >Загрузка...</div>
            <div
                v-else-if="!application"
                class="rounded-2xl border border-dashed border-gray-300 bg-white px-4 py-10 text-center text-gray-600"
            >
                {{ errorMessage || 'Заявка не найдена.' }}
            </div>

            <div
                v-else
                class="bg-white rounded-2xl shadow border border-gray-100 p-5 flex flex-col gap-5"
            >
                <div class="flex flex-col xl:flex-row gap-5">
                    <div class="xl:w-80 space-y-3">
                        <div>
                            <h2 class="text-lg font-semibold text-[#005eb8]">
                                Вакансия: {{ vacancyTitle(application) }}
                            </h2>
                            <p class="text-gray-500 text-sm">Тип вакансии: {{ vacancyTypeLabel(application?.vacancy?.type) }}</p>
                            <p class="text-gray-700 text-sm">Пользователь: {{ candidateDisplay(application.user) }}</p>
                            <p class="text-gray-700 text-sm">Телефон: {{ application.user?.phone || 'Не указан' }}</p>
                            <p
                                v-if="isPpsApplication && application?.pps_profile?.faculty_name"
                                class="text-gray-700 text-sm"
                            >
                                Факультет: {{ application.pps_profile.faculty_name }}
                            </p>
                            <p
                                v-if="isPpsApplication && application?.pps_profile?.department_name"
                                class="text-gray-700 text-sm"
                            >
                                Кафедра: {{ application.pps_profile.department_name }}
                            </p>
                            <p
                                v-if="application.hiring_status === 'hired' && application.hiring_term_years"
                                class="text-gray-700 text-sm"
                            >
                                Срок найма: {{ formatHiringTermLabel(application.hiring_term_years) }}
                            </p>
                            <p class="text-gray-500 text-sm">Дата подачи: {{ formatDate(application.created_at) }}</p>
                            <p class="text-gray-500 text-sm">ID кандидата: {{ application.user?.id }}</p>
                            <TeacherAuditLink
                                v-if="isPpsApplication"
                                :application="application"
                                class="mt-3"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div class="bg-blue-50 rounded px-2 py-1">Всего членов: <b>{{ application.vote_summary?.total_members ?? 0 }}</b></div>
                            <template v-if="isPpsApplication">
                                <div class="bg-emerald-50 rounded px-2 py-1">На 1 год: <b>{{ application.vote_summary?.hire_1_year ?? 0 }}</b></div>
                                <div class="bg-sky-50 rounded px-2 py-1">На 3 года: <b>{{ application.vote_summary?.hire_3_year ?? 0 }}</b></div>
                            </template>
                            <template v-else>
                                <div class="bg-emerald-50 rounded px-2 py-1">За: <b>{{ application.vote_summary?.hire ?? 0 }}</b></div>
                            </template>
                            <div class="bg-red-50 rounded px-2 py-1">{{ isPpsApplication ? 'Не брать' : 'Против' }}: <b>{{ application.vote_summary?.reject ?? 0 }}</b></div>
                            <div class="bg-gray-50 rounded px-2 py-1">Голосовали: <b>{{ application.vote_summary?.voted ?? 0 }}</b></div>
                            <div class="bg-amber-50 rounded px-2 py-1 col-span-2">Ожидают: <b>{{ application.vote_summary?.pending ?? 0 }}</b></div>
                        </div>

                        <div
                            v-if="(application.vote_details || []).length"
                            class="space-y-1"
                        >
                            <div class="text-xs font-semibold text-gray-600">Детали голосования:</div>
                            <div
                                v-for="vote in application.vote_details"
                                :key="`${application.id}-${vote.user_id}`"
                                class="text-xs bg-gray-50 border border-gray-100 rounded px-2 py-1.5"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="truncate text-gray-700">{{ vote.name }}</div>
                                        <div class="truncate text-gray-400">{{ vote.email }}</div>
                                    </div>
                                    <span
                                        class="font-semibold whitespace-nowrap px-2 py-0.5 rounded"
                                        :class="commissionVoteDecisionClass(vote.decision, vote.hire_term_years)"
                                    >
                                        {{ commissionVoteDecisionLabel(vote.decision, vote.hire_term_years, application?.vacancy?.type) }}
                                    </span>
                                </div>
                                <div
                                    v-if="vote.comment"
                                    class="mt-2 whitespace-pre-line rounded-md border border-gray-100 bg-white px-2 py-1.5 text-gray-600"
                                >
                                    {{ vote.comment }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 space-y-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <a
                                v-if="application.resume_url"
                                :href="application.resume_url"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center whitespace-nowrap border border-[#005eb8] text-[#005eb8] hover:bg-[#005eb8] hover:text-white px-3 py-2 rounded-lg text-sm font-medium transition"
                            >
                                Резюме
                            </a>

                            <template v-if="application.documents_map && Object.keys(application.documents_map).length">
                                <span
                                    v-for="item in orderedDocs(application.documents_map)"
                                    :key="item.type"
                                    :class="`inline-flex max-w-full items-stretch overflow-hidden rounded-lg border ${docPillClass(item.base)}`"
                                >
                                    <a
                                        :href="item.doc.url"
                                        target="_blank"
                                        rel="noopener"
                                        :download="`${item.type}-${application.id}`"
                                        :class="`inline-flex min-w-0 max-w-[18rem] items-center px-3 py-2 text-sm font-medium transition hover:bg-white/60 ${docTextClass(item.base)}`"
                                    >
                                        <span class="truncate">{{ docLabel(item.type) }}</span>
                                    </a>
                                    <button
                                        v-if="canDeleteApplicationDocuments && item.doc.id"
                                        type="button"
                                        :disabled="isDeletingApplicationDocument(item.doc.id)"
                                        :class="`inline-flex w-9 shrink-0 items-center justify-center border-l transition disabled:cursor-not-allowed disabled:opacity-60 ${docDeleteClass(item.base)}`"
                                        title="Удалить документ"
                                        aria-label="Удалить документ"
                                        @click="deleteApplicationDocument(item)"
                                    >
                                        <span
                                            v-if="isDeletingApplicationDocument(item.doc.id)"
                                            class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent"
                                        ></span>
                                        <svg
                                            v-else
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            class="h-4 w-4"
                                            aria-hidden="true"
                                        >
                                            <path d="M18 6 6 18" />
                                            <path d="m6 6 12 12" />
                                        </svg>
                                    </button>
                                </span>
                            </template>

                            <button
                                v-if="canManageApplicationDocuments"
                                type="button"
                                class="inline-flex items-center whitespace-nowrap bg-[#005eb8] hover:bg-blue-700 text-white px-3 py-2 rounded-lg text-sm font-medium transition"
                                @click="openUploadDocsModal"
                            >
                                {{ hasApplicationDocuments ? 'Дополнить документы' : 'Добавить документы за пользователя' }}
                            </button>
                        </div>

                        <section class="border border-gray-200 rounded-lg p-4 space-y-4">
                            <div>
                                <div class="text-sm font-semibold text-gray-800">Статусы заявки</div>
                                <p class="text-xs text-gray-500">Здесь можно изменить текущий этап: резюме, документы, проверка и итоговое решение.</p>
                            </div>

                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                <div
                                    v-for="stage in applicationStageOrder"
                                    :key="stage"
                                    class="rounded-2xl border border-gray-200 bg-slate-50 p-4 space-y-3"
                                >
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="text-sm font-semibold text-gray-800">{{ stageTitle(stage) }}</div>
                                        <span :class="stageClass(stage, application[stageStatusField(stage)], application) + ' px-2.5 py-1 rounded-full text-xs font-medium'">
                                            {{ stageLabel(stage, application[stageStatusField(stage)], application) }}
                                        </span>
                                    </div>

                                    <div class="space-y-2">
                                        <select
                                            v-model="stageDrafts[stage].status"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white"
                                        >
                                            <option
                                                v-for="option in stageOptions(stage)"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>

                                        <textarea
                                            v-model="stageDrafts[stage].comment"
                                            :placeholder="stagePlaceholder(stage)"
                                            rows="3"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white"
                                        ></textarea>

                                        <div class="flex items-center justify-between gap-3">
                                            <div class="text-xs text-gray-500 min-h-4">
                                                <template v-if="latestStageLog(application, stage)">
                                                    {{ formatDateTime(latestStageLog(application, stage).created_at) }}
                                                    <span v-if="latestStageLog(application, stage)?.author?.name">, {{ latestStageLog(application, stage).author.name }}</span>
                                                </template>
                                            </div>
                                            <button
                                                :disabled="Boolean(stageSavingByKey[stage])"
                                                class="inline-flex items-center justify-center bg-[#005eb8] hover:bg-blue-700 disabled:bg-gray-300 text-white text-sm font-semibold px-4 py-2 rounded-lg transition"
                                                @click="saveStage(stage)"
                                            >
                                                {{ stageSavingByKey[stage] ? 'Сохранение...' : 'Сохранить' }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <div
                            v-if="isStaffApplication"
                            class="border border-gray-200 rounded-lg p-4 space-y-4"
                        >
                            <div>
                                <div class="text-sm font-semibold text-gray-800">Данные кандидата ОУП</div>
                                <p class="text-xs text-gray-500">Основные данные заявки ОУП можно редактировать отдельно от профиля ППС.</p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <label class="space-y-2">
                                    <span class="text-sm font-medium text-gray-700">ФИО</span>
                                    <input
                                        v-model="staffDetailsDraft.full_name"
                                        type="text"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white"
                                        placeholder="ФИО кандидата"
                                    />
                                </label>

                                <label class="space-y-2">
                                    <span class="text-sm font-medium text-gray-700">Телефон</span>
                                    <input
                                        v-model="staffDetailsDraft.phone"
                                        type="text"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white"
                                        placeholder="Телефон кандидата"
                                    />
                                </label>

                                <label class="space-y-2 md:col-span-2">
                                    <span class="text-sm font-medium text-gray-700">Вакансия ОУП</span>
                                    <select
                                        v-model="staffDetailsDraft.vacancy_id"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white"
                                    >
                                        <option value="">Выберите вакансию</option>
                                        <option
                                            v-for="vacancy in staffVacancies"
                                            :key="vacancy.id"
                                            :value="String(vacancy.id)"
                                        >
                                            {{ vacancy.title }}
                                        </option>
                                    </select>
                                </label>
                            </div>

                            <div class="flex justify-end">
                                <button
                                    type="button"
                                    :disabled="staffDetailsSaving"
                                    class="inline-flex items-center justify-center rounded-lg bg-[#005eb8] px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:bg-gray-300"
                                    @click="saveStaffDetails"
                                >
                                    {{ staffDetailsSaving ? 'Сохранение...' : 'Сохранить данные ОУП' }}
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="isStaffApplication"
                            class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
                        >
                            <button
                                @click="openLawyerResponsePdf"
                                :disabled="!canGenerateLawyerPdf || pdfLoading"
                                class="inline-flex justify-center items-center whitespace-nowrap bg-slate-700 hover:bg-slate-800 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-semibold px-4 py-2 rounded-lg text-sm transition"
                            >
                                {{ pdfLoading ? 'Генерация PDF...' : 'Открыть PDF ответа юриста' }}
                            </button>
                        </div>

                        <div
                            v-if="isStaffApplication"
                            class="border border-gray-200 rounded-lg p-3 space-y-2"
                        >
                            <div class="text-sm font-semibold text-gray-700">Комиссия по вакансии</div>

                            <div
                                v-if="allCommissionMembers.length"
                                class="flex flex-wrap gap-2"
                            >
                                <span
                                    v-for="member in allCommissionMembers"
                                    :key="member.id"
                                    class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs"
                                >
                                    <span>{{ member.name }}</span>
                                    <button
                                        v-if="isVacancyCommissionMember(member)"
                                        @click="removeCommissionMember(member.id)"
                                        class="text-red-600 hover:text-red-700 font-semibold"
                                        title="Удалить"
                                    >
                                        ×
                                    </button>
                                </span>
                            </div>
                            <div
                                v-else
                                class="text-xs text-gray-500"
                            >Голосующие не назначены.</div>

                            <div class="flex flex-col md:flex-row gap-2">
                                <select
                                    v-model="memberToAdd"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                                >
                                    <option value="">Выберите пользователя</option>
                                    <option
                                        v-for="user in vacancyCandidates"
                                        :key="user.id"
                                        :value="user.id"
                                    >
                                        {{ candidateDisplay(user) }}
                                    </option>
                                </select>
                                <button
                                    @click="addCommissionMember"
                                    :disabled="!memberToAdd"
                                    class="bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-semibold px-4 py-2 rounded-lg text-sm transition"
                                >
                                    Добавить
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="isPpsApplication"
                            class="border border-gray-200 rounded-lg p-4 space-y-4"
                        >
                            <div class="flex flex-col gap-2">
                                <div>
                                    <div class="text-sm font-semibold text-gray-800">Данные преподавателя</div>
                                    <p class="text-xs text-gray-500">Подтверждающие документы для образования, магистратуры, ученой степени и ученого звания можно загрузить дополнительно.</p>
                                </div>
                                <!--
                  :disabled="ppsProfileSaving"
                  class="inline-flex items-center justify-center bg-[#005eb8] hover:bg-blue-700 disabled:bg-gray-300 text-white text-sm font-semibold px-4 py-2 rounded-lg transition"
                  @click="savePpsProfile"
                >
                  {{ ppsProfileSaving ? 'Сохранение...' : 'Сохранить данные ППС' }}
                -->
                            </div>

                            <div class="space-y-4">
                                <details
                                    open
                                    class="overflow-hidden rounded-xl border border-gray-200 bg-slate-50"
                                >
                                    <summary class="flex cursor-pointer items-start justify-between gap-3 px-4 py-3">
                                        <div>
                                            <div class="text-sm font-semibold text-gray-800">Основные данные преподавателя</div>
                                            <p class="mt-1 text-xs text-gray-500">ФИО, претендуемая должность, год рождения, образование и стаж работы.</p>
                                        </div>
                                        <span class="shrink-0 text-xs font-semibold uppercase tracking-wide text-[#005eb8]">Скрыть / показать</span>
                                    </summary>
                                    <div class="border-t border-gray-200 px-4 py-4">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <label class="space-y-2">
                                                <span class="text-sm font-medium text-gray-700">ФИО</span>
                                                <input
                                                    v-model="ppsProfileDraft.full_name"
                                                    type="text"
                                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white"
                                                    placeholder="ФИО преподавателя"
                                                />
                                            </label>

                                            <label class="space-y-2">
                                                <span class="text-sm font-medium text-gray-700">Претендуемая должность</span>
                                                <input
                                                    v-model="ppsProfileDraft.desired_position"
                                                    type="text"
                                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white"
                                                    placeholder="Например, старший преподаватель"
                                                />
                                            </label>

                                            <label class="space-y-2">
                                                <span class="text-sm font-medium text-gray-700">Факультет</span>
                                                <select
                                                    v-model="ppsProfileDraft.faculty_name"
                                                    :disabled="ppsFacultyOptions.length === 0"
                                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white disabled:bg-gray-100 disabled:text-gray-500"
                                                    @change="onPpsFacultyChange"
                                                >
                                                    <option value="">Выберите факультет</option>
                                                    <option
                                                        v-for="faculty in ppsFacultyOptions"
                                                        :key="faculty.id"
                                                        :value="faculty.name"
                                                    >
                                                        {{ faculty.name }}
                                                    </option>
                                                </select>
                                                <p
                                                    v-if="ppsFacultyOptions.length === 0"
                                                    class="text-xs text-amber-700"
                                                >
                                                    В справочнике нет факультетов.
                                                </p>
                                            </label>

                                            <label class="space-y-2">
                                                <span class="text-sm font-medium text-gray-700">Кафедра</span>
                                                <select
                                                    v-model="ppsProfileDraft.department_name"
                                                    :disabled="
                                                        !ppsProfileDraft.faculty_name ||
                                                        ppsDepartmentSelectOptions.length === 0
                                                    "
                                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white disabled:bg-gray-100 disabled:text-gray-500"
                                                >
                                                    <option value="">Выберите кафедру</option>
                                                    <option
                                                        v-for="department in ppsDepartmentSelectOptions"
                                                        :key="department.id"
                                                        :value="department.name"
                                                    >
                                                        {{ department.name }}
                                                    </option>
                                                </select>
                                                <p
                                                    v-if="
                                                        ppsProfileDraft.faculty_name &&
                                                        ppsDepartmentOptions.length === 0
                                                    "
                                                    class="text-xs text-amber-700"
                                                >
                                                    Для выбранного факультета в справочнике нет кафедр.
                                                </p>
                                            </label>

                                            <label class="space-y-2">
                                                <span class="text-sm font-medium text-gray-700">Год рождения</span>
                                                <input
                                                    v-model="ppsProfileDraft.birth_year"
                                                    type="number"
                                                    min="1900"
                                                    :max="currentYear"
                                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white"
                                                    placeholder="Например, 1988"
                                                />
                                            </label>

                                            <label class="space-y-2 md:col-span-2">
                                                <span class="text-sm font-medium text-gray-700">Стаж работы</span>
                                                <textarea
                                                    v-model="ppsProfileDraft.work_experience"
                                                    rows="3"
                                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white"
                                                    placeholder="Опишите стаж работы"
                                                ></textarea>
                                            </label>

                                            <div
                                                v-for="field in ppsDocumentFields"
                                                :key="field.key"
                                                class="space-y-3 md:col-span-2"
                                            >
                                                <label class="space-y-2 block">
                                                    <span class="text-sm font-medium text-gray-700">{{ field.label }}</span>
                                                    <textarea
                                                        v-model="ppsProfileDraft[field.key]"
                                                        rows="3"
                                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white"
                                                        :placeholder="field.placeholder"
                                                    ></textarea>
                                                </label>

                                                <div class="rounded-xl border border-dashed border-gray-300 bg-slate-50 px-3 py-3 space-y-3">
                                                    <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                                                        <div class="space-y-1">
                                                            <div class="text-xs font-medium text-gray-600">Подтверждающие документы</div>
                                                            <div class="text-xs text-gray-500">
                                                                {{ pendingPpsProfileFilesLabel(field.documentsField) }}
                                                            </div>
                                                        </div>

                                                        <input
                                                            type="file"
                                                            multiple
                                                            accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                                            class="block text-xs text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-[#005eb8] file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-blue-700"
                                                            @change="setPpsProfileFiles(field.documentsField, $event)"
                                                        />
                                                    </div>

                                                    <div
                                                        v-if="ppsProfileDraft[field.documentsField]?.length"
                                                        class="flex flex-wrap gap-2"
                                                    >
                                                        <span
                                                            v-for="(file, index) in ppsProfileDraft[field.documentsField]"
                                                            :key="`${field.documentsField}-pending-${index}-${file.name}`"
                                                            class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs text-amber-800"
                                                        >
                                                            <span class="max-w-[220px] truncate">{{ file.name }}</span>
                                                            <button
                                                                type="button"
                                                                class="font-semibold text-amber-700 hover:text-red-600"
                                                                @click="removePendingPpsProfileFile(field.documentsField, index)"
                                                            >
                                                                x
                                                            </button>
                                                        </span>
                                                    </div>

                                                    <div
                                                        v-if="ppsProfileDocuments(field.documentsField).length"
                                                        class="flex flex-wrap gap-2"
                                                    >
                                                        <span
                                                            v-for="document in ppsProfileDocuments(field.documentsField)"
                                                            :key="document.id"
                                                            class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs text-slate-700"
                                                        >
                                                            <a
                                                                :href="document.url"
                                                                target="_blank"
                                                                rel="noopener"
                                                                class="font-medium text-[#005eb8] hover:underline"
                                                            >
                                                                {{ document.name }}
                                                            </a>
                                                            <button
                                                                type="button"
                                                                class="font-semibold text-red-600 hover:text-red-700"
                                                                @click="deletePpsProfileDocument(document.id)"
                                                            >
                                                                Удалить
                                                            </button>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                        <div class="mt-4 flex justify-end">
                                            <button
                                                type="button"
                                                :disabled="ppsProfileSaving"
                                                class="inline-flex items-center justify-center rounded-lg bg-[#005eb8] px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:bg-gray-300"
                                                @click="saveMainPpsProfile"
                                            >
                                                {{ ppsProfileSavingSection === 'main' ? 'Сохранение...' : 'Сохранить секцию' }}
                                            </button>
                                        </div>
                                    </div>
                                </details>

                                <details class="overflow-hidden rounded-xl border border-gray-200 bg-slate-50">
                                    <summary class="flex cursor-pointer items-start justify-between gap-3 px-4 py-3">
                                        <div>
                                            <div class="text-sm font-semibold text-gray-800">Научные труды преподавателя</div>
                                            <p class="mt-1 text-xs text-gray-500">Отдельная секция для описания научных трудов и приложенных файлов.</p>
                                        </div>
                                        <span class="shrink-0 text-xs font-semibold uppercase tracking-wide text-[#005eb8]">Скрыть / показать</span>
                                    </summary>
                                    <div class="border-t border-gray-200 px-4 py-4">
                                        <div class="space-y-4">
                                            <label class="space-y-2 block">
                                                <span class="text-sm font-medium text-gray-700">Научные труды преподавателя</span>
                                                <textarea
                                                    v-model="ppsProfileDraft.scientific_works"
                                                    rows="4"
                                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white"
                                                    placeholder="Укажите научные труды преподавателя"
                                                ></textarea>
                                            </label>

                                            <div class="rounded-xl border border-dashed border-gray-300 bg-white/70 px-3 py-3 space-y-3">
                                                <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                                                    <div class="space-y-1">
                                                        <div class="text-xs font-medium text-gray-600">Подтверждающие документы</div>
                                                        <div class="text-xs text-gray-500">
                                                            {{ pendingPpsProfileFilesLabel('scientific_works_documents') }}
                                                        </div>
                                                    </div>

                                                    <input
                                                        type="file"
                                                        multiple
                                                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                                        class="block text-xs text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-[#005eb8] file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-blue-700"
                                                        @change="setPpsProfileFiles('scientific_works_documents', $event)"
                                                    />
                                                </div>

                                                <div
                                                    v-if="ppsProfileDraft.scientific_works_documents?.length"
                                                    class="flex flex-wrap gap-2"
                                                >
                                                    <span
                                                        v-for="(file, index) in ppsProfileDraft.scientific_works_documents"
                                                        :key="`scientific-works-pending-${index}-${file.name}`"
                                                        class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs text-amber-800"
                                                    >
                                                        <span class="max-w-[220px] truncate">{{ file.name }}</span>
                                                        <button
                                                            type="button"
                                                            class="font-semibold text-amber-700 hover:text-red-600"
                                                            @click="removePendingPpsProfileFile('scientific_works_documents', index)"
                                                        >
                                                            x
                                                        </button>
                                                    </span>
                                                </div>

                                                <div
                                                    v-if="ppsProfileDocuments('scientific_works_documents').length"
                                                    class="flex flex-wrap gap-2"
                                                >
                                                    <span
                                                        v-for="document in ppsProfileDocuments('scientific_works_documents')"
                                                        :key="document.id"
                                                        class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs text-slate-700"
                                                    >
                                                        <a
                                                            :href="document.url"
                                                            target="_blank"
                                                            rel="noopener"
                                                            class="font-medium text-[#005eb8] hover:underline"
                                                        >
                                                            {{ document.name }}
                                                        </a>
                                                        <button
                                                            type="button"
                                                            class="font-semibold text-red-600 hover:text-red-700"
                                                            @click="deletePpsProfileDocument(document.id)"
                                                        >
                                                            Удалить
                                                        </button>
                                                    </span>
                                                </div>
                                            </div>

                                            <div class="rounded-xl border border-gray-200 bg-white px-4 py-4 space-y-4">
                                                <label class="space-y-2 block">
                                                    <span class="text-sm font-medium text-gray-700">Наличие ЦОР / МООК</span>
                                                    <textarea
                                                        v-model="ppsProfileDraft.digital_mooc"
                                                        rows="4"
                                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white"
                                                        placeholder="Укажите сведения по ЦОР / МООК"
                                                    ></textarea>
                                                </label>

                                                <div class="rounded-xl border border-dashed border-gray-300 bg-slate-50 px-3 py-3 space-y-3">
                                                    <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                                                        <div class="space-y-1">
                                                            <div class="text-xs font-medium text-gray-600">Подтверждающие документы</div>
                                                            <div class="text-xs text-gray-500">
                                                                {{ pendingPpsProfileFilesLabel('digital_mooc_documents') }}
                                                            </div>
                                                        </div>

                                                        <input
                                                            type="file"
                                                            multiple
                                                            accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                                            class="block text-xs text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-[#005eb8] file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-blue-700"
                                                            @change="setPpsProfileFiles('digital_mooc_documents', $event)"
                                                        />
                                                    </div>

                                                    <div
                                                        v-if="ppsProfileDraft.digital_mooc_documents?.length"
                                                        class="flex flex-wrap gap-2"
                                                    >
                                                        <span
                                                            v-for="(file, index) in ppsProfileDraft.digital_mooc_documents"
                                                            :key="`digital-mooc-pending-${index}-${file.name}`"
                                                            class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs text-amber-800"
                                                        >
                                                            <span class="max-w-[220px] truncate">{{ file.name }}</span>
                                                            <button
                                                                type="button"
                                                                class="font-semibold text-amber-700 hover:text-red-600"
                                                                @click="removePendingPpsProfileFile('digital_mooc_documents', index)"
                                                            >
                                                                x
                                                            </button>
                                                        </span>
                                                    </div>

                                                    <div
                                                        v-if="ppsProfileDocuments('digital_mooc_documents').length"
                                                        class="flex flex-wrap gap-2"
                                                    >
                                                        <span
                                                            v-for="document in ppsProfileDocuments('digital_mooc_documents')"
                                                            :key="document.id"
                                                            class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs text-slate-700"
                                                        >
                                                            <a
                                                                :href="document.url"
                                                                target="_blank"
                                                                rel="noopener"
                                                                class="font-medium text-[#005eb8] hover:underline"
                                                            >
                                                                {{ document.name }}
                                                            </a>
                                                            <button
                                                                type="button"
                                                                class="font-semibold text-red-600 hover:text-red-700"
                                                                @click="deletePpsProfileDocument(document.id)"
                                                            >
                                                                Удалить
                                                            </button>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex justify-end">
                                            <button
                                                type="button"
                                                :disabled="ppsProfileSaving"
                                                class="inline-flex items-center justify-center rounded-lg bg-[#005eb8] px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:bg-gray-300"
                                                @click="saveScientificPpsProfile"
                                            >
                                                {{ ppsProfileSavingSection === 'scientific' ? 'Сохранение...' : 'Сохранить секцию' }}
                                            </button>
                                        </div>
                                    </div>
                                </details>

                                <details
                                    v-for="section in renderedPpsExtraSections"
                                    :key="section.key"
                                    class="overflow-hidden rounded-xl border border-gray-200 bg-slate-50"
                                >
                                    <summary class="flex cursor-pointer items-start justify-between gap-3 px-4 py-3">
                                        <div>
                                            <div class="text-sm font-semibold text-gray-800">{{ section.title }}</div>
                                            <p
                                                v-if="section.description"
                                                class="mt-1 text-xs text-gray-500"
                                            >{{ section.description }}</p>
                                        </div>
                                        <span class="shrink-0 text-xs font-semibold uppercase tracking-wide text-[#005eb8]">Скрыть / показать</span>
                                    </summary>
                                    <div class="border-t border-gray-200 px-4 py-4 space-y-4">
                                        <div :class="['grid grid-cols-1 gap-4', section.columnsClass]">
                                            <label
                                                v-for="field in section.fields"
                                                :key="field.key"
                                                class="space-y-2"
                                            >
                                                <span class="text-sm font-medium text-gray-700">{{ field.label }}</span>

                                                <input
                                                    v-if="field.type === 'input'"
                                                    v-model="ppsProfileDraft[field.key]"
                                                    type="text"
                                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white"
                                                    :placeholder="field.placeholder"
                                                />
                                                <textarea
                                                    v-else
                                                    v-model="ppsProfileDraft[field.key]"
                                                    :rows="field.rows || 4"
                                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white"
                                                    :placeholder="field.placeholder"
                                                ></textarea>
                                            </label>
                                        </div>

                                        <div
                                            v-if="section.documentField"
                                            class="rounded-xl border border-dashed border-gray-300 bg-white/70 px-3 py-3 space-y-3"
                                        >
                                            <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                                                <div class="space-y-1">
                                                    <div class="text-xs font-medium text-gray-600">Подтверждающие документы</div>
                                                    <div class="text-xs text-gray-500">
                                                        {{ pendingPpsProfileFilesLabel(section.documentField) }}
                                                    </div>
                                                </div>

                                                <input
                                                    type="file"
                                                    multiple
                                                    accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                                    class="block text-xs text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-[#005eb8] file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-blue-700"
                                                    @change="setPpsProfileFiles(section.documentField, $event)"
                                                />
                                            </div>

                                            <div
                                                v-if="ppsProfileDraft[section.documentField]?.length"
                                                class="flex flex-wrap gap-2"
                                            >
                                                <span
                                                    v-for="(file, index) in ppsProfileDraft[section.documentField]"
                                                    :key="`${section.documentField}-pending-${index}-${file.name}`"
                                                    class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs text-amber-800"
                                                >
                                                    <span class="max-w-[220px] truncate">{{ file.name }}</span>
                                                    <button
                                                        type="button"
                                                        class="font-semibold text-amber-700 hover:text-red-600"
                                                        @click="removePendingPpsProfileFile(section.documentField, index)"
                                                    >
                                                        x
                                                    </button>
                                                </span>
                                            </div>

                                            <div
                                                v-if="ppsProfileDocuments(section.documentField).length"
                                                class="flex flex-wrap gap-2"
                                            >
                                                <span
                                                    v-for="document in ppsProfileDocuments(section.documentField)"
                                                    :key="document.id"
                                                    class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs text-slate-700"
                                                >
                                                    <a
                                                        :href="document.url"
                                                        target="_blank"
                                                        rel="noopener"
                                                        class="font-medium text-[#005eb8] hover:underline"
                                                    >
                                                        {{ document.name }}
                                                    </a>
                                                    <button
                                                        type="button"
                                                        class="font-semibold text-red-600 hover:text-red-700"
                                                        @click="deletePpsProfileDocument(document.id)"
                                                    >
                                                        Удалить
                                                    </button>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex justify-end">
                                            <button
                                                type="button"
                                                :disabled="ppsProfileSaving"
                                                class="inline-flex items-center justify-center rounded-lg bg-[#005eb8] px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:bg-gray-300"
                                                @click="saveExtraPpsProfileSection(section)"
                                            >
                                                {{ ppsProfileSavingSection === section.key ? 'Сохранение...' : 'Сохранить секцию' }}
                                            </button>
                                        </div>
                                    </div>
                                </details>
                            </div>

                            <!--
                <div v-if="false">

                <div class="space-y-3 md:col-span-2 rounded-xl border border-gray-200 bg-slate-50 px-4 py-4">
                  <div>
                    <div class="text-sm font-medium text-gray-700">Научные труды преподавателя</div>
                    <div class="mt-2 text-sm text-gray-600 whitespace-pre-line">
                      {{ application.pps_profile?.scientific_works || 'Заполняет директор Департамента науки.' }}
                    </div>
                  </div>

                  <a
                    v-if="application.pps_profile?.scientific_works_document_url"
                    :href="application.pps_profile.scientific_works_document_url"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center text-sm font-medium text-[#005eb8] hover:underline"
                  >
                    Открыть документ по научным трудам
                  </a>
                </div>

                <div class="space-y-3 md:col-span-2 rounded-xl border border-gray-200 bg-slate-50 px-4 py-4">
                  <div>
                    <div class="text-sm font-medium text-gray-700">Наличие ЦОР / МООК</div>
                    <div class="mt-2 text-sm text-gray-600 whitespace-pre-line">
                      {{ application.pps_profile?.digital_mooc || 'Заполняет директор Центра искусственного интеллекта и цифрового развития.' }}
                    </div>
                  </div>

                  <div v-if="(application.pps_profile?.digital_mooc_documents || []).length" class="flex flex-wrap gap-2">
                    <a
                      v-for="document in application.pps_profile.digital_mooc_documents"
                      :key="document.id"
                      :href="document.url"
                      target="_blank"
                      rel="noopener"
                      class="inline-flex items-center text-sm font-medium text-[#005eb8] hover:underline"
                    >
                      {{ document.name }}
                    </a>
                  </div>
                </div>
                <div class="space-y-3 md:col-span-2 rounded-xl border border-gray-200 bg-slate-50 px-4 py-4">
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                      <div class="text-sm font-medium text-gray-700">Итоговый рейтинговый балл</div>
                      <div class="mt-2 text-sm text-gray-600 whitespace-pre-line">
                        {{ application.pps_profile?.final_rating_score || 'Заполняет директор Департамента стратегического развития.' }}
                      </div>
                    </div>

                    <div>
                      <div class="text-sm font-medium text-gray-700">Невыполнение индивидуального плана</div>
                      <div class="mt-2 text-sm text-gray-600 whitespace-pre-line">
                        {{ application.pps_profile?.individual_plan_nonfulfillment || 'Заполняет директор Департамента стратегического развития.' }}
                      </div>
                    </div>

                    <div>
                      <div class="text-sm font-medium text-gray-700">КРК</div>
                      <div class="mt-2 text-sm text-gray-600 whitespace-pre-line">
                        {{ application.pps_profile?.krk || 'Заполняет директор Департамента стратегического развития.' }}
                      </div>
                    </div>
                  </div>

                  <div v-if="(application.pps_profile?.strategy_documents || []).length" class="flex flex-wrap gap-2">
                    <a
                      v-for="document in application.pps_profile.strategy_documents"
                      :key="document.id"
                      :href="document.url"
                      target="_blank"
                      rel="noopener"
                      class="inline-flex items-center text-sm font-medium text-[#005eb8] hover:underline"
                    >
                      {{ document.name }}
                    </a>
                  </div>
                </div>
                <div class="space-y-3 md:col-span-2 rounded-xl border border-gray-200 bg-slate-50 px-4 py-4">
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                      <div class="text-sm font-medium text-gray-700">Оценка качества проведения открытого занятия</div>
                      <div class="mt-2 text-sm text-gray-600 whitespace-pre-line">
                        {{ application.pps_profile?.open_lesson_quality || 'Заполняет директор Департамента академического развития.' }}
                      </div>
                    </div>

                    <div>
                      <div class="text-sm font-medium text-gray-700">Преподаваемые дисциплины</div>
                      <div class="mt-2 text-sm text-gray-600 whitespace-pre-line">
                        {{ application.pps_profile?.taught_disciplines || 'Заполняет директор Департамента академического развития.' }}
                      </div>
                    </div>

                    <div>
                      <div class="text-sm font-medium text-gray-700">Учебно-методическая литература</div>
                      <div class="mt-2 text-sm text-gray-600 whitespace-pre-line">
                        {{ application.pps_profile?.educational_methodical_literature || 'Заполняет директор Департамента академического развития.' }}
                      </div>
                    </div>
                  </div>

                  <div v-if="(application.pps_profile?.academic_documents || []).length" class="flex flex-wrap gap-2">
                    <a
                      v-for="document in application.pps_profile.academic_documents"
                      :key="document.id"
                      :href="document.url"
                      target="_blank"
                      rel="noopener"
                      class="inline-flex items-center text-sm font-medium text-[#005eb8] hover:underline"
                    >
                      {{ document.name }}
                    </a>
                  </div>
                </div>

                <div class="space-y-3 md:col-span-2 rounded-xl border border-gray-200 bg-slate-50 px-4 py-4">
                  <div>
                    <div class="text-sm font-medium text-gray-700">Показатели учебных изданий</div>
                    <div class="mt-2 text-sm text-gray-600 whitespace-pre-line">
                      {{ application.pps_profile?.educational_publication_metrics || 'Заполняет директор Научной библиотеки.' }}
                    </div>
                  </div>

                  <div v-if="(application.pps_profile?.library_documents || []).length" class="flex flex-wrap gap-2">
                    <a
                      v-for="document in application.pps_profile.library_documents"
                      :key="document.id"
                      :href="document.url"
                      target="_blank"
                      rel="noopener"
                      class="inline-flex items-center text-sm font-medium text-[#005eb8] hover:underline"
                    >
                      {{ document.name }}
                    </a>
                  </div>
                </div>
                <div class="space-y-3 md:col-span-2 rounded-xl border border-gray-200 bg-slate-50 px-4 py-4">
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                      <div class="text-sm font-medium text-gray-700">Результаты анкетирования по вопросам противодействия коррупции</div>
                      <div class="mt-2 text-sm text-gray-600 whitespace-pre-line">
                        {{ application.pps_profile?.anti_corruption_survey_results || 'Заполняет директор Департамента правового обеспечения и комплаенса.' }}
                      </div>
                    </div>

                    <div>
                      <div class="text-sm font-medium text-gray-700">Сведения о дисциплинарных взысканиях</div>
                      <div class="mt-2 text-sm text-gray-600 whitespace-pre-line">
                        {{ application.pps_profile?.disciplinary_actions_info || 'Заполняет директор Департамента правового обеспечения и комплаенса.' }}
                      </div>
                    </div>
                  </div>

                  <div v-if="(application.pps_profile?.compliance_documents || []).length" class="flex flex-wrap gap-2">
                    <a
                      v-for="document in application.pps_profile.compliance_documents"
                      :key="document.id"
                      :href="document.url"
                      target="_blank"
                      rel="noopener"
                      class="inline-flex items-center text-sm font-medium text-[#005eb8] hover:underline"
                    >
                      {{ document.name }}
                    </a>
                  </div>
                </div>
              </div>
            </div>
                </div>

                -->
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <button
                                    @click="openLawyerResponsePdf"
                                    :disabled="!canGenerateLawyerPdf || pdfLoading"
                                    class="inline-flex justify-center items-center whitespace-nowrap bg-slate-700 hover:bg-slate-800 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-semibold px-4 py-2 rounded-lg text-sm transition"
                                >
                                    {{ pdfLoading ? 'Генерация PDF...' : 'Открыть PDF ответа юриста' }}
                                </button>
                            </div>

                            <div class="border border-gray-200 rounded-lg p-3 space-y-2">
                                <div class="text-sm font-semibold text-gray-700">Комиссия по вакансии</div>

                                <div
                                    v-if="allCommissionMembers.length"
                                    class="flex flex-wrap gap-2"
                                >
                                    <span
                                        v-for="member in allCommissionMembers"
                                        :key="member.id"
                                        class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs"
                                    >
                                        <span>{{ member.name }}</span>
                                        <button
                                            v-if="isVacancyCommissionMember(member)"
                                            @click="removeCommissionMember(member.id)"
                                            class="text-red-600 hover:text-red-700 font-semibold"
                                            title="Удалить"
                                        >
                                            ×
                                        </button>
                                    </span>
                                </div>
                                <div
                                    v-else
                                    class="text-xs text-gray-500"
                                >Голосующие не назначены.</div>

                                <div class="flex flex-col md:flex-row gap-2">
                                    <select
                                        v-model="memberToAdd"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                                    >
                                        <option value="">Выберите пользователя</option>
                                        <option
                                            v-for="user in vacancyCandidates"
                                            :key="user.id"
                                            :value="user.id"
                                        >
                                            {{ candidateDisplay(user) }}
                                        </option>
                                    </select>
                                    <button
                                        @click="addCommissionMember"
                                        :disabled="!memberToAdd"
                                        class="bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-semibold px-4 py-2 rounded-lg text-sm transition"
                                    >
                                        Добавить
                                    </button>
                                </div>
                            </div>

                            <div class="border border-gray-200 rounded-lg p-3 space-y-2">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                    <div class="text-sm font-semibold text-gray-700">AI-анализ кандидата</div>
                                    <button
                                        @click="generateCandidateAI"
                                        :disabled="aiLoading"
                                        class="bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-semibold px-4 py-2 rounded-lg text-sm transition"
                                    >
                                        {{ aiLoading ? 'Генерация...' : 'Сгенерировать' }}
                                    </button>
                                </div>

                                <p
                                    v-if="aiError"
                                    class="text-sm text-red-700"
                                >{{ aiError }}</p>

                                <template v-if="aiResult">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                                        <div class="bg-indigo-50 rounded px-2 py-1">Итоговый балл: <b>{{ aiResult.score ?? '-' }}</b></div>
                                        <div class="bg-indigo-50 rounded px-2 py-1">Решение: <b>{{ aiResult.decision || '-' }}</b></div>
                                        <div class="bg-indigo-50 rounded px-2 py-1">Образование: <b>{{ aiResult.education_match ?? '-' }}</b></div>
                                        <div class="bg-indigo-50 rounded px-2 py-1">Опыт: <b>{{ aiResult.experience_match ?? '-' }}</b></div>
                                        <div class="bg-indigo-50 rounded px-2 py-1 col-span-1 sm:col-span-2">Soft skills: <b>{{ aiResult.soft_skills_match ?? '-' }}</b></div>
                                    </div>
                                    <div class="text-sm text-gray-700 whitespace-pre-line">{{ aiResult.summary }}</div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <UploadDocsModal
                    v-if="showUploadDocsModal && application"
                    :application="application"
                    :mode="uploadDocsModalMode"
                    :upload-url="adminUploadDocsUrl"
                    @close="closeUploadDocsModal"
                    @saved="() => closeUploadDocsModal(true)"
                />
            </div>
        </div>
    </Layout>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute } from "vue-router";
import axios from "axios";
import Layout from "../components/Layout.vue";
import TeacherAuditLink from "../components/TeacherAuditLink.vue";
import UploadDocsModal from "../components/UploadDocsModal.vue";
import { useDialogStore } from "../stores/useDialogStore";
import {
    applicationStageOrder,
    latestStageLog,
    normalizedStageStatus,
    stageClass,
    stageCommentField,
    stageLabel,
    stageOptions,
    stagePlaceholder,
    stageStatusField,
    stageTitle,
    summaryClass,
    summaryLabel,
} from "../utils/applicationStages";
import { vacancyTypeLabel } from "../utils/vacancyTypes";
import {
    hiringTermLabel as formatHiringTermLabel,
    voteDecisionClass as commissionVoteDecisionClass,
    voteDecisionLabel as commissionVoteDecisionLabel,
} from "../utils/commissionVotes";

const route = useRoute();
const dialogStore = useDialogStore();

const application = ref(null);
const loading = ref(true);
const errorMessage = ref("");
const vacancyCandidates = ref([]);
const memberToAdd = ref("");
const staffVacancies = ref([]);
const departments = ref([]);
const aiResult = ref(null);
const aiLoading = ref(false);
const aiError = ref("");
const pdfLoading = ref(false);
const staffDetailsSaving = ref(false);
const ppsProfileSaving = ref(false);
const ppsProfileSavingSection = ref("");
const stageDrafts = ref({});
const stageSavingByKey = ref({});
const showUploadDocsModal = ref(false);
const uploadDocsModalMode = ref("create");
const deletingApplicationDocumentIds = ref([]);
const currentYear = new Date().getFullYear();

const backRoute = computed(() => ({
    name: "AdminApplications",
    query: route.query.type ? { type: String(route.query.type) } : {},
}));

const isTechnicalCandidateEmail = (email) =>
    String(email || "")
        .toLowerCase()
        .endsWith("@hr-ai.invalid");
const candidateDisplay = (user) => {
    const name = user?.name || "Кандидат не указан";
    const email = String(user?.email || "").trim();

    return email && !isTechnicalCandidateEmail(email)
        ? `${name} (${email})`
        : name;
};

const vacancyId = computed(
    () =>
        application.value?.vacancy?.id || application.value?.vacancy_id || null
);
const vacancyMembers = computed(
    () => application.value?.vacancy?.commission_members || []
);
const vacancyMemberIds = computed(
    () => new Set(vacancyMembers.value.map((member) => Number(member.id)))
);
const allCommissionMembers = computed(() => {
    const members = [
        ...(application.value?.vote_details || []).map((vote) => ({
            id: vote.user_id,
            name: vote.name,
            email: vote.email,
        })),
        ...vacancyMembers.value.map((member) => ({
            id: member.id,
            name: member.name,
            email: member.email,
        })),
    ];
    const seen = new Set();

    return members.filter((member) => {
        const id = Number(member?.id);
        if (!id || seen.has(id)) {
            return false;
        }

        seen.add(id);
        return true;
    });
});
const isVacancyCommissionMember = (member) =>
    vacancyMemberIds.value.has(Number(member?.id));
const canGenerateLawyerPdf = computed(() =>
    ["clear", "flagged"].includes(application.value?.compliance_status)
);
const isPpsApplication = computed(
    () => application.value?.vacancy?.type === "pps"
);
const isStaffApplication = computed(
    () => application.value?.vacancy?.type === "staff"
);
const hasApplicationDocuments = computed(
    () =>
        Boolean(application.value?.documents_map) &&
        Object.keys(application.value.documents_map).length > 0
);
const canManageApplicationDocuments = computed(
    () =>
        application.value?.resume_status === "accepted" &&
        ["awaiting_upload", "uploaded", "rejected"].includes(
            application.value?.documents_status
        ) &&
        application.value?.compliance_status === "not_started" &&
        !["hired", "rejected"].includes(application.value?.hiring_status)
);
const canDeleteApplicationDocuments = computed(
    () =>
        Boolean(application.value) &&
        !application.value?.archived_at &&
        !["hired", "rejected"].includes(application.value?.hiring_status)
);
const adminUploadDocsUrl = computed(() =>
    application.value
        ? `/api/admin/applications/${application.value.id}/upload-docs`
        : ""
);
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
        items.sort((left, right) =>
            String(left?.name || "").localeCompare(
                String(right?.name || ""),
                "ru"
            )
        );
    }

    return grouped;
});
const hasDescribedFaculties = computed(() =>
    (departments.value || []).some(
        (department) =>
            String(department?.description || "")
                .trim()
                .toLowerCase() === "факультет"
    )
);
const ppsFacultyOptions = computed(() =>
    (departments.value || [])
        .filter(
            (department) =>
                !department?.parent_id &&
                (hasDescribedFaculties.value
                    ? String(department?.description || "")
                          .trim()
                          .toLowerCase() === "факультет"
                    : departmentChildrenByParentId.value.has(
                          Number(department?.id)
                      ))
        )
        .slice()
        .sort((left, right) =>
            String(left?.name || "").localeCompare(
                String(right?.name || ""),
                "ru"
            )
        )
);
const selectedPpsFaculty = computed(() =>
    ppsFacultyOptions.value.find(
        (faculty) => faculty.name === ppsProfileDraft.value.faculty_name
    )
);
const hasDescribedChairs = computed(() =>
    (departments.value || []).some(
        (department) =>
            String(department?.description || "")
                .trim()
                .toLowerCase() === "кафедра"
    )
);
const isChairDepartment = (department) => {
    const description = String(department?.description || "")
        .trim()
        .toLowerCase();
    const name = String(department?.name || "")
        .trim()
        .toLowerCase();

    return description === "кафедра" || name.startsWith("кафедра");
};
const ppsDepartmentOptions = computed(() => {
    if (!selectedPpsFaculty.value) {
        return [];
    }

    return (
        departmentChildrenByParentId.value.get(
            Number(selectedPpsFaculty.value.id)
        ) || []
    )
        .filter((department) =>
            hasDescribedChairs.value ? isChairDepartment(department) : true
        )
        .slice()
        .sort((left, right) =>
            String(left?.name || "").localeCompare(
                String(right?.name || ""),
                "ru"
            )
        );
});
const ppsDepartmentSelectOptions = computed(() => {
    if (!ppsProfileDraft.value.faculty_name) {
        return [];
    }

    const options = ppsDepartmentOptions.value.map((department) => ({
        id: department.id,
        name: department.name,
    }));
    const currentDepartmentName = String(
        ppsProfileDraft.value.department_name || ""
    ).trim();

    if (
        currentDepartmentName &&
        !options.some((department) => department.name === currentDepartmentName)
    ) {
        return [
            {
                id: `current-${currentDepartmentName}`,
                name: currentDepartmentName,
            },
            ...options,
        ];
    }

    return options;
});
const onPpsFacultyChange = () => {
    ppsProfileDraft.value.department_name = "";
};
const openUploadDocsModal = () => {
    uploadDocsModalMode.value = hasApplicationDocuments.value
        ? "replace"
        : "create";
    showUploadDocsModal.value = true;
};

const closeUploadDocsModal = async (refresh = false) => {
    showUploadDocsModal.value = false;

    if (refresh) {
        await fetchApplication();
    }
};

const emptyStaffDetailsDraft = () => ({
    full_name: "",
    phone: "",
    vacancy_id: "",
});

const staffDetailsDraft = ref(emptyStaffDetailsDraft());

const emptyPpsProfileDraft = () => ({
    full_name: "",
    desired_position: "",
    faculty_name: "",
    department_name: "",
    birth_year: "",
    basic_education: "",
    magistracy: "",
    scientific_degree: "",
    academic_title: "",
    work_experience: "",
    scientific_works: "",
    digital_mooc: "",
    final_rating_score: "",
    student_survey_results: "",
    individual_plan_nonfulfillment: "",
    krk: "",
    open_lesson_quality: "",
    taught_disciplines: "",
    educational_methodical_literature: "",
    educational_publication_metrics: "",
    anti_corruption_survey_results: "",
    disciplinary_actions_info: "",
    basic_education_documents: [],
    magistracy_documents: [],
    scientific_degree_documents: [],
    academic_title_documents: [],
    scientific_works_documents: [],
    digital_mooc_documents: [],
});

const ppsProfileDraft = ref(emptyPpsProfileDraft());

const ppsDocumentFields = [
    {
        key: "basic_education",
        label: "Базовое образование",
        placeholder: "Укажите базовое образование",
        documentsField: "basic_education_documents",
    },
    {
        key: "magistracy",
        label: "Магистратура",
        placeholder: "Укажите сведения о магистратуре",
        documentsField: "magistracy_documents",
    },
    {
        key: "scientific_degree",
        label: "Ученая степень",
        placeholder: "Укажите ученую степень",
        documentsField: "scientific_degree_documents",
    },
    {
        key: "academic_title",
        label: "Ученое звание",
        placeholder: "Укажите ученое звание",
        documentsField: "academic_title_documents",
    },
];

const ppsMainFieldKeys = [
    "full_name",
    "desired_position",
    "faculty_name",
    "department_name",
    "birth_year",
    "work_experience",
    ...ppsDocumentFields.map((field) => field.key),
];

const ppsMainDocumentFields = ppsDocumentFields.map(
    (field) => field.documentsField
);

const ppsExtraSections = [
    {
        key: "digital",
        title: "Наличие ЦОР / МООК",
        description:
            "Блок директора Центра искусственного интеллекта и цифрового развития.",
        columnsClass: "md:grid-cols-1",
        documentField: "digital_mooc_documents",
        fields: [
            {
                key: "digital_mooc",
                label: "Наличие ЦОР / МООК",
                placeholder: "Укажите сведения по ЦОР / МООК",
                rows: 4,
            },
        ],
    },
    {
        key: "strategy",
        title: "Показатели стратегического развития",
        description:
            "Итоговый рейтинг, анкетирование студентов, индивидуальный план и КРК.",
        columnsClass: "md:grid-cols-1",
        fields: [
            {
                key: "final_rating_score",
                label: "Итоговый рейтинговый балл",
                placeholder: "Укажите итоговый рейтинговый балл",
                type: "input",
            },
            {
                key: "student_survey_results",
                label: "Результаты анкетирования студентов о деятельности ППС",
                placeholder:
                    "Укажите результаты анкетирования студентов о деятельности ППС",
                rows: 4,
            },
            {
                key: "individual_plan_nonfulfillment",
                label: "Невыполнение индивидуального плана",
                placeholder:
                    "Укажите сведения по невыполнению индивидуального плана",
                rows: 4,
            },
            {
                key: "krk",
                label: "КРК",
                placeholder: "Укажите сведения по КРК",
                rows: 4,
            },
        ],
    },
    {
        key: "academic",
        title: "Академическое развитие",
        description:
            "Открытое занятие, дисциплины и учебно-методическая литература.",
        columnsClass: "md:grid-cols-1",
        fields: [
            {
                key: "open_lesson_quality",
                label: "Оценка качества проведения открытого занятия",
                placeholder:
                    "Укажите оценку качества проведения открытого занятия",
                rows: 4,
            },
            {
                key: "taught_disciplines",
                label: "Преподаваемые дисциплины",
                placeholder: "Укажите преподаваемые дисциплины",
                rows: 4,
            },
            {
                key: "educational_methodical_literature",
                label: "Учебно-методическая литература",
                placeholder:
                    "Укажите сведения по учебно-методической литературе",
                rows: 4,
            },
        ],
    },
    {
        key: "library",
        title: "Показатели учебных изданий",
        description: "Данные, которые заполняет Научная библиотека.",
        columnsClass: "md:grid-cols-1",
        fields: [
            {
                key: "educational_publication_metrics",
                label: "Показатели учебных изданий",
                placeholder: "Укажите показатели учебных изданий",
                rows: 4,
            },
        ],
    },
    {
        key: "compliance",
        title: "Комплаенс и дисциплинарные сведения",
        description:
            "Анкетирование по противодействию коррупции и дисциплинарные взыскания.",
        columnsClass: "md:grid-cols-1",
        fields: [
            {
                key: "anti_corruption_survey_results",
                label: "Результаты анкетирования по вопросам противодействия коррупции",
                placeholder: "Укажите результаты анкетирования",
                rows: 4,
            },
            {
                key: "disciplinary_actions_info",
                label: "Сведения о дисциплинарных взысканиях",
                placeholder: "Укажите сведения о дисциплинарных взысканиях",
                rows: 4,
            },
        ],
    },
];

const renderedPpsExtraSections = computed(() =>
    ppsExtraSections.filter((section) => section.key !== "digital")
);

const docLabels = {
    diploma: "Дипломы и сертификаты",
    recommendation_letter: "Рекомендательное письмо",
    scientific_works: "Список научных трудов",
    other: "Другое",
    articles: "Список научных трудов",
};

const parseDocType = (type) => {
    const raw = String(type);
    const match = raw.match(/^(.*)_(\d+)$/);
    if (!match) return { base: raw, index: null };
    return { base: match[1], index: Number(match[2]) };
};

const normalizeBase = (type) => {
    const base = String(type).replace(/_\d+$/, "");
    return base === "articles" ? "scientific_works" : base;
};

const docOrder = {
    diploma: 1,
    recommendation_letter: 2,
    scientific_works: 3,
    other: 4,
};

const orderedDocs = (documentsMap) =>
    Object.entries(documentsMap || {})
        .map(([type, doc]) => {
            const parsed = parseDocType(type);
            const base = normalizeBase(parsed.base);
            const index = parsed.index || (docOrder[base] ? 1 : 0);
            return { type, doc, base, index };
        })
        .sort(
            (a, b) =>
                (docOrder[a.base] || 99) - (docOrder[b.base] || 99) ||
                a.index - b.index
        );

const docPillClass = (base) => {
    if (base === "diploma") return "border-sky-200 bg-sky-50";
    if (base === "recommendation_letter") return "border-amber-200 bg-amber-50";
    if (base === "scientific_works") return "border-cyan-200 bg-cyan-50";
    return "border-emerald-200 bg-emerald-50";
};

const docTextClass = (base) => {
    if (base === "diploma") return "text-sky-800";
    if (base === "recommendation_letter") return "text-amber-800";
    if (base === "scientific_works") return "text-cyan-800";
    return "text-emerald-800";
};

const docDeleteClass = (base) => {
    if (base === "diploma") return "border-sky-200 text-sky-700 hover:bg-sky-100 hover:text-red-700";
    if (base === "recommendation_letter") return "border-amber-200 text-amber-700 hover:bg-amber-100 hover:text-red-700";
    if (base === "scientific_works") return "border-cyan-200 text-cyan-700 hover:bg-cyan-100 hover:text-red-700";
    return "border-emerald-200 text-emerald-700 hover:bg-emerald-100 hover:text-red-700";
};

const docLabel = (type) => {
    const parsed = parseDocType(type);
    const normalizedBase = normalizeBase(parsed.base);
    const base = docLabels[normalizedBase] || parsed.base;

    if (
        [
            "diploma",
            "recommendation_letter",
            "scientific_works",
            "other",
        ].includes(normalizedBase)
    ) {
        return `${base} #${parsed.index || 1}`;
    }

    return parsed.index ? `${base} #${parsed.index}` : base;
};

const isDeletingApplicationDocument = (documentId) =>
    deletingApplicationDocumentIds.value.includes(Number(documentId));

const deleteApplicationDocument = async (item) => {
    const documentId = Number(item?.doc?.id);
    if (!application.value || !documentId) return;

    const confirmed = await dialogStore.openConfirm(
        `Удалить документ "${docLabel(item.type)}"?`,
        {
            title: "Удалить документ",
            confirmText: "Удалить",
            cancelText: "Отмена",
        }
    );

    if (!confirmed) {
        return;
    }

    deletingApplicationDocumentIds.value = [
        ...deletingApplicationDocumentIds.value,
        documentId,
    ];

    try {
        const response = await axios.delete(
            `/api/admin/applications/${application.value.id}/documents/${documentId}`
        );
        application.value = response.data.application;
        syncStageDrafts(application.value);
        syncPpsProfileDraft(application.value);
        syncStaffDetailsDraft(application.value);
        aiResult.value = application.value.ai_result || null;
    } catch (error) {
        alert(
            error?.response?.data?.message ||
                "Не удалось удалить документ."
        );
    } finally {
        deletingApplicationDocumentIds.value =
            deletingApplicationDocumentIds.value.filter(
                (id) => id !== documentId
            );
    }
};

const voteDecisionLabel = (decision) => {
    if (decision === "hire") return "За";
    if (decision === "reject") return "Против";
    return "Не голосовал";
};

const voteDecisionClass = (decision) => {
    if (decision === "hire") return "bg-emerald-100 text-emerald-700";
    if (decision === "reject") return "bg-red-100 text-red-700";
    return "bg-gray-200 text-gray-700";
};

const vacancyTitle = (item) => item?.vacancy?.title || "Без названия";

const formatDate = (value) => {
    if (!value) return "Не указана";

    return new Date(value).toLocaleDateString("ru-RU", {
        year: "numeric",
        month: "long",
        day: "numeric",
    });
};

const formatDateTime = (value) => {
    if (!value) return "";

    return new Date(value).toLocaleString("ru-RU", {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
};

const syncStageDrafts = (item) => {
    stageDrafts.value = Object.fromEntries(
        applicationStageOrder.map((stage) => [
            stage,
            {
                status: normalizedStageStatus(
                    stage,
                    item?.[stageStatusField(stage)] ||
                        stageOptions(stage)[0]?.value ||
                        "",
                    item
                ),
                comment: item?.[stageCommentField(stage)] || "",
            },
        ])
    );
};

const ppsProfileFieldValue = (item, field) => {
    const profile = item?.pps_profile;

    if (field === "full_name") {
        return profile?.full_name || item?.user?.name || "";
    }

    if (field === "desired_position") {
        return profile?.desired_position || item?.vacancy?.title || "";
    }

    return profile?.[field] || "";
};

const syncPpsProfileDraft = (item) => {
    ppsProfileDraft.value = {
        ...emptyPpsProfileDraft(),
        full_name: ppsProfileFieldValue(item, "full_name"),
        desired_position: ppsProfileFieldValue(item, "desired_position"),
        faculty_name: ppsProfileFieldValue(item, "faculty_name"),
        department_name: ppsProfileFieldValue(item, "department_name"),
        birth_year: ppsProfileFieldValue(item, "birth_year"),
        basic_education: ppsProfileFieldValue(item, "basic_education"),
        magistracy: ppsProfileFieldValue(item, "magistracy"),
        scientific_degree: ppsProfileFieldValue(item, "scientific_degree"),
        academic_title: ppsProfileFieldValue(item, "academic_title"),
        work_experience: ppsProfileFieldValue(item, "work_experience"),
        scientific_works: ppsProfileFieldValue(item, "scientific_works"),
        digital_mooc: ppsProfileFieldValue(item, "digital_mooc"),
        final_rating_score: ppsProfileFieldValue(item, "final_rating_score"),
        student_survey_results: ppsProfileFieldValue(
            item,
            "student_survey_results"
        ),
        individual_plan_nonfulfillment: ppsProfileFieldValue(
            item,
            "individual_plan_nonfulfillment"
        ),
        krk: ppsProfileFieldValue(item, "krk"),
        open_lesson_quality: ppsProfileFieldValue(item, "open_lesson_quality"),
        taught_disciplines: ppsProfileFieldValue(item, "taught_disciplines"),
        educational_methodical_literature: ppsProfileFieldValue(
            item,
            "educational_methodical_literature"
        ),
        educational_publication_metrics: ppsProfileFieldValue(
            item,
            "educational_publication_metrics"
        ),
        anti_corruption_survey_results: ppsProfileFieldValue(
            item,
            "anti_corruption_survey_results"
        ),
        disciplinary_actions_info: ppsProfileFieldValue(
            item,
            "disciplinary_actions_info"
        ),
    };
};

const syncStaffDetailsDraft = (item) => {
    staffDetailsDraft.value = {
        ...emptyStaffDetailsDraft(),
        full_name: item?.user?.name || "",
        phone: item?.user?.phone || "",
        vacancy_id: item?.vacancy?.id ? String(item.vacancy.id) : "",
    };
};

const applySavedPpsProfileSection = (item, fieldKeys, documentFields = []) => {
    for (const field of fieldKeys) {
        ppsProfileDraft.value[field] = ppsProfileFieldValue(item, field);
    }

    for (const field of documentFields) {
        ppsProfileDraft.value[field] = [];
    }
};

const setPpsProfileFiles = (field, event) => {
    const files = Array.from(event.target.files || []);
    if (!files.length) {
        return;
    }

    ppsProfileDraft.value[field] = [
        ...(ppsProfileDraft.value[field] || []),
        ...files,
    ];

    event.target.value = "";
};

const removePendingPpsProfileFile = (field, index) => {
    ppsProfileDraft.value[field] = (ppsProfileDraft.value[field] || []).filter(
        (_, fileIndex) => fileIndex !== index
    );
};

const pendingPpsProfileFilesLabel = (field) => {
    const files = ppsProfileDraft.value[field] || [];

    if (!files.length) {
        return "Новые документы не выбраны";
    }

    return `Новых файлов: ${files.length}`;
};

const ppsProfileDocuments = (field) =>
    application.value?.pps_profile?.[field] || [];

const deletePpsProfileDocument = async (documentId) => {
    if (!application.value) return;
    if (!confirm("Удалить документ профиля ППС?")) return;

    try {
        const response = await axios.delete(
            `/api/admin/applications/${application.value.id}/pps-profile/documents/${documentId}`
        );
        application.value = response.data.application;
    } catch (error) {
        alert(
            error?.response?.data?.message ||
                "Ошибка при удалении документа профиля ППС."
        );
    }
};

const loadVacancyCandidates = async () => {
    if (!vacancyId.value) {
        vacancyCandidates.value = [];
        return;
    }

    try {
        const response = await axios.get(
            `/api/admin/vacancies/${vacancyId.value}/commission-candidates`
        );
        vacancyCandidates.value = response.data;
    } catch (error) {
        vacancyCandidates.value = [];
        console.error(error);
    }
};

const fetchStaffVacancies = async () => {
    try {
        const response = await axios.get("/api/admin/vacancies");
        staffVacancies.value = (response.data || [])
            .filter((vacancy) => vacancy?.type === "staff")
            .slice()
            .sort((left, right) =>
                String(left?.title || "").localeCompare(
                    String(right?.title || ""),
                    "ru"
                )
            );
    } catch (error) {
        staffVacancies.value = [];
        console.error(error);
    }
};

const fetchDepartments = async () => {
    try {
        const response = await axios.get("/api/admin/departments");
        departments.value = response.data || [];
    } catch (error) {
        departments.value = [];
        console.error(error);
    }
};

const fetchApplication = async () => {
    loading.value = true;
    errorMessage.value = "";

    try {
        const response = await axios.get(
            `/api/admin/applications/${route.params.id}`
        );
        application.value = response.data;
        syncStageDrafts(application.value);
        syncPpsProfileDraft(application.value);
        syncStaffDetailsDraft(application.value);
        aiResult.value = application.value.ai_result || null;
        aiError.value = "";
        memberToAdd.value = "";
        await loadVacancyCandidates();
    } catch (error) {
        application.value = null;
        staffDetailsDraft.value = emptyStaffDetailsDraft();
        ppsProfileDraft.value = emptyPpsProfileDraft();
        errorMessage.value =
            error?.response?.data?.message || "Ошибка при загрузке заявки.";
    } finally {
        loading.value = false;
    }
};

const saveStaffDetails = async () => {
    if (!application.value || !isStaffApplication.value) return;

    if (!staffDetailsDraft.value.full_name.trim()) {
        alert("Введите ФИО кандидата.");
        return;
    }

    if (!staffDetailsDraft.value.vacancy_id) {
        alert("Выберите вакансию ОУП.");
        return;
    }

    staffDetailsSaving.value = true;

    try {
        const response = await axios.put(
            `/api/admin/applications/${application.value.id}/staff-details`,
            {
                full_name: staffDetailsDraft.value.full_name.trim(),
                phone: staffDetailsDraft.value.phone.trim() || null,
                vacancy_id: staffDetailsDraft.value.vacancy_id,
            }
        );

        application.value = response.data.application;
        syncStageDrafts(application.value);
        syncStaffDetailsDraft(application.value);
        aiResult.value = application.value.ai_result || null;
        await loadVacancyCandidates();
    } catch (error) {
        alert(
            error?.response?.data?.message ||
                "Ошибка при сохранении данных ОУП."
        );
    } finally {
        staffDetailsSaving.value = false;
    }
};

const savePpsProfile = async () => {
    if (!application.value || !isPpsApplication.value) return;

    ppsProfileSaving.value = true;

    try {
        const formData = new FormData();

        formData.append("full_name", ppsProfileDraft.value.full_name || "");
        formData.append(
            "desired_position",
            ppsProfileDraft.value.desired_position || ""
        );
        formData.append(
            "faculty_name",
            ppsProfileDraft.value.faculty_name || ""
        );
        formData.append(
            "department_name",
            ppsProfileDraft.value.department_name || ""
        );
        formData.append("birth_year", ppsProfileDraft.value.birth_year || "");
        formData.append(
            "basic_education",
            ppsProfileDraft.value.basic_education || ""
        );
        formData.append("magistracy", ppsProfileDraft.value.magistracy || "");
        formData.append(
            "scientific_degree",
            ppsProfileDraft.value.scientific_degree || ""
        );
        formData.append(
            "academic_title",
            ppsProfileDraft.value.academic_title || ""
        );
        formData.append(
            "work_experience",
            ppsProfileDraft.value.work_experience || ""
        );
        formData.append(
            "scientific_works",
            ppsProfileDraft.value.scientific_works || ""
        );
        formData.append(
            "digital_mooc",
            ppsProfileDraft.value.digital_mooc || ""
        );
        formData.append(
            "final_rating_score",
            ppsProfileDraft.value.final_rating_score || ""
        );
        formData.append(
            "student_survey_results",
            ppsProfileDraft.value.student_survey_results || ""
        );
        formData.append(
            "individual_plan_nonfulfillment",
            ppsProfileDraft.value.individual_plan_nonfulfillment || ""
        );
        formData.append("krk", ppsProfileDraft.value.krk || "");
        formData.append(
            "open_lesson_quality",
            ppsProfileDraft.value.open_lesson_quality || ""
        );
        formData.append(
            "taught_disciplines",
            ppsProfileDraft.value.taught_disciplines || ""
        );
        formData.append(
            "educational_methodical_literature",
            ppsProfileDraft.value.educational_methodical_literature || ""
        );
        formData.append(
            "educational_publication_metrics",
            ppsProfileDraft.value.educational_publication_metrics || ""
        );
        formData.append(
            "anti_corruption_survey_results",
            ppsProfileDraft.value.anti_corruption_survey_results || ""
        );
        formData.append(
            "disciplinary_actions_info",
            ppsProfileDraft.value.disciplinary_actions_info || ""
        );

        for (const field of ppsDocumentFields) {
            for (const file of ppsProfileDraft.value[field.documentsField] ||
                []) {
                if (file instanceof File) {
                    formData.append(`${field.documentsField}[]`, file);
                }
            }
        }

        for (const file of ppsProfileDraft.value.scientific_works_documents ||
            []) {
            if (file instanceof File) {
                formData.append("scientific_works_documents[]", file);
            }
        }

        for (const section of ppsExtraSections) {
            if (!section.documentField) {
                continue;
            }
            for (const file of ppsProfileDraft.value[section.documentField] ||
                []) {
                if (file instanceof File) {
                    formData.append(`${section.documentField}[]`, file);
                }
            }
        }

        await axios.post(
            `/api/admin/applications/${application.value.id}/pps-profile`,
            formData,
            {
                headers: {
                    "Content-Type": "multipart/form-data",
                },
            }
        );

        await fetchApplication();
    } catch (error) {
        alert(
            error?.response?.data?.message ||
                "Ошибка при сохранении данных преподавателя."
        );
    } finally {
        ppsProfileSaving.value = false;
    }
};

const savePpsProfileSection = async ({
    key,
    fieldKeys,
    documentFields = [],
    errorMessage,
}) => {
    if (!application.value || !isPpsApplication.value) return;

    ppsProfileSaving.value = true;
    ppsProfileSavingSection.value = key;

    try {
        const formData = new FormData();

        for (const field of fieldKeys) {
            formData.append(field, ppsProfileDraft.value[field] || "");
        }

        for (const field of documentFields) {
            for (const file of ppsProfileDraft.value[field] || []) {
                if (file instanceof File) {
                    formData.append(`${field}[]`, file);
                }
            }
        }

        const response = await axios.post(
            `/api/admin/applications/${application.value.id}/pps-profile`,
            formData,
            {
                headers: {
                    "Content-Type": "multipart/form-data",
                },
            }
        );

        application.value = response.data.application;
        applySavedPpsProfileSection(
            application.value,
            fieldKeys,
            documentFields
        );
    } catch (error) {
        alert(
            error?.response?.data?.message ||
                errorMessage ||
                "РћС€РёР±РєР° РїСЂРё СЃРѕС…СЂР°РЅРµРЅРёРё РґР°РЅРЅС‹С… РїСЂРµРїРѕРґР°РІР°С‚РµР»СЏ."
        );
    } finally {
        ppsProfileSaving.value = false;
        ppsProfileSavingSection.value = "";
    }
};

const saveMainPpsProfile = () =>
    savePpsProfileSection({
        key: "main",
        fieldKeys: ppsMainFieldKeys,
        documentFields: ppsMainDocumentFields,
        errorMessage:
            "РћС€РёР±РєР° РїСЂРё СЃРѕС…СЂР°РЅРµРЅРёРё РѕСЃРЅРѕРІРЅС‹С… РґР°РЅРЅС‹С… РїСЂРµРїРѕРґР°РІР°С‚РµР»СЏ.",
    });

const saveScientificPpsProfile = () =>
    savePpsProfileSection({
        key: "scientific",
        fieldKeys: ["scientific_works", "digital_mooc"],
        documentFields: [
            "scientific_works_documents",
            "digital_mooc_documents",
        ],
        errorMessage:
            "РћС€РёР±РєР° РїСЂРё СЃРѕС…СЂР°РЅРµРЅРёРё РЅР°СѓС‡РЅС‹С… С‚СЂСѓРґРѕРІ Рё Р¦РћР  / РњРћРћРљ.",
    });

const saveExtraPpsProfileSection = (section) =>
    savePpsProfileSection({
        key: section.key,
        fieldKeys: section.fields.map((field) => field.key),
        documentFields: section.documentField ? [section.documentField] : [],
        errorMessage: `РћС€РёР±РєР° РїСЂРё СЃРѕС…СЂР°РЅРµРЅРёРё СЃРµРєС†РёРё "${section.title}".`,
    });

const saveStage = async (stage) => {
    if (!application.value) return;

    stageSavingByKey.value[stage] = true;

    try {
        const response = await axios.put(
            `/api/admin/applications/${application.value.id}`,
            {
                stage,
                stage_status: stageDrafts.value[stage].status,
                comment: stageDrafts.value[stage].comment || null,
            }
        );
        application.value = response.data.application;
        syncStageDrafts(application.value);
    } catch (error) {
        alert(
            error?.response?.data?.message ||
                "Ошибка при обновлении статуса этапа."
        );
    } finally {
        stageSavingByKey.value[stage] = false;
    }
};

const addCommissionMember = async () => {
    if (!vacancyId.value || !memberToAdd.value) return;

    try {
        await axios.post(
            `/api/admin/vacancies/${vacancyId.value}/commission-members`,
            {
                user_id: Number(memberToAdd.value),
            }
        );

        memberToAdd.value = "";
        await fetchApplication();
    } catch (error) {
        alert(
            error?.response?.data?.message ||
                "Ошибка при добавлении голосующего."
        );
    }
};

const removeCommissionMember = async (userId) => {
    if (!vacancyId.value) return;
    if (!confirm("Удалить голосующего из вакансии?")) return;

    try {
        await axios.delete(
            `/api/admin/vacancies/${vacancyId.value}/commission-members/${userId}`
        );
        await fetchApplication();
    } catch (error) {
        alert(
            error?.response?.data?.message || "Ошибка при удалении голосующего."
        );
    }
};

const openLawyerResponsePdf = async () => {
    if (!application.value || !canGenerateLawyerPdf.value) {
        alert(
            "PDF ответа юриста доступен только после завершения юридической проверки."
        );
        return;
    }

    const previewWindow = window.open("", "_blank");
    if (previewWindow) {
        previewWindow.opener = null;
        previewWindow.document.title = "PDF ответа юриста";
        previewWindow.document.body.innerHTML =
            '<p style="font-family:sans-serif;padding:16px">Подготовка PDF...</p>';
    }

    pdfLoading.value = true;

    try {
        const response = await axios.get(
            `/api/admin/applications/${application.value.id}/lawyer-response-pdf`,
            {
                responseType: "blob",
            }
        );

        const blobUrl = window.URL.createObjectURL(
            new Blob([response.data], { type: "application/pdf" })
        );

        if (previewWindow) {
            previewWindow.location.href = blobUrl;
        } else {
            window.open(blobUrl, "_blank");
        }

        window.setTimeout(() => {
            window.URL.revokeObjectURL(blobUrl);
        }, 60_000);
    } catch (error) {
        if (previewWindow) {
            previewWindow.close();
        }

        alert(
            error?.response?.data?.message ||
                "Ошибка при генерации PDF ответа юриста."
        );
    } finally {
        pdfLoading.value = false;
    }
};

const generateCandidateAI = async () => {
    const workerId = application.value?.user?.id;
    const positionId =
        application.value?.ai_position_id ||
        application.value?.vacancy?.position_id;

    aiError.value = "";

    if (!workerId) {
        aiError.value = "Не найден ID кандидата.";
        return;
    }

    if (!positionId) {
        aiError.value = "У вакансии не указана должность (position_id).";
        return;
    }

    aiLoading.value = true;

    try {
        const response = await axios.post("/api/check-candidate", {
            worker_id: Number(workerId),
            position_id: Number(positionId),
            lang: "ru",
        });

        aiResult.value = response.data;
    } catch (error) {
        aiError.value =
            error?.response?.data?.message ||
            "Ошибка при генерации AI-анализа.";
    } finally {
        aiLoading.value = false;
    }
};

onMounted(async () => {
    await Promise.all([
        fetchApplication(),
        fetchStaffVacancies(),
        fetchDepartments(),
    ]);
});
</script>
