<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Competition;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CompetitionController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['type' => ['nullable', Rule::in(['pps', 'staff'])], 'q' => 'nullable|string|max:255']);

        return Competition::query()
            ->when($data['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($data['q'] ?? null, fn ($query, $search) => $query->where('title', 'like', '%'.$search.'%'))
            ->withCount(['applications', 'applications as active_applications_count' => fn ($query) => $query->notArchived()])
            ->latest('id')->paginate(20);
    }

    public function defaults()
    {
        return collect(['pps', 'staff'])->mapWithKeys(function ($type) {
            $number = (int) Competition::where('type', $type)->max('number') + 1;

            return [$type => Competition::defaultTitle($type, $number)];
        });
    }

    public function store(Request $request)
    {
        $data = $request->validate(['type' => ['required', Rule::in(['pps', 'staff'])], 'title' => 'nullable|string|max:255']);
        // The unique sequence also protects simultaneous submissions by different admins.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $competition = DB::transaction(function () use ($data, $request) {
                    $number = (int) Competition::where('type', $data['type'])->max('number') + 1;

                    return Competition::create([
                        'type' => $data['type'],
                        'number' => $number,
                        'title' => trim($data['title'] ?? '') ?: Competition::defaultTitle($data['type'], $number),
                        'created_by' => $request->user()->id,
                    ]);
                }, 5);

                return response()->json($competition, 201);
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === 4) {
                    throw $exception;
                }
            }
        }
    }

    public function show(Competition $competition)
    {
        return $competition->loadCount(['applications', 'applications as active_applications_count' => fn ($query) => $query->notArchived()]);
    }

    public function update(Request $request, Competition $competition)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'type' => ['sometimes', Rule::in([$competition->type])],
        ]);
        $competition->update(['title' => $data['title']]);

        return $competition;
    }

    public function candidates(Request $request, Competition $competition)
    {
        $data = $request->validate(['q' => 'nullable|string|max:255']);

        return Application::query()->notArchived()->whereNull('competition_id')
            ->whereHas('vacancy', fn ($query) => $query->where('type', $competition->type))
            ->when($data['q'] ?? null, fn ($query, $search) => $query->whereHas('user', fn ($user) => $user->where('name', 'like', '%'.$search.'%')))
            ->with(['user:id,name', 'vacancy:id,title,type,position_id', 'vacancy.position:id,name', 'ppsProfile:id,application_id,desired_position,faculty_name,department_name'])
            ->select(['id', 'user_id', 'vacancy_id', 'created_at'])
            ->latest('id')->paginate(20);
    }

    public function attach(Request $request, Competition $competition)
    {
        $data = $request->validate([
            'application_ids' => 'required|array|min:1|max:100',
            'application_ids.*' => 'required|integer|distinct|exists:applications,id',
        ]);
        DB::transaction(function () use ($data, $competition) {
            $applications = Application::whereIn('id', $data['application_ids'])->with('vacancy')->orderBy('id')->lockForUpdate()->get();
            foreach ($applications as $application) {
                if ($application->archived_at || $application->vacancy?->type !== $competition->type ||
                    ($application->competition_id !== null && (int) $application->competition_id !== $competition->id)) {
                    throw ValidationException::withMessages(['application_ids' => 'Можно добавить только активные заявки выбранного типа, не включённые в другой конкурс.']);
                }
            }
            foreach ($applications as $application) {
                $application->update(['competition_id' => $competition->id]);
            }
        });

        return response()->json(['message' => 'Конкурсанты добавлены.']);
    }

    public function detach(Competition $competition, Application $application)
    {
        $updated = Application::whereKey($application->id)->where('competition_id', $competition->id)->update(['competition_id' => null]);
        abort_unless($updated, 404);

        return response()->json(['message' => 'Заявка исключена из конкурса и сохранена в общем списке.']);
    }
}
