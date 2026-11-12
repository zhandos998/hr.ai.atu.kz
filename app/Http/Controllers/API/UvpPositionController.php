<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PersonnelRequest;
use App\Models\UvpPosition;
use App\Models\Vacancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UvpPositionController extends Controller
{
    public function index()
    {
        return UvpPosition::orderBy('id')->get(['id', 'name']);
    }

    public function store(Request $request)
    {
        $data = $this->validatedName($request);

        return response()->json(UvpPosition::create($data), 201);
    }

    public function update(Request $request, int $id)
    {
        $position = UvpPosition::findOrFail($id);
        $position->update($this->validatedName($request, $id));

        return $position;
    }

    public function destroy(int $id)
    {
        DB::transaction(function () use ($id) {
            $position = UvpPosition::lockForUpdate()->findOrFail($id);
            if (PersonnelRequest::where('uvp_position_id', $id)->orWhere('target_uvp_position_id', $id)->exists() ||
                Vacancy::withTrashed()->where('uvp_position_id', $id)->exists()) {
                throw ValidationException::withMessages(['name' => __('messages.uvp_position_in_use')]);
            }
            $position->delete();
        });

        return response()->noContent();
    }

    private function validatedName(Request $request, ?int $id = null): array
    {
        if (is_string($request->input('name'))) {
            $request->merge(['name' => trim($request->input('name'))]);
        }

        return $request->validate(['name' => ['required', 'string', 'max:255', Rule::unique('uvp_positions')->ignore($id)]]);
    }
}
