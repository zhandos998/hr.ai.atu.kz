<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Vacancy;

class VacancyController extends Controller
{
    /**
     * Вывод всех вакансий для админки
     */
    public function index()
    {
        $vacancies = Vacancy::latest()->get();
        return response()->json($vacancies);
    }

    /**
     * Создание вакансии
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'type' => 'required|in:staff,pps',
        ]);

        $vacancy = Vacancy::create($validated);

        return response()->json([
            'message' => 'Вакансия успешно создана.',
            'vacancy' => $vacancy
        ]);
    }

    /**
     * Обновление вакансии
     */
    public function update(Request $request, $id)
    {
        $vacancy = Vacancy::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'type' => 'sometimes|in:staff,pps',
        ]);

        $vacancy->update($validated);

        return response()->json([
            'message' => 'Вакансия обновлена.',
            'vacancy' => $vacancy
        ]);
    }

    public function show($id)
    {
        $vacancy = Vacancy::findOrFail($id);
        return response()->json($vacancy);
    }

    /**
     * Удаление вакансии
     */
    public function destroy($id)
    {
        $vacancy = Vacancy::findOrFail($id);
        $vacancy->delete();

        return response()->json(['message' => 'Вакансия удалена.']);
    }
}
