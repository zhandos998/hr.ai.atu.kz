export const hiringTermLabel = (years) => {
  if (Number(years) === 1) return '1 год';
  if (Number(years) === 3) return '3 года';
  return years ? `${years} года` : '';
};

export const voteDecisionLabel = (decision, hireTermYears = null, vacancyType = null) => {
  if (decision === 'hire') {
    if (vacancyType === 'pps' && Number(hireTermYears) === 1) return 'Взять на 1 год';
    if (vacancyType === 'pps' && Number(hireTermYears) === 3) return 'Взять на 3 года';
    if (vacancyType === 'pps') return 'Взять на работу';
    return 'За';
  }

  if (decision === 'reject') {
    return vacancyType === 'pps' ? 'Не брать' : 'Против';
  }

  return 'Не голосовал';
};

export const voteDecisionClass = (decision, hireTermYears = null) => {
  if (decision === 'hire' && Number(hireTermYears) === 1) return 'bg-emerald-100 text-emerald-700';
  if (decision === 'hire' && Number(hireTermYears) === 3) return 'bg-sky-100 text-sky-700';
  if (decision === 'hire') return 'bg-emerald-100 text-emerald-700';
  if (decision === 'reject') return 'bg-red-100 text-red-700';
  return 'bg-gray-100 text-gray-700';
};

export const voteResultLabel = (result, approvedTermYears = null, vacancyType = null) => {
  if (result === 'approved') {
    if (vacancyType === 'pps' && Number(approvedTermYears) === 1) return 'Взять на 1 год';
    if (vacancyType === 'pps' && Number(approvedTermYears) === 3) return 'Взять на 3 года';
    return 'Взять на работу';
  }

  if (result === 'rejected') {
    return 'Не брать';
  }

  return 'Ожидание голосов';
};

export const voteResultClass = (result, approvedTermYears = null) => {
  if (result === 'approved' && Number(approvedTermYears) === 3) return 'bg-sky-100 text-sky-700';
  if (result === 'approved') return 'bg-emerald-100 text-emerald-700';
  if (result === 'rejected') return 'bg-red-100 text-red-700';
  return 'bg-gray-100 text-gray-700';
};
