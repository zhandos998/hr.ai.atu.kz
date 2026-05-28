<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TeacherAuditController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $apiKey = config('services.teacher_audit.api_key');

        if (! $apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'Не настроен ключ КМК аудита.',
                'data' => [],
            ], 500);
        }

        try {
            $http = Http::timeout(10)
                ->acceptJson()
                ->withHeaders([
                    'X-API-Key' => $apiKey,
                ]);

            if (! config('services.teacher_audit.verify_ssl')) {
                $http = $http->withoutVerifying();
            }

            $response = $http->get(config('services.teacher_audit.url'), [
                'name' => $validated['name'],
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Teacher audit request failed.', [
                'name' => $validated['name'],
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Не удалось получить КМК аудит.',
                'data' => [],
            ], 502);
        }

        if (! $response->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Сервис КМК аудита временно недоступен.',
                'data' => [],
            ], 502);
        }

        $payload = $response->json();

        if (! is_array($payload) || ! array_key_exists('data', $payload)) {
            return response()->json([
                'success' => false,
                'message' => 'Сервис КМК аудита вернул некорректный ответ.',
                'data' => [],
            ], 502);
        }

        $normalizedPayload = [
            'success' => (bool) ($payload['success'] ?? true),
            'data' => $payload['data'] ?? [],
        ];
        $normalizedPayload['matches_found'] = $payload['matches_found']
            ?? (is_array($normalizedPayload['data']) ? count($normalizedPayload['data']) : 0);

        if (isset($payload['message'])) {
            $normalizedPayload['message'] = $payload['message'];
        }

        return response()->json($normalizedPayload);
    }
}
