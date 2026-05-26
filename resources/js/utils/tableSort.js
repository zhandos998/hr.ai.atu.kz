export const compareSortValues = (left, right) => {
  const leftIsEmpty = left === null || left === undefined || left === '';
  const rightIsEmpty = right === null || right === undefined || right === '';

  if (leftIsEmpty && rightIsEmpty) return 0;
  if (leftIsEmpty) return 1;
  if (rightIsEmpty) return -1;

  if (typeof left === 'number' && typeof right === 'number') {
    return left - right;
  }

  return String(left).localeCompare(String(right), 'ru', {
    numeric: true,
    sensitivity: 'base',
  });
};

export const sortRows = (items, getValue, direction = 'asc') => {
  const multiplier = direction === 'desc' ? -1 : 1;

  return [...(items || [])].sort((left, right) => (
    compareSortValues(getValue(left), getValue(right)) * multiplier
  ));
};
