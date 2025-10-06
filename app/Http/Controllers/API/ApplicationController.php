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
            'vacancy_id' => 'required|integer|max:255|exists:vacancies,id',
            'resume' => 'required|file|mimes:pdf,doc,docx|max:2048',
        ]);

        $path = $request->file('resume')->store('resumes', 'public');


        $status = ApplicationStatus::where('code', 'pending')->first();

        $application = Application::create([
            'user_id' => $request->user()->id,
            'vacancy_id' => $validated['vacancy_id'],
            'status_id' => $status->id,
        ]);


        $resume = Resume::create([
            'application_id' => $application->id,
            'user_id' => $request->user()->id,
            'file_path' => $path,
        ]);

        return response()->json([
            'message' => 'Вы успешно откликнулись на вакансию.',
            'application' => $application,
            'resume' => $resume,
        ]);
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
        $applications = Application::with(['status', 'vacancy'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

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
        $application = Application::with('vacancy')->where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();

        if ($application->status->code !== 'resume_accepted') {
            return response()->json(['message' => 'Документы можно загрузить только после принятия резюме.'], 403);
        }

        $rules = [
            'id_card' => 'required|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'diploma' => 'required|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ];

        if ($application->vacancy->type === 'pps') {
            $rules['articles'] = 'required|file|mimes:pdf,zip|max:5120';
        } elseif ($application->vacancy->type === 'staff') {
            $rules['address_certificate'] = 'required|file|mimes:pdf,jpg,jpeg,png|max:2048';
        }

        $validated = $request->validate($rules);

        foreach ($validated as $key => $file) {
            $file->storeAs("applications/{$application->id}", "{$key}." . $file->getClientOriginalExtension(), 'public');
        }

        $status = ApplicationStatus::where('code', 'docs_uploaded')->first();
        $application->status_id = $status->id;
        $application->save();

        return response()->json(['message' => 'Документы успешно загружены. Заявка обновлена.']);
    }
}
