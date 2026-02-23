<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Http\Request;

class VacancyController extends Controller
{
    public function index()
    {
        $vacancies = Vacancy::query()
            ->with([
                'position:id,department_id,name',
                'commissionMembers:id,name,email,phone',
            ])
            ->latest()
            ->get();

        return response()->json($vacancies);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'type' => 'required|in:staff,pps',
            'position_id' => 'required|integer|exists:positions,id',
        ]);

        $vacancy = Vacancy::create($validated);

        return response()->json([
            'message' => 'Р’Р°РєР°РЅСЃРёСЏ СѓСЃРїРµС€РЅРѕ СЃРѕР·РґР°РЅР°.',
            'vacancy' => $vacancy,
        ]);
    }

    public function update(Request $request, $id)
    {
        $vacancy = Vacancy::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'type' => 'sometimes|in:staff,pps',
            'position_id' => 'sometimes|integer|exists:positions,id',
        ]);

        $vacancy->update($validated);

        return response()->json([
            'message' => 'Р’Р°РєР°РЅСЃРёСЏ РѕР±РЅРѕРІР»РµРЅР°.',
            'vacancy' => $vacancy,
        ]);
    }

    public function show($id)
    {
        $vacancy = Vacancy::with([
            'position:id,department_id,name',
            'commissionMembers:id,name,email,phone',
        ])->findOrFail($id);

        return response()->json($vacancy);
    }

    public function destroy($id)
    {
        $vacancy = Vacancy::findOrFail($id);
        $vacancy->delete();

        return response()->json(['message' => 'Р’Р°РєР°РЅСЃРёСЏ СѓРґР°Р»РµРЅР°.']);
    }

    public function commissionCandidates(Request $request, int $id)
    {
        $vacancy = Vacancy::findOrFail($id);
        $q = trim((string) $request->query('q', ''));

        $assignedIds = $vacancy->commissionMembers()->pluck('users.id');

        $users = User::query()
            ->select('id', 'name', 'email', 'phone')
            ->whereNotIn('id', $assignedIds)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner
                        ->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->limit(200)
            ->get();

        return response()->json($users);
    }

    public function addCommissionMember(Request $request, int $id)
    {
        $vacancy = Vacancy::findOrFail($id);

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $vacancy->commissionMembers()->syncWithoutDetaching([$validated['user_id']]);

        return response()->json([
            'message' => 'Р“РѕР»РѕСЃСѓСЋС‰РёР№ РґРѕР±Р°РІР»РµРЅ Рє РІР°РєР°РЅСЃРёРё.',
            'vacancy' => $vacancy->fresh()->load('commissionMembers:id,name,email,phone'),
        ]);
    }

    public function removeCommissionMember(int $id, int $userId)
    {
        $vacancy = Vacancy::findOrFail($id);
        $vacancy->commissionMembers()->detach($userId);

        return response()->json([
            'message' => 'Р“РѕР»РѕСЃСѓСЋС‰РёР№ СѓРґР°Р»РµРЅ РёР· РІР°РєР°РЅСЃРёРё.',
            'vacancy' => $vacancy->fresh()->load('commissionMembers:id,name,email,phone'),
        ]);
    }
}
