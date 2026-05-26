export const applicationStageOrder = ['resume', 'documents', 'compliance', 'hiring'];

export const applicationStageMeta = {
  resume: {
    title: 'Статус резюме',
    placeholder: 'Комментарий по резюме',
    statuses: {
      pending: { label: 'На рассмотрении', className: 'bg-yellow-100 text-yellow-800' },
      accepted: { label: 'Принято', className: 'bg-emerald-100 text-emerald-800' },
      rejected: { label: 'Отклонено', className: 'bg-red-100 text-red-800' },
    },
  },
  documents: {
    title: 'Статус документов',
    placeholder: 'Комментарий по документам',
    statuses: {
      awaiting_upload: { label: 'Ожидается загрузка', className: 'bg-blue-100 text-blue-800' },
      accepted: { label: 'Приняты', className: 'bg-emerald-100 text-emerald-800' },
      rejected: { label: 'Отклонены', className: 'bg-red-100 text-red-800' },
    },
  },
  compliance: {
    title: 'Статус коррупции',
    placeholder: 'Комментарий по юридической проверке',
    statuses: {
      not_started: { label: 'Не начато', className: 'bg-slate-100 text-slate-700' },
      clear: { label: 'Не выявлена', className: 'bg-emerald-100 text-emerald-800' },
      flagged: { label: 'Выявлена', className: 'bg-red-100 text-red-800' },
    },
  },
  hiring: {
    title: 'Принятие на работу',
    placeholder: 'Комментарий по финальному решению',
    statuses: {
      not_started: { label: 'Не начато', className: 'bg-slate-100 text-slate-700' },
      voting: { label: 'Голосование', className: 'bg-amber-100 text-amber-800' },
      hired_1_year: { label: 'Взять на 1 год', className: 'bg-emerald-100 text-emerald-800' },
      hired_3_year: { label: 'Взять на 3 года', className: 'bg-sky-100 text-sky-800' },
      rejected: { label: 'Не брать', className: 'bg-red-100 text-red-800' },
    },
  },
};

const applicationStageStatusAliases = {
  documents: {
    not_requested: 'awaiting_upload',
    uploaded: 'awaiting_upload',
  },
};

export const applicationSummaryMeta = {
  pending: { label: 'Резюме на рассмотрении', className: 'bg-yellow-100 text-yellow-800' },
  resume_rejected: { label: 'Резюме отклонено', className: 'bg-red-100 text-red-800' },
  resume_accepted: { label: 'Резюме принято', className: 'bg-blue-100 text-blue-800' },
  docs_uploaded: { label: 'Ожидается загрузка документов', className: 'bg-blue-100 text-blue-800' },
  docs_rejected: { label: 'Документы отклонены', className: 'bg-red-100 text-red-800' },
  docs_accepted: { label: 'Документы приняты', className: 'bg-emerald-100 text-emerald-800' },
  corruption_not_found: { label: 'Коррупция не выявлена', className: 'bg-emerald-100 text-emerald-800' },
  corruption_found: { label: 'Коррупция выявлена', className: 'bg-red-100 text-red-800' },
  completed: { label: 'Взять на работу', className: 'bg-emerald-200 text-emerald-900' },
  not_accepted: { label: 'Не брать', className: 'bg-red-200 text-red-900' },
};

export const stageStatusField = (stage) => `${stage}_status`;
export const stageCommentField = (stage) => `${stage}_comment`;

export const stageOptions = (stage) => Object.entries(applicationStageMeta[stage]?.statuses || {}).map(([value, meta]) => ({
  value,
  label: meta.label,
}));

export const normalizedStageStatus = (stage, status, context = null) => {
  if (stage === 'hiring' && status === 'hired') {
    return Number(context?.hiring_term_years) === 3 ? 'hired_3_year' : 'hired_1_year';
  }

  return applicationStageStatusAliases[stage]?.[status] || status;
};
export const stageLabel = (stage, status, context = null) => {
  const normalizedStatus = normalizedStageStatus(stage, status, context);
  return applicationStageMeta[stage]?.statuses?.[normalizedStatus]?.label || normalizedStatus || '-';
};
export const stageClass = (stage, status, context = null) => {
  const normalizedStatus = normalizedStageStatus(stage, status, context);
  return applicationStageMeta[stage]?.statuses?.[normalizedStatus]?.className || 'bg-slate-100 text-slate-700';
};

export const summaryLabel = (code) => applicationSummaryMeta[code]?.label || code || '-';
export const summaryClass = (code) => applicationSummaryMeta[code]?.className || 'bg-slate-100 text-slate-700';

export const stageTitle = (stage) => applicationStageMeta[stage]?.title || stage;
export const stagePlaceholder = (stage) => applicationStageMeta[stage]?.placeholder || 'Комментарий';

export const latestStageLog = (application, stage) => {
  const logs = Array.isArray(application?.stage_logs) ? application.stage_logs : [];
  return logs.find((log) => log.stage === stage) || null;
};
