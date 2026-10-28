<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\WorkerImportService;
use App\Services\WorkerSpreadsheetReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkerImportController extends Controller
{
    public function preview(Request $request, WorkerImportService $service): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:2048', 'mimes:xlsx', 'extensions:xlsx']]);

        return response()->json($service->preview($request->file('file'), $request->user()))->header('Cache-Control', 'private, no-store');
    }

    public function apply(Request $request, WorkerImportService $service): JsonResponse
    {
        $validated = $request->validate([
            'batch_id' => ['required', 'uuid'],
            'rows' => ['required', 'array', 'min:1', 'max:'.WorkerSpreadsheetReader::MAX_ROWS],
            'rows.*.row_number' => ['required', 'integer', 'distinct', 'min:1'],
            'rows.*.action' => ['required', Rule::in(['existing', 'create', 'skip'])],
            'rows.*.user_id' => ['nullable', 'integer'],
            'rows.*.position_id' => ['nullable', 'integer'],
        ]);

        return response()->json(['result' => $service->apply($validated['batch_id'], $validated['rows'], $request->user())])->header('Cache-Control', 'private, no-store');
    }
}
