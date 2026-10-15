import axios from "axios";

export const positionUnitKey = (position) =>
    position?.academic_unit_id
        ? `academic:${position.academic_unit_id}`
        : position?.department_id
          ? `department:${position.department_id}`
          : "";

export const positionOwnerFields = (key) => {
    const [type, id] = String(key).split(":");
    return {
        department_id: type === "department" ? Number(id) : null,
        academic_unit_id: type === "academic" ? Number(id) : null,
    };
};

export async function loadPositionUnits({ includeAcademic = true } = {}) {
    const requests = [axios.get("/api/admin/departments")];
    if (includeAcademic) requests.push(axios.get("/api/admin/academic-units"));
    const [departments, academic] = await Promise.all(requests);
    const units = [
        ...departments.data.map((unit) => ({
            id: `department:${unit.id}`,
            name: unit.name,
            type: "staff",
        })),
    ];
    if (academic) {
        units.push(
            ...academic.data
                .filter((unit) => unit.kind === "chair")
                .map((unit) => ({
                    id: `academic:${unit.id}`,
                    name: unit.name,
                    type: "pps",
                })),
        );
    }
    return units;
}
