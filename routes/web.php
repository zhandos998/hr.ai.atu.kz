<?php

use App\Http\Controllers\PublicApplicationVerificationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/documents/{path}', \App\Http\Controllers\DocumentController::class)
    ->where('path', '.*')->middleware('signed')->name('documents.show');

Route::get('/verify/applications/{application}', PublicApplicationVerificationController::class)
    ->middleware('signed')
    ->name('applications.verify');

foreach ([
    '/' => 'Home',
    '/login' => 'Auth/Login',
    '/register' => 'Auth/Register',
    '/verify-email' => 'Auth/VerifyEmail',
    '/forgot-password' => 'Auth/ForgotPassword',
    '/reset-password' => 'Auth/ResetPassword',
    '/email-change-confirm' => 'Auth/EmailChangeConfirm',
    '/personnel-status' => 'Personnel/PublicStatus',
    '/profile' => 'Profile/Index',
    '/profile/change-password' => 'Profile/ChangePassword',
    '/profile/change-email' => 'Profile/ChangeEmail',
    '/vacancies' => 'Vacancies',
    '/upload-resume' => 'UploadResume',
    '/my-applications' => 'MyApplications',
    '/apply' => 'Apply',
    '/chat' => 'Chat',
] as $uri => $component) {
    Route::get($uri, fn () => Inertia::render($component));
}

foreach ([
    '/admin' => 'Admin/Dashboard',
    '/admin/departments' => 'Admin/Departments/Index',
    '/admin/departments/create' => 'Admin/Departments/Form',
    '/admin/positions' => 'Admin/Positions/Index',
    '/admin/pps-positions' => 'Admin/PpsPositions/Index',
    '/admin/pps-positions/create' => 'Admin/PpsPositions/Create',
    '/admin/positions/create' => 'Admin/Positions/Create',
    '/admin/structure-tree' => 'Admin/StructureTree',
    '/admin/commission-members' => 'Admin/CommissionMembers',
    '/admin/users' => 'Admin/Users',
    '/admin/users/import' => 'Admin/UserImport',
    '/admin/vacancies' => 'Admin/Vacancies/Index',
    '/admin/vacancies/create' => 'Admin/Vacancies/Create',
    '/admin/applications' => 'Admin/Applications/Index',
    '/admin/competitions' => 'Admin/Competitions/Index',
    '/admin/competitions/create' => 'Admin/Competitions/Form',
] as $uri => $component) {
    Route::get($uri, fn () => Inertia::render($component));
}

Route::get('/admin/academic-units', fn () => Inertia::render('Admin/Departments/Index', ['academic' => true]));
Route::get('/admin/users/technical', fn () => Inertia::render('Admin/Users', ['technical' => true]));
Route::get('/admin/academic-units/create', fn () => Inertia::render('Admin/Departments/Form', ['academic' => true]));
Route::get('/admin/academic-units/{unit}/edit', fn (int $unit) => Inertia::render('Admin/Departments/Form', ['academic' => true, 'departmentId' => $unit]))->whereNumber('unit');

Route::get('/admin/departments/{department}/edit', fn (int $department) => Inertia::render('Admin/Departments/Form', [
    'departmentId' => $department,
]))->whereNumber('department');
Route::get('/admin/departments/{department}', fn (int $department) => Inertia::render('Admin/Departments/Show', [
    'departmentId' => $department,
]))->whereNumber('department');
Route::get('/admin/positions/{position}', fn (int $position) => Inertia::render('Admin/Positions/Show', [
    'positionId' => $position,
]))->whereNumber('position');

Route::get('/admin/applications/{application}', fn (int $application) => Inertia::render('Admin/Applications/Show', [
    'applicationId' => $application,
]))->whereNumber('application');

Route::get('/admin/competitions/{competition}/edit', fn (int $competition) => Inertia::render('Admin/Competitions/Form', ['competitionId' => $competition]))->whereNumber('competition');
Route::get('/admin/competitions/{competition}/add', fn (int $competition) => Inertia::render('Admin/Competitions/AddCandidates', ['competitionId' => $competition]))->whereNumber('competition');
Route::get('/admin/competitions/{competition}', fn (int $competition) => Inertia::render('Admin/Applications/Index', ['competitionId' => $competition]))->whereNumber('competition');

Route::get('/lawyer/applications', fn () => Inertia::render('Reviews/LawyerQueue'));

foreach ([
    'science' => '/science/applications',
    'digital' => '/digital/applications',
    'strategy' => '/strategy/applications',
    'academic' => '/academic/applications',
    'library' => '/library/applications',
    'professional' => '/professional-development/applications',
    'compliance' => '/compliance/applications',
] as $reviewType => $uri) {
    Route::get($uri, fn () => Inertia::render('Reviews/Queue', [
        'reviewType' => $reviewType,
    ]));
    Route::get("{$uri}/{application}", fn (int $application) => Inertia::render('Reviews/Show', [
        'reviewType' => $reviewType,
        'applicationId' => $application,
    ]))->whereNumber('application');
}

Route::get('/commission/applications', fn () => Inertia::render('Commission/Index'));
Route::get('/commission/applications/{application}', fn (int $application) => Inertia::render('Commission/Show', [
    'applicationId' => $application,
]))->whereNumber('application');

Route::get('/admin/personnel-requests', fn () => Inertia::render('Personnel/Index', [
    'admin' => true,
]))->name('inertia.admin.personnel');
Route::get('/admin/personnel-requests/create', fn () => Inertia::render('Personnel/RequestForm', [
    'mode' => 'create',
    'requestId' => null,
]));
Route::get('/admin/personnel-approval-routes', fn () => Inertia::render('Personnel/ApprovalRoutes'));
Route::get('/admin/personnel-requests/{personnelRequest}/edit', fn (int $personnelRequest) => Inertia::render('Personnel/RequestForm', [
    'mode' => 'edit',
    'requestId' => $personnelRequest,
]))->whereNumber('personnelRequest');
Route::get('/admin/personnel-requests/{personnelRequest}', fn (int $personnelRequest) => Inertia::render('Personnel/Show', [
    'admin' => true,
    'requestId' => $personnelRequest,
]))->whereNumber('personnelRequest');

Route::get('/personnel/requests', fn () => Inertia::render('Personnel/Index', [
    'admin' => false,
]))->name('inertia.personnel');
Route::get('/personnel/requests/{personnelRequest}', fn (int $personnelRequest) => Inertia::render('Personnel/Show', [
    'admin' => false,
    'requestId' => $personnelRequest,
]))->whereNumber('personnelRequest');

Route::fallback(fn () => abort(404));
