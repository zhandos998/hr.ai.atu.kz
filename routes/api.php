<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ChatController;
use App\Http\Controllers\API\ResumeController;
use App\Http\Controllers\API\ApplicationController;
// use App\Http\Controllers\API\CandidateAIController;
use App\Http\Controllers\API\DepartmentController;
use App\Http\Controllers\API\AdminStructureController;
use App\Http\Controllers\API\CommissionController;
use App\Http\Controllers\API\AdminUserController;
use App\Http\Controllers\API\ScienceApplicationController;
use App\Http\Controllers\API\DigitalApplicationController;
use App\Http\Controllers\API\StrategyApplicationController;
use App\Http\Controllers\API\AcademicApplicationController;
use App\Http\Controllers\API\LibraryApplicationController;
use App\Http\Controllers\API\ComplianceApplicationController;
use App\Http\Controllers\API\PdfParseController;
use App\Http\Controllers\API\TeacherAuditController;
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
    Route::get('/teacher-audit', [TeacherAuditController::class, 'show']);

    Route::get('/application-statuses', function () {
        return \App\Models\ApplicationStatus::select('id', 'code', 'name')->get();
    });
    Route::post('/applications/{id}/upload-docs', [ApplicationController::class, 'uploadDocs']);
    Route::delete('/applications/{id}/documents/{documentId}', [ApplicationController::class, 'deleteDocument']);
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
    Route::post('/admin/applications', [ApplicationController::class, 'adminStore']);
    Route::get('/admin/applications/{id}', [ApplicationController::class, 'adminShow']);
    Route::put('/admin/applications/{id}/archive', [ApplicationController::class, 'archive']);
    Route::put('/admin/applications/{id}/unarchive', [ApplicationController::class, 'unarchive']);
    Route::post('/admin/applications/{id}/upload-docs', [ApplicationController::class, 'adminUploadDocs']);
    Route::delete('/admin/applications/{id}/documents/{documentId}', [ApplicationController::class, 'adminDeleteDocument']);
    Route::put('/admin/applications/{id}/staff-details', [ApplicationController::class, 'updateStaffDetails']);
    Route::post('/admin/applications/{id}/pps-profile', [ApplicationController::class, 'updatePpsProfile']);
    Route::delete('/admin/applications/{id}/pps-profile/documents/{documentId}', [ApplicationController::class, 'deletePpsProfileDocument']);
    Route::put('/admin/applications/{id}', [ApplicationController::class, 'updateStatus']);
    Route::get('/admin/applications/{id}/lawyer-response-pdf', [ApplicationController::class, 'adminLawyerResponsePdf']);
    Route::get('/admin/applications/{id}/academic-response-pdf', [AcademicApplicationController::class, 'academicResponsePdf']);
    Route::get('/admin/applications/{id}/science-response-pdf', [ScienceApplicationController::class, 'scienceResponsePdf']);

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

    Route::get('/admin/users', [AdminUserController::class, 'index']);
    Route::post('/admin/users', [AdminUserController::class, 'store']);
    Route::put('/admin/users/{id}/role', [AdminUserController::class, 'updateRole']);
});

Route::middleware(['auth:sanctum', 'lawyer'])->group(function () {
    Route::get('/lawyer/applications', [ApplicationController::class, 'lawyerQueue']);
    Route::put('/lawyer/applications/{id}/corruption-status', [ApplicationController::class, 'lawyerSetCorruptionStatus']);
});

Route::middleware(['auth:sanctum', 'science'])->group(function () {
    Route::get('/science/applications', [ScienceApplicationController::class, 'queue']);
    Route::get('/science/applications/{id}', [ScienceApplicationController::class, 'show']);
    Route::get('/science/applications/{id}/science-response-pdf', [ScienceApplicationController::class, 'scienceResponsePdf']);
    Route::post('/science/applications/{id}/scientific-works', [ScienceApplicationController::class, 'updateScientificWorks']);
    Route::delete('/science/applications/{applicationId}/scientific-works-documents/{documentId}', [ScienceApplicationController::class, 'deleteScientificWorksDocument']);
});

Route::middleware(['auth:sanctum', 'digital'])->group(function () {
    Route::get('/digital/applications', [DigitalApplicationController::class, 'queue']);
    Route::get('/digital/applications/{id}', [DigitalApplicationController::class, 'show']);
    Route::post('/digital/applications/{id}/digital-mooc', [DigitalApplicationController::class, 'updateDigitalMooc']);
    Route::delete('/digital/applications/{applicationId}/digital-mooc-documents/{documentId}', [DigitalApplicationController::class, 'deleteDigitalMoocDocument']);
});

Route::middleware(['auth:sanctum', 'strategy'])->group(function () {
    Route::get('/strategy/applications', [StrategyApplicationController::class, 'queue']);
    Route::get('/strategy/applications/{id}', [StrategyApplicationController::class, 'show']);
    Route::post('/strategy/applications/{id}/strategy-review', [StrategyApplicationController::class, 'updateStrategyReview']);
    Route::delete('/strategy/applications/{applicationId}/strategy-documents/{documentId}', [StrategyApplicationController::class, 'deleteStrategyDocument']);
});

Route::middleware(['auth:sanctum', 'academic'])->group(function () {
    Route::get('/academic/applications', [AcademicApplicationController::class, 'queue']);
    Route::get('/academic/applications/{id}', [AcademicApplicationController::class, 'show']);
    Route::get('/academic/applications/{id}/academic-response-pdf', [AcademicApplicationController::class, 'academicResponsePdf']);
    Route::post('/academic/applications/{id}/academic-review', [AcademicApplicationController::class, 'updateAcademicReview']);
    Route::delete('/academic/applications/{applicationId}/academic-documents/{documentId}', [AcademicApplicationController::class, 'deleteAcademicDocument']);
});

Route::middleware(['auth:sanctum', 'library'])->group(function () {
    Route::get('/library/applications', [LibraryApplicationController::class, 'queue']);
    Route::get('/library/applications/{id}', [LibraryApplicationController::class, 'show']);
    Route::post('/library/applications/{id}/library-metrics', [LibraryApplicationController::class, 'updateLibraryMetrics']);
    Route::delete('/library/applications/{applicationId}/library-documents/{documentId}', [LibraryApplicationController::class, 'deleteLibraryDocument']);
});

Route::middleware(['auth:sanctum', 'lawyer'])->group(function () {
    Route::get('/compliance/applications', [ComplianceApplicationController::class, 'queue']);
    Route::get('/compliance/applications/{id}', [ComplianceApplicationController::class, 'show']);
    Route::get('/compliance/applications/{id}/lawyer-response-pdf', [ApplicationController::class, 'adminLawyerResponsePdf']);
    Route::post('/compliance/applications/{id}/department-review', [ComplianceApplicationController::class, 'updateComplianceDepartment']);
    Route::delete('/compliance/applications/{applicationId}/documents/{documentId}', [ComplianceApplicationController::class, 'deleteComplianceDocument']);
});

Route::middleware(['auth:sanctum', 'commission'])->group(function () {
    Route::get('/commission/applications', [CommissionController::class, 'queue']);
    Route::get('/commission/applications/{id}', [CommissionController::class, 'show']);
    Route::post('/commission/applications/{id}/vote', [CommissionController::class, 'vote']);
});


Route::get('/vacancies', [VacancyController::class, 'index'])->name('api.vacancies.index');
Route::get('/vacancies/{id}', [VacancyController::class, 'show'])->name('api.vacancies.show');
// Если есть страница деталей заявки
Route::get('/applications/{id}', [ApplicationController::class, 'show'])->name('api.applications.show');

// AI-анализ кандидата временно отключен.
// Route::post('/check-candidate', [CandidateAIController::class, 'analyze']);


Route::get('/departments', [DepartmentController::class, 'index']);
// Route::get('/positions', [DepartmentController::class, 'index']);

Route::post('/pdf/parse', [PdfParseController::class, 'parse']);
