<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationPpsProfile;
use App\Models\ApplicationPpsProfileDocument;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ComplianceApplicationController extends Controller
{
    private const COMPLIANCE_CATEGORY = 'compliance_department';

    public function queue()
    {
        $applications = $this->applicationsQuery()
            ->where('resume_status', 'accepted')
            ->whereNotIn('hiring_status', ['hired', 'rejected'])
            ->latest()
            ->get();

        $applications->transform(fn (Application $application) => $this->transformApplication($application));

        return response()->json($applications);
    }

    public function show(int $id)
    {
        $application = $this->applicationsQuery()->findOrFail($id);

        return response()->json($this->transformApplication($application));
    }

    public function updateComplianceDepartment(Request $request, int $id)
    {
        $application = Application::with(['vacancy', 'ppsProfile.documents'])->notArchived()->findOrFail($id);

        if ($application->vacancy?->type !== 'pps') {
            return response()->json([
                'message' => 'Данные правового обеспечения и комплаенса доступны только для заявок ППС.',
            ], 422);
        }

        if ($application->resume_status !== 'accepted') {
            return response()->json([
                'message' => 'Данные правового обеспечения и комплаенса можно заполнять только после принятия резюме.',
            ], 422);
        }

        if (in_array($application->hiring_status, ['hired', 'rejected'], true)) {
            return response()->json([
                'message' => 'После финального решения комиссии редактировать данные правового обеспечения и комплаенса нельзя.',
            ], 422);
        }

        $validated = $request->validate([
            'anti_corruption_survey_results' => 'nullable|string|max:8000',
            'disciplinary_actions_info' => 'nullable|string|max:8000',
        ]);

        $profile = $application->ppsProfile ?: new ApplicationPpsProfile([
            'application_id' => $application->id,
        ]);

        $profile->anti_corruption_survey_results = $this->emptyToNull($validated['anti_corruption_survey_results'] ?? null);
        $profile->disciplinary_actions_info = $this->emptyToNull($validated['disciplinary_actions_info'] ?? null);
        $profile->save();

        $application = $this->applicationsQuery()->findOrFail($id);

        return response()->json([
            'message' => 'Данные правового обеспечения и комплаенса обновлены.',
            'application' => $this->transformApplication($application),
        ]);
    }

    public function deleteComplianceDocument(int $applicationId, int $documentId)
    {
        $application = Application::with(['vacancy', 'ppsProfile.documents'])->notArchived()->findOrFail($applicationId);

        if ($application->vacancy?->type !== 'pps') {
            return response()->json([
                'message' => 'Документ не найден.',
            ], 404);
        }

        if (in_array($application->hiring_status, ['hired', 'rejected'], true)) {
            return response()->json([
                'message' => 'После финального решения комиссии документы департамента комплаенса удалять нельзя.',
            ], 422);
        }

        $document = $application->ppsProfile?->documents
            ?->first(fn ($item) => (int) $item->id === $documentId && $item->category === self::COMPLIANCE_CATEGORY);

        if (!$document) {
            return response()->json([
                'message' => 'Документ департамента комплаенса не найден.',
            ], 404);
        }

        if (!empty($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        $application = $this->applicationsQuery()->findOrFail($applicationId);

        return response()->json([
            'message' => 'Документ департамента комплаенса удалён.',
            'application' => $this->transformApplication($application),
        ]);
    }

    private function applicationsQuery()
    {
        return Application::query()
            ->notArchived()
            ->with([
                'user:id,name,email,phone',
                'status:id,code,name',
                'vacancy:id,title,type,position_id',
                'vacancy.position:id,department_id,name',
                'vacancy.position.department:id,name',
                'resume:id,application_id,file_path',
                'documents:id,application_id,type,file_path',
                'ppsProfile.documents',
            ]);
    }

    private function transformApplication(Application $application): Application
    {
        return $this->attachPpsProfileExtras($this->attachDocumentUrls($application));
    }

    private function attachDocumentUrls(Application $application): Application
    {
        $application->resume_url = $application->resume ? url(Storage::url($application->resume->file_path)) : null;

        $documents = [];
        foreach ($application->documents as $document) {
            $normalizedType = $this->normalizeDocumentTypeForOutput($document->type);
            if ($normalizedType === null) {
                continue;
            }

            $documents[$normalizedType] = [
                'id' => $document->id,
                'path' => $document->file_path,
                'url' => url(Storage::url($document->file_path)),
            ];
        }

        $application->documents_map = (object) $documents;

        return $application;
    }

    private function attachPpsProfileExtras(Application $application): Application
    {
        if (!$application->relationLoaded('ppsProfile') || !$application->ppsProfile) {
            return $application;
        }

        foreach ($this->ppsProfileDocumentFields() as $field) {
            $pathField = "{$field}_document_path";
            $urlField = "{$field}_document_url";

            $application->ppsProfile->{$urlField} = $application->ppsProfile->{$pathField}
                ? url(Storage::url($application->ppsProfile->{$pathField}))
                : null;
        }

        $application->ppsProfile->compliance_documents = $application->ppsProfile->documents
            ->where('category', self::COMPLIANCE_CATEGORY)
            ->values()
            ->map(function (ApplicationPpsProfileDocument $document) {
                return [
                    'id' => $document->id,
                    'name' => $document->original_name ?: basename($document->file_path),
                    'url' => url(Storage::url($document->file_path)),
                ];
            })
            ->all();

        return $application;
    }

    private function ppsProfileDocumentFields(): array
    {
        return [
            'basic_education',
            'magistracy',
            'scientific_degree',
            'academic_title',
        ];
    }

    private function storeDocument(UploadedFile $file, int $applicationId, int $profileId, int $index): string
    {
        $extension = $file->getClientOriginalExtension();
        $directory = "applications/{$applicationId}/pps-profile/compliance-department";
        $filename = now()->format('YmdHisv') . "-{$profileId}-{$index}.{$extension}";

        return $file->storeAs($directory, $filename, 'public');
    }

    private function emptyToNull($value)
    {
        return $value === '' ? null : $value;
    }

    private function documentBaseType(string $type): string
    {
        return preg_replace('/_\d+$/', '', $type);
    }

    private function normalizeDocumentBaseType(string $type): string
    {
        return $type === 'articles' ? 'scientific_works' : $type;
    }

    private function normalizeDocumentTypeForOutput(string $type): ?string
    {
        $baseType = $this->normalizeDocumentBaseType($this->documentBaseType($type));

        if (in_array($baseType, ['id_card', 'address_certificate'], true)) {
            return null;
        }

        if (preg_match('/_(\d+)$/', $type, $matches)) {
            return "{$baseType}_{$matches[1]}";
        }

        if (in_array($baseType, ['diploma', 'recommendation_letter', 'scientific_works', 'other'], true)) {
            return "{$baseType}_1";
        }

        return $baseType;
    }
}
