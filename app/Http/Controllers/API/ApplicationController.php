<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

use App\Models\Application;
use App\Models\ApplicationStatus;
use App\Models\Resume;
use App\Models\Vacancy;

use App\Models\ApplicationDocument;

class ApplicationController extends Controller
{
    /**
     * Вывод всех заявок для админки
     */
    public function index()
    {
        $applications = Application::with(['user', 'status'])
            ->latest()
            ->get();

        $applications->transform(function ($a) {
            // Резюме (если есть)
            $a->resume_url = $a->resume
                ? url(Storage::url($a->resume->file_path))
                : null;


            // Карта документов: type => { path, url }
            $docs = [];
            foreach ($a->documents as $doc) {
                $docs[$doc->type] = [
                    'path' => $doc->file_path,
                    'url'  => url(Storage::url($a->resume->file_path)),
                ];
            }
            $a->documents_map = (object) $docs; // пустой объект вместо [] если нет документов

            // Если не нужно дублировать полные связи в JSON — можно скрыть:
            // unset($a->resume, $a->documents);

            return $a;
        });

        return response()->json($applications);
    }

    /**
     * Обновление статуса заявки
     */
    public function updateStatus(Request $request, $id)
    {
        $application = Application::findOrFail($id);

        $validated = $request->validate([
            'status_code' => 'required|exists:application_statuses,code',
        ]);


        $status = ApplicationStatus::where('code', $validated['status_code'])->first();

        $application->update([
            'status_id' => $status->id,
        ]);

        return response()->json([
            'message' => 'Статус заявки обновлен.',
            'application' => $application
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'vacancy_id' => 'required|exists:vacancies,id',
            'resume'     => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:2048',
        ]);

        $user = $request->user();

        return DB::transaction(function () use ($validated, $user, $request) {
            // 1) находим вакансию (опционально для доп.проверок)
            $vacancy = Vacancy::findOrFail($validated['vacancy_id']);

            // 2) статус pending
            $statusId = ApplicationStatus::where('code', 'pending')->value('id');

            // 3) создаём заявку
            $application = Application::create([
                'user_id'    => $user->id,
                'vacancy_id' => $vacancy->id,
                'status_id'  => $statusId,
            ]);

            // 4) сохраняем файл резюме в папку заявки
            $storedPath = $request->file('resume')->store("resumes/{$application->id}", 'public');

            // 5) пишем запись в resumes с привязкой к заявке
            $resume = Resume::create([
                'application_id' => $application->id,
                'user_id'        => $user->id,
                'file_path'      => $storedPath,
            ]);

            // 6) добавим удобный URL для фронта
            $application->load('resume');
            $resumeUrl = Storage::url($resume->file_path); // /storage/resumes/{id}/...

            return response()->json([
                'message'     => 'Вы успешно откликнулись на вакансию.',
                'application' => $application,
                'resume'      => $resume,
                'resume_url'  => $resumeUrl,
            ], 201);
        });
    }

    public function acceptResume($id)
    {
        $this->updateStatusCode($id, 'resume_accepted', 'Резюме принято.');
    }

    public function rejectResume($id)
    {
        $this->updateStatusCode($id, 'resume_rejected', 'Резюме отклонено.');
    }

    public function acceptDocs($id)
    {
        $this->updateStatusCode($id, 'docs_accepted', 'Документы приняты.');
    }


    public function rejectDocs($id)
    {
        $this->updateStatusCode($id, 'docs_rejected', 'Документы отклонены.');
    }


    public function complete($id)
    {
        $this->updateStatusCode($id, 'completed', 'Кандидат принят на вакансию.');
    }

    public function userApplications(Request $request)
    {
        $applications = Application::with([
            'status:id,code,name',
            'vacancy:id,title,type',
            'resume:id,application_id,file_path',
            'documents:id,application_id,type,file_path',
        ])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        $applications->transform(function ($a) {
            // Резюме
            $a->resume_url = $a->resume
                ? url(Storage::url($a->resume->file_path))
                : null;

            // Документы
            $docs = [];
            foreach ($a->documents as $doc) {
                $docs[$doc->type] = [
                    'path' => $doc->file_path,
                    'url'  => url(Storage::url($doc->file_path)),
                ];
            }
            $a->documents_map = (object) $docs;

            return $a;
        });

        return response()->json($applications);
    }

    private function updateStatusCode($id, $code, $message)
    {
        $application = Application::findOrFail($id);
        $status = ApplicationStatus::where('code', $code)->first();
        $application->status_id = $status->id;
        $application->save();

        return response()->json([
            'message' => $message,
            'application' => $application->load(['user', 'status']),
        ]);
    }


    public function uploadDocs(Request $request, $id)
    {
        $application = Application::with(['vacancy', 'status', 'documents'])
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if (!in_array($application->status->code, ['resume_accepted', 'docs_rejected', 'docs_uploaded'])) {
            return response()->json(['message' => 'Сейчас нельзя загрузить/заменить документы.'], 403);
        }

        // ожидаемые типы
        $expected = ['id_card', 'diploma'];
        if ($application->vacancy->type === 'pps')   $expected[] = 'articles';
        if ($application->vacancy->type === 'staff') $expected[] = 'address_certificate';

        $existing = $application->documents->pluck('type')->all();

        // динамические правила
        $rules = [];
        foreach ($expected as $t) {
            $base = in_array($t, $existing) ? 'sometimes' : 'required';
            $max  = ($t === 'articles') ? 5120 : 2048;
            $mimes = ($t === 'articles') ? 'pdf,zip' : 'pdf,jpg,jpeg,png';
            $rules[$t] = "$base|file|mimes:$mimes|max:$max";
        }

        $validated = $request->validate($rules);

        $savedDocs = [];

        DB::transaction(function () use ($validated, $application, &$savedDocs) {
            foreach ($validated as $type => $file) {
                $ext = $file->getClientOriginalExtension();
                $dir = "applications/{$application->id}";
                $path = $file->storeAs($dir, "{$type}.{$ext}", 'public');

                $doc = ApplicationDocument::firstOrNew([
                    'application_id' => $application->id,
                    'type' => $type,
                ]);

                if ($doc->exists && $doc->file_path && $doc->file_path !== $path) {
                    Storage::disk('public')->delete($doc->file_path);
                }

                $doc->file_path = $path;
                $doc->save();

                $savedDocs[$type] = [
                    'path' => $path,
                    'url'  => url(Storage::url($path)),
                ];
            }

            // статус: помечаем как "документы загружены"
            $statusId = ApplicationStatus::where('code', 'docs_uploaded')->value('id');
            if ($statusId) {
                $application->status_id = $statusId;
                $application->save();
            }
        });

        return response()->json([
            'message' => 'Документы обновлены.',
            'documents_map' => (object)$savedDocs,
        ]);
    }
}
