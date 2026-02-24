<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ChatController;
use App\Http\Controllers\API\ResumeController;
use App\Http\Controllers\API\ApplicationController;
use App\Http\Controllers\API\CandidateAIController;
use App\Http\Controllers\API\DepartmentController;
use App\Http\Controllers\API\AdminStructureController;
use App\Http\Controllers\API\CommissionController;
use App\Http\Controllers\Api\PdfParseController;
use App\Http\Controllers\API\VacancyController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/email/verification-notification', [AuthController::class, 'resendVerification']);
Route::post('/email/verify', [AuthController::class, 'verifyEmail']);
Route::post('/password/forgot', [AuthController::class, 'forgotPassword']);
Route::post('/password/reset', [AuthController::class, 'resetPassword']);
Route::post('/user/email/change-confirm', [AuthController::class, 'confirmEmailChange']);

Route::get('/vacancies', [VacancyController::class, 'index']);
Route::get('/vacancies/{id}', [VacancyController::class, 'show']);

//Route::post('/chat/send', [ChatController::class, 'send'])->middleware('throttle:30,1');
Route::middleware('auth:sanctum')->post('/chat/send', [ChatController::class, 'send']);


Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/user/password', [AuthController::class, 'changePassword']);
    Route::post('/user/email/change-request', [AuthController::class, 'requestEmailChange']);


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
    Route::get('/admin/vacancies/{id}/commission-candidates', [VacancyController::class, 'commissionCandidates']);
    Route::post('/admin/vacancies/{id}/commission-members', [VacancyController::class, 'addCommissionMember']);
    Route::delete('/admin/vacancies/{id}/commission-members/{userId}', [VacancyController::class, 'removeCommissionMember']);

    Route::get('/admin/applications', [ApplicationController::class, 'index']);
    Route::put('/admin/applications/{id}', [ApplicationController::class, 'updateStatus']);

    Route::put('/admin/applications/{id}/accept-resume', [ApplicationController::class, 'acceptResume']);
    Route::put('/admin/applications/{id}/reject-resume', [ApplicationController::class, 'rejectResume']);
    Route::put('/admin/applications/{id}/accept-docs', [ApplicationController::class, 'acceptDocs']);
    Route::put('/admin/applications/{id}/reject-docs', [ApplicationController::class, 'rejectDocs']);
    Route::put('/admin/applications/{id}/complete', [ApplicationController::class, 'complete']);

    Route::get('/admin/departments', [AdminStructureController::class, 'departments']);
    Route::post('/admin/departments', [AdminStructureController::class, 'storeDepartment']);
    Route::put('/admin/departments/{id}', [AdminStructureController::class, 'updateDepartment']);
    Route::delete('/admin/departments/{id}', [AdminStructureController::class, 'destroyDepartment']);

    Route::get('/admin/positions', [AdminStructureController::class, 'positions']);
    Route::post('/admin/positions', [AdminStructureController::class, 'storePosition']);
    Route::put('/admin/positions/{id}', [AdminStructureController::class, 'updatePosition']);
    Route::delete('/admin/positions/{id}', [AdminStructureController::class, 'destroyPosition']);
    Route::get('/admin/position-users', [AdminStructureController::class, 'positionUsers']);
    Route::post('/admin/positions/{id}/users', [AdminStructureController::class, 'attachUserToPosition']);
    Route::delete('/admin/positions/{id}/users/{userId}', [AdminStructureController::class, 'detachUserFromPosition']);

    Route::get('/admin/commission-members', [CommissionController::class, 'adminMembers']);
    Route::get('/admin/commission-candidates', [CommissionController::class, 'adminCandidateUsers']);
    Route::post('/admin/commission-members', [CommissionController::class, 'adminAddMember']);
    Route::delete('/admin/commission-members/{userId}', [CommissionController::class, 'adminRemoveMember']);
});

Route::middleware(['auth:sanctum', 'lawyer'])->group(function () {
    Route::get('/lawyer/applications', [ApplicationController::class, 'lawyerQueue']);
    Route::put('/lawyer/applications/{id}/corruption-status', [ApplicationController::class, 'lawyerSetCorruptionStatus']);
});

Route::middleware(['auth:sanctum', 'commission'])->group(function () {
    Route::get('/commission/applications', [CommissionController::class, 'queue']);
    Route::post('/commission/applications/{id}/vote', [CommissionController::class, 'vote']);
});


Route::get('/vacancies', [VacancyController::class, 'index'])->name('api.vacancies.index');
Route::get('/vacancies/{id}', [VacancyController::class, 'show'])->name('api.vacancies.show');
// Если есть страница деталей заявки
Route::get('/applications/{id}', [ApplicationController::class, 'show'])->name('api.applications.show');

Route::post('/check-candidate', [CandidateAIController::class, 'analyze']);


Route::get('/departments', [DepartmentController::class, 'index']);
// Route::get('/positions', [DepartmentController::class, 'index']);

Route::post('/pdf/parse', [PdfParseController::class, 'parse']);
