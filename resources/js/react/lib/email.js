export const isTechnicalEmail = (email) =>
    typeof email === "string" &&
    email.trim().toLowerCase().endsWith("@hr-ai.invalid");
