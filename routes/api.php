<?php

use App\Http\Controllers\API\AcademicApplicationController;
use App\Http\Controllers\API\AdminStructureController;
use App\Http\Controllers\API\AdminUserController;
use App\Http\Controllers\API\ApplicationController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CandidateAIController;
use App\Http\Controllers\API\ChatController;
use App\Http\Controllers\API\CommissionController;
use App\Http\Controllers\API\CompetitionController;
use App\Http\Controllers\API\ComplianceApplicationController;
use App\Http\Controllers\API\DepartmentController;
use App\Http\Controllers\API\DigitalApplicationController;
use App\Http\Controllers\API\FacultyController;
use App\Http\Controllers\API\LibraryApplicationController;
use App\Http\Controllers\API\PdfParseController;
use App\Http\Controllers\API\PersonnelRequestController;
use App\Http\Controllers\API\ProfessionalDevelopmentApplicationController;
use App\Http\Controllers\API\ResumeController;
use App\Http\Controllers\API\ScienceApplicationController;
use App\Http\Controllers\API\StrategyApplicationController;
use App\Http\Controllers\API\TeacherAuditController;
use App\Http\Controllers\API\VacancyController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])->middleware('throttle:mail');
Route::post('/email/verify', [AuthController::class, 'verifyEmail']);
Route::post('/password/forgot', [AuthController::class, 'forgotPassword'])->middleware('throttle:mail');
Route::post('/password/reset', [AuthController::class, 'resetPassword']);
Route::post('/user/email/change-confirm', [AuthController::class, 'confirmEmailChange']);

Route::get('/vacancies', [VacancyController::class, 'index']);
Route::get('/vacancies/{id}', [VacancyController::class, 'show']);
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/faculties', [FacultyController::class, 'index']);
    Route::get('/faculties/{faculty}/departments', [FacultyController::class, 'departments'])->whereNumber('faculty');
});
Route::post('/personnel/status', [PersonnelRequestController::class, 'publicStatus'])
    ->middleware('throttle:10,1');

// Route::post('/chat/send', [ChatController::class, 'send'])->middleware('throttle:30,1');
Route::middleware(['auth:sanctum', 'throttle:20,1'])->post('/chat/send', [ChatController::class, 'send']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/user/password', [AuthController::class, 'changePassword'])->middleware('throttle:10,1');
    Route::put('/user/contact-info', [AuthController::class, 'updateContactInfo']);
    Route::post('/user/email/change-request', [AuthController::class, 'requestEmailChange'])->middleware('throttle:mail');

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

    Route::get('/personnel/requests', [PersonnelRequestController::class, 'index']);
    Route::get('/personnel/requests/{id}', [PersonnelRequestController::class, 'show']);
    Route::post('/personnel/requests/{id}/decision', [PersonnelRequestController::class, 'decide'])->middleware(\App\Http\Middleware\LockPersonnelRequest::class);
    Route::get('/personnel/requests/{requestId}/documents/{documentId}', [PersonnelRequestController::class, 'document']);
});

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/admin/competitions', [CompetitionController::class, 'index']);
    Route::get('/admin/competitions/defaults', [CompetitionController::class, 'defaults']);
    Route::post('/admin/competitions', [CompetitionController::class, 'store']);
    Route::get('/admin/competitions/{competition}', [CompetitionController::class, 'show'])->whereNumber('competition');
    Route::put('/admin/competitions/{competition}', [CompetitionController::class, 'update'])->whereNumber('competition');
    Route::get('/admin/competitions/{competition}/candidates', [CompetitionController::class, 'candidates'])->whereNumber('competition');
    Route::post('/admin/competitions/{competition}/applications', [CompetitionController::class, 'attach'])->whereNumber('competition');
    Route::delete('/admin/competitions/{competition}/applications/{application}', [CompetitionController::class, 'detach'])->whereNumber(['competition', 'application']);
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
    Route::put('/admin/applications/{id}/voting-date', [ApplicationController::class, 'updateVotingDate']);
    Route::post('/admin/applications/{id}/resume', [ApplicationController::class, 'adminReplaceResume']);
    Route::post('/admin/applications/{id}/upload-docs', [ApplicationController::class, 'adminUploadDocs']);
    Route::delete('/admin/applications/{id}/documents/{documentId}', [ApplicationController::class, 'adminDeleteDocument']);
    Route::put('/admin/applications/{id}/staff-details', [ApplicationController::class, 'updateStaffDetails']);
    Route::post('/admin/applications/{id}/pps-profile', [ApplicationController::class, 'updatePpsProfile']);
    Route::delete('/admin/applications/{id}/pps-profile/documents/{documentId}', [ApplicationController::class, 'deletePpsProfileDocument']);
    Route::put('/admin/applications/{id}', [ApplicationController::class, 'updateStatus']);
    Route::get('/admin/applications/{id}/lawyer-response-pdf', [ApplicationController::class, 'adminLawyerResponsePdf']);
    Route::get('/admin/applications/{id}/academic-response-pdf', [AcademicApplicationController::class, 'academicResponsePdf']);
    Route::get('/admin/applications/{id}/science-response-pdf', [ScienceApplicationController::class, 'scienceResponsePdf']);
    Route::post('/check-candidate', [CandidateAIController::class, 'analyze']);

    Route::put('/admin/applications/{id}/accept-resume', [ApplicationController::class, 'acceptResume']);
    Route::put('/admin/applications/{id}/reject-resume', [ApplicationController::class, 'rejectResume']);
    Route::put('/admin/applications/{id}/accept-docs', [ApplicationController::class, 'acceptDocs']);
    Route::put('/admin/applications/{id}/reject-docs', [ApplicationController::class, 'rejectDocs']);
    Route::put('/admin/applications/{id}/complete', [ApplicationController::class, 'complete']);

    Route::get('/admin/academic-units', [\App\Http\Controllers\API\AcademicStructureController::class, 'index']);
    Route::post('/admin/academic-units', [\App\Http\Controllers\API\AcademicStructureController::class, 'store']);
    Route::put('/admin/academic-units/{id}', [\App\Http\Controllers\API\AcademicStructureController::class, 'update']);
    Route::delete('/admin/academic-units/{id}', [\App\Http\Controllers\API\AcademicStructureController::class, 'destroy']);
    Route::get('/admin/departments', [AdminStructureController::class, 'departments']);
    Route::get('/admin/departments/{id}', [AdminStructureController::class, 'showDepartment']);
    Route::post('/admin/departments', [AdminStructureController::class, 'storeDepartment']);
    Route::put('/admin/departments/{id}', [AdminStructureController::class, 'updateDepartment']);
    Route::delete('/admin/departments/{id}', [AdminStructureController::class, 'destroyDepartment']);

    Route::get('/admin/positions', [AdminStructureController::class, 'positions']);
    Route::get('/admin/pps-positions', [AdminStructureController::class, 'ppsPositions']);
    Route::post('/admin/pps-positions', [AdminStructureController::class, 'storePpsPosition']);
    Route::get('/admin/positions/{id}', [AdminStructureController::class, 'showPosition']);
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
    Route::get('/admin/user-position-options', [AdminUserController::class, 'positionOptions']);
    Route::post('/admin/users', [AdminUserController::class, 'store']);
    Route::post('/admin/users/{id}/impersonate', [AdminUserController::class, 'impersonate']);
    Route::put('/admin/users/{id}/role', [AdminUserController::class, 'updateRole']);
    Route::post('/admin/users/{id}/positions', [AdminUserController::class, 'assignPosition']);
    Route::delete('/admin/users/{id}/positions/{positionId}', [AdminUserController::class, 'detachPosition']);

    Route::post('/admin/personnel/requests', [PersonnelRequestController::class, 'store']);
    Route::post('/admin/personnel/requests/{id}/update', [PersonnelRequestController::class, 'update'])->middleware(\App\Http\Middleware\LockPersonnelRequest::class);
    Route::post('/admin/personnel/requests/{id}/documents', [PersonnelRequestController::class, 'uploadDocuments'])->middleware(\App\Http\Middleware\LockPersonnelRequest::class);
    Route::delete('/admin/personnel/requests/{id}/documents/{documentId}', [PersonnelRequestController::class, 'destroyDocument'])->middleware(\App\Http\Middleware\LockPersonnelRequest::class);
    Route::post('/admin/personnel/requests/{id}/approvers', [PersonnelRequestController::class, 'addApprover'])->middleware(\App\Http\Middleware\LockPersonnelRequest::class);
    Route::delete('/admin/personnel/requests/{id}/approvers/{approvalId}', [PersonnelRequestController::class, 'removeApprover'])->middleware(\App\Http\Middleware\LockPersonnelRequest::class);
    Route::get('/admin/personnel/reference-data', [PersonnelRequestController::class, 'referenceData']);
    Route::get('/admin/personnel/approval-routes', [PersonnelRequestController::class, 'approvalRoutes']);
    Route::put('/admin/personnel/approval-routes/{actionType}', [PersonnelRequestController::class, 'saveApprovalRoutes']);
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

Route::middleware(['auth:sanctum', 'professional_development'])->group(function () {
    Route::get('/professional-development/applications', [ProfessionalDevelopmentApplicationController::class, 'queue']);
    Route::get('/professional-development/applications/{id}', [ProfessionalDevelopmentApplicationController::class, 'show']);
    Route::post('/professional-development/applications/{id}/professional-development', [ProfessionalDevelopmentApplicationController::class, 'updateProfessionalDevelopment']);
    Route::delete('/professional-development/applications/{applicationId}/professional-development-documents/{documentId}', [ProfessionalDevelopmentApplicationController::class, 'deleteProfessionalDevelopmentDocument']);
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
Route::get('/applications/{id}', [ApplicationController::class, 'show'])->middleware('auth:sanctum')->name('api.applications.show');

Route::get('/departments', [DepartmentController::class, 'index']);
// Route::get('/positions', [DepartmentController::class, 'index']);

Route::post('/pdf/parse', [PdfParseController::class, 'parse'])->middleware(['auth:sanctum', 'throttle:10,1']);
