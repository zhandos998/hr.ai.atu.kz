export const buildDepartmentTree = (departments) => {
  const byId = new Map(
    (departments || []).map((department) => [
      department.id,
      {
        ...department,
        children: [],
      },
    ]),
  );

  const roots = [];

  for (const department of byId.values()) {
    if (department.parent_id && byId.has(department.parent_id)) {
      byId.get(department.parent_id).children.push(department);
      continue;
    }

    roots.push(department);
  }

  const sortTree = (items) => {
    items.sort((left, right) => String(left.name || '').localeCompare(String(right.name || ''), 'ru'));
    items.forEach((item) => sortTree(item.children));
  };

  sortTree(roots);

  return roots;
};

export const flattenDepartmentTree = (tree, level = 0, result = []) => {
  for (const department of tree || []) {
    result.push({
      ...department,
      level,
    });

    flattenDepartmentTree(department.children || [], level + 1, result);
  }

  return result;
};

export const departmentFullName = (department, departmentsById) => {
  if (!department) return '';

  const parts = [department.name];
  let current = department;

  while (current?.parent_id && departmentsById.has(current.parent_id)) {
    current = departmentsById.get(current.parent_id);
    parts.unshift(current.name);
  }

  return parts.filter(Boolean).join(' / ');
};

export const decorateDepartments = (departments) => {
  const byId = new Map((departments || []).map((department) => [department.id, department]));

  return (departments || []).map((department) => ({
    ...department,
    full_name: departmentFullName(department, byId),
  }));
};

export const collectDepartmentSubtreeIds = (departmentId, departments) => {
  const ids = new Set();
  const stack = [Number(departmentId)];
  const items = departments || [];

  while (stack.length > 0) {
    const currentId = stack.pop();
    if (!currentId || ids.has(currentId)) continue;

    ids.add(currentId);

    items.forEach((department) => {
      if (Number(department.parent_id) === currentId) {
        stack.push(Number(department.id));
      }
    });
  }

  return ids;
};
