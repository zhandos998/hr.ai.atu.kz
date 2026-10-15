<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ResumeController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'application_id' => 'required|integer|exists:applications,id',
            'resume' => 'required|file|mimes:pdf,doc,docx|extensions:pdf,doc,docx|max:2048',
        ]);

        $application = \App\Models\Application::query()->notArchived()->where('user_id', $request->user()->id)->findOrFail($request->integer('application_id'));
        abort_unless($application->resume_status === 'pending' && in_array($application->hiring_status, ['not_started', 'voting'], true), 403);
        $oldPath = $application->resume?->file_path;
        $path = $request->file('resume')->store("resumes/{$application->id}", 'documents');

        try {
            $application->resume()->updateOrCreate(['application_id' => $application->id], [
                'user_id' => $request->user()->id,
                'file_path' => $path,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('documents')->delete($path);
            throw $exception;
        }

        if ($oldPath) {
            Storage::disk('documents')->delete($oldPath);
        }

        return response()->json(['message' => 'Resume uploaded successfully.']);
    }
}
