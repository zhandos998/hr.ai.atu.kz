export const vacancyTypeMeta = {
  staff: {
    label: 'ОУП',
  },
  pps: {
    label: 'ППС',
  },
};

export const vacancyTypeTabs = ['pps', 'staff'];

export const vacancyTypeLabel = (type) => vacancyTypeMeta[type]?.label || type || 'Не указан';
