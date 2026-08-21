import { t } from '../i18n';

export const vacancyTypeMeta = {
  staff: {
    label: 'АУП',
  },
  pps: {
    label: 'ППС',
  },
};

export const vacancyTypeTabs = ['pps', 'staff'];

export const vacancyTypeLabel = (type) => vacancyTypeMeta[type]?.label || type || t('common.notSpecified');
