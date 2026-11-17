export function buildDepartmentTree(items) {
    const nodes = new Map(items.map(item => [Number(item.id), { ...item, children: [] }]));
    const roots = [];
    nodes.forEach(item => {
        const parent = nodes.get(Number(item.parent_id));
        if (item.parent_id && parent) parent.children.push(item);
        else roots.push(item);
    });
    return roots;
}

export function filterDepartmentTree(roots, search) {
    const query = search.trim().toLocaleLowerCase();
    if (!query) return roots;
    const matches = values => values.some(value => String(value || '').toLocaleLowerCase().includes(query));
    const filter = item => {
        if (matches([item.name, item.description])) return item;
        const positions = (item.positions || []).filter(position => matches([position.name, position.duties, position.qualification]));
        const children = (item.children || []).map(filter).filter(Boolean);
        return positions.length || children.length ? { ...item, positions, children } : null;
    };
    return roots.map(filter).filter(Boolean);
}
