<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ChatController;
use App\Http\Controllers\API\ResumeController;
use App\Http\Controllers\API\ApplicationController;

use App\Http\Controllers\API\VacancyController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/vacancies', [VacancyController::class, 'index']);
Route::get('/vacancies/{id}', [VacancyController::class, 'show']);

Route::post('/chat/send', [ChatController::class, 'send'])->middleware('throttle:30,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);


    Route::post('/resumes', [ResumeController::class, 'store']);

    // Route::get('/applications', [ApplicationController::class, 'index']);
    Route::post('/applications', [ApplicationController::class, 'store']);

    Route::get('/applications', [ApplicationController::class, 'userApplications']);

    Route::get('/application-statuses', function () {
        return \App\Models\ApplicationStatus::select('id', 'code', 'name')->get();
    });
    Route::post('/applications/{id}/upload-docs', [ApplicationController::class, 'uploadDocs']);
});

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/admin/vacancies', [VacancyController::class, 'index']);
    Route::post('/admin/vacancies', [VacancyController::class, 'store']);
    Route::put('/admin/vacancies/{id}', [VacancyController::class, 'update']);
    Route::delete('/admin/vacancies/{id}', [VacancyController::class, 'destroy']);

    Route::get('/admin/applications', [ApplicationController::class, 'index']);
    Route::put('/admin/applications/{id}', [ApplicationController::class, 'updateStatus']);

    Route::put('/admin/applications/{id}/accept-resume', [ApplicationController::class, 'acceptResume']);
    Route::put('/admin/applications/{id}/reject-resume', [ApplicationController::class, 'rejectResume']);
    Route::put('/admin/applications/{id}/accept-docs', [ApplicationController::class, 'acceptDocs']);
    Route::put('/admin/applications/{id}/reject-docs', [ApplicationController::class, 'rejectDocs']);
    Route::put('/admin/applications/{id}/complete', [ApplicationController::class, 'complete']);
});


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



Route::get('/vacancies', [VacancyController::class, 'index'])->name('api.vacancies.index');
Route::get('/vacancies/{id}', [VacancyController::class, 'show'])->name('api.vacancies.show');
// (если есть страница детали заявки)
Route::get('/applications/{id}', [ApplicationController::class, 'show'])->name('api.applications.show');
