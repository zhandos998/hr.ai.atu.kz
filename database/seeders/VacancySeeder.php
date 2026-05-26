<?php

namespace Database\Seeders;

use App\Models\Position;
use App\Models\Vacancy;
use Illuminate\Database\Seeder;

class VacancySeeder extends Seeder
{
    private const PPS_TITLES = [
        'Ассистент',
        'Ассистент-профессор',
        'Ассистент-профессор-исследователь',
        'Ассоциированный профессор',
        'Ассоциированный профессор – исследователь',
        'Заведующий кафедрой (для выпускающих кафедр)',
        'Заведующий кафедрой (для не выпускающих кафедр)',
        'Сеньор-лектор',
        'Профессор',
        'Профессор-исследователь',
        'Исполняющий обязанности (и.о.) ассоциированного профессора',
        'Исполняющий обязанности (и.о.) ассоциированного профессора – исследователя',
        'Исполняющий обязанности (и.о.) профессора',
        'Исполняющий обязанности (и.о.) профессора-исследователя',
    ];

    public function run(): void
    {
        $this->seedStaffVacancies();
        $this->pruneLegacyStaffVacancyTemplates();

        foreach (self::PPS_TITLES as $title) {
            $this->upsertVacancy([
                'title' => $title,
                'type' => 'pps',
                'description' => "Конкурс на должность {$title}.",
                'position_id' => $this->positionIdForPpsTitle($title),
            ]);
        }
    }

    private function seedStaffVacancies(): void
    {
        Position::query()
            ->with('department:id,name,description')
            ->whereDoesntHave('department', function ($query) {
                $query
                    ->where('description', 'Кафедра')
                    ->orWhere('name', 'like', 'Кафедра%');
            })
            ->whereNotIn('name', self::PPS_TITLES)
            ->orderBy('department_id')
            ->orderBy('name')
            ->get(['id', 'department_id', 'name'])
            ->each(function (Position $position) {
                $this->upsertVacancy([
                    'title' => $this->staffVacancyTitle($position),
                    'type' => 'staff',
                    'description' => "Конкурс на должность {$position->name}.",
                    'position_id' => $position->id,
                ]);
            });
    }

    private function staffVacancyTitle(Position $position): string
    {
        $departmentName = trim((string) $position->department?->name);

        if ($departmentName === '') {
            return $position->name;
        }

        return "{$position->name} — {$departmentName}";
    }

    private function pruneLegacyStaffVacancyTemplates(): void
    {
        Vacancy::query()
            ->where('type', 'staff')
            ->where(function ($query) {
                $query
                    ->where(function ($inner) {
                        $inner
                            ->whereNull('position_id')
                            ->where('title', 'ОУП');
                    })
                    ->orWhereHas('position', function ($inner) {
                        $inner->whereIn('name', self::PPS_TITLES);
                    });
            })
            ->doesntHave('applications')
            ->get()
            ->each
            ->delete();
    }

    private function positionIdForPpsTitle(string $title): ?int
    {
        $position = Position::query()
            ->where('name', $title)
            ->whereHas('department', function ($query) {
                $query
                    ->where('description', 'Кафедра')
                    ->orWhere('name', 'like', 'Кафедра%');
            })
            ->orderBy('department_id')
            ->first(['id']);

        if (!$position) {
            $position = Position::query()
                ->where('name', $title)
                ->orderBy('department_id')
                ->first(['id']);
        }

        return $position?->id ? (int) $position->id : null;
    }

    private function upsertVacancy(array $vacancy): void
    {
        $positionId = $vacancy['position_id'] ?? null;
        $query = Vacancy::query()->where('type', $vacancy['type']);
        $titleQuery = (clone $query)->where('title', $vacancy['title']);

        $model = $positionId
            ? (clone $query)->where('position_id', $positionId)->first()
            : null;

        $model ??= (clone $titleQuery)->whereNull('position_id')->first();

        if (!$model && !$positionId) {
            $model = (clone $titleQuery)->first();
        }

        if (!$model) {
            $model = new Vacancy();
        }

        $model->title = $vacancy['title'];
        $model->type = $vacancy['type'];
        $model->description = $vacancy['description'];
        $model->position_id = $positionId;
        $model->save();
    }
}
// php artisan db:seed --class= VacancySeeder
