<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationPpsProfile;
use App\Models\ApplicationPpsProfileDocument;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ScienceApplicationController extends Controller
{
    private const SCIENTIFIC_WORKS_CATEGORY = 'scientific_works';

    public function queue()
    {
        $applications = $this->scienceApplicationsQuery()
            ->where('resume_status', 'accepted')
            ->whereNotIn('hiring_status', ['hired', 'rejected'])
            ->latest()
            ->get();

        $applications->transform(fn (Application $application) => $this->transformApplication($application));

        return response()->json($applications);
    }

    public function show(int $id)
    {
        $application = $this->scienceApplicationsQuery()->findOrFail($id);

        return response()->json($this->transformApplication($application));
    }

    public function updateScientificWorks(Request $request, int $id)
    {
        $application = Application::with(['vacancy', 'ppsProfile.documents'])->notArchived()->findOrFail($id);

        if ($application->vacancy?->type !== 'pps') {
            return response()->json([
                'message' => 'Научные труды доступны только для заявок ППС.',
            ], 422);
        }

        if ($application->resume_status !== 'accepted') {
            return response()->json([
                'message' => 'Научные труды можно заполнять только после принятия резюме.',
            ], 422);
        }

        if (in_array($application->hiring_status, ['hired', 'rejected'], true)) {
            return response()->json([
                'message' => 'После финального решения комиссии научные труды редактировать нельзя.',
            ], 422);
        }

        $validated = $request->validate([
            'scientific_works' => 'nullable|string|max:8000',
            'documents' => 'nullable|array',
            'documents.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ]);

        $profile = $application->ppsProfile ?: new ApplicationPpsProfile([
            'application_id' => $application->id,
        ]);

        $profile->scientific_works = $this->emptyToNull($validated['scientific_works'] ?? null);

        $profile->save();

        $files = $request->file('documents', []);
        if ($files instanceof UploadedFile) {
            $files = [$files];
        }

        if (is_array($files)) {
            foreach ($files as $index => $file) {
                $path = $this->storeCategoryDocument($file, $application->id, $profile->id, 'scientific-works', $index);

                ApplicationPpsProfileDocument::create([
                    'application_pps_profile_id' => $profile->id,
                    'category' => self::SCIENTIFIC_WORKS_CATEGORY,
                    'original_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                ]);
            }
        }

        $application = $this->scienceApplicationsQuery()->findOrFail($id);

        return response()->json([
            'message' => 'Научные труды обновлены.',
            'application' => $this->transformApplication($application),
        ]);
    }

    public function deleteScientificWorksDocument(int $applicationId, int $documentId)
    {
        $application = Application::with(['vacancy', 'ppsProfile.documents'])->notArchived()->findOrFail($applicationId);

        if ($application->vacancy?->type !== 'pps') {
            return response()->json([
                'message' => 'Р”РѕРєСѓРјРµРЅС‚ РЅРµ РЅР°Р№РґРµРЅ.',
            ], 404);
        }

        if (in_array($application->hiring_status, ['hired', 'rejected'], true)) {
            return response()->json([
                'message' => 'РџРѕСЃР»Рµ С„РёРЅР°Р»СЊРЅРѕРіРѕ СЂРµС€РµРЅРёСЏ РєРѕРјРёСЃСЃРёРё РґРѕРєСѓРјРµРЅС‚С‹ РЅР°СѓС‡РЅС‹С… С‚СЂСѓРґРѕРІ СѓРґР°Р»СЏС‚СЊ РЅРµР»СЊР·СЏ.',
            ], 422);
        }

        $document = $application->ppsProfile?->documents
            ?->first(fn ($item) => (int) $item->id === $documentId && $item->category === self::SCIENTIFIC_WORKS_CATEGORY);

        if (!$document) {
            return response()->json([
                'message' => 'Р”РѕРєСѓРјРµРЅС‚ РЅР°СѓС‡РЅС‹С… С‚СЂСѓРґРѕРІ РЅРµ РЅР°Р№РґРµРЅ.',
            ], 404);
        }

        if (!empty($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        $application = $this->scienceApplicationsQuery()->findOrFail($applicationId);

        return response()->json([
            'message' => 'Р”РѕРєСѓРјРµРЅС‚ РЅР°СѓС‡РЅС‹С… С‚СЂСѓРґРѕРІ СѓРґР°Р»С‘РЅ.',
            'application' => $this->transformApplication($application),
        ]);
    }

    private function scienceApplicationsQuery()
    {
        return Application::query()
            ->notArchived()
            ->whereHas('vacancy', function ($query) {
                $query->where('type', 'pps');
            })
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
        return $this->attachPpsProfileDocumentUrls($this->attachDocumentUrls($application));
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

    private function attachPpsProfileDocumentUrls(Application $application): Application
    {
        if (!$application->relationLoaded('ppsProfile') || !$application->ppsProfile) {
            return $application;
        }

        foreach ($this->ppsProfileCategoryDocumentConfig() as $responseKey => $config) {
            $application->ppsProfile->{$responseKey} = $application->ppsProfile->documents
                ->where('category', $config['category'])
                ->values()
                ->map(function (ApplicationPpsProfileDocument $document) {
                    return [
                        'id' => $document->id,
                        'name' => $document->original_name ?: basename($document->file_path),
                        'url' => url(Storage::url($document->file_path)),
                    ];
                })
                ->all();
        }

        return $application;
    }

    private function ppsProfileCategoryDocumentConfig(): array
    {
        return [
            'basic_education_documents' => [
                'category' => 'basic_education',
            ],
            'magistracy_documents' => [
                'category' => 'magistracy',
            ],
            'scientific_degree_documents' => [
                'category' => 'scientific_degree',
            ],
            'academic_title_documents' => [
                'category' => 'academic_title',
            ],
            'scientific_works_documents' => [
                'category' => self::SCIENTIFIC_WORKS_CATEGORY,
            ],
        ];
    }

    private function storeCategoryDocument(UploadedFile $file, int $applicationId, int $profileId, string $directoryName, int $index): string
    {
        $extension = $file->getClientOriginalExtension();
        $directory = "applications/{$applicationId}/pps-profile/{$directoryName}";
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
