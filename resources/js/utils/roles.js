export const LEGAL_ROLE_ALIASES = ['lawyer', 'compliance_director'];

export const normalizeRole = (role) => (role === 'compliance_director' ? 'lawyer' : role);

export const isLegalRole = (role) => LEGAL_ROLE_ALIASES.includes(role);
