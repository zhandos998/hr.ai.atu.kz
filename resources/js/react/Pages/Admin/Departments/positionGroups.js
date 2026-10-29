export function groupPositionsByDepartment(positions, root, locale = 'ru') {
    if (!root) return [];
    const groups = new Map();
    for (const position of positions) {
        const id = Number(position.department?.id ?? position.department_id ?? root.id);
        if (!groups.has(id)) {
            groups.set(id, {
                id,
                name: position.department?.name || root.name,
                positions: [],
            });
        }
        groups.get(id).positions.push(position);
    }
    return [...groups.values()].sort((left, right) => {
        if (left.id === right.id) return 0;
        if (left.id === Number(root.id)) return -1;
        if (right.id === Number(root.id)) return 1;
        return left.name.localeCompare(right.name, locale, { numeric: true });
    });
}
