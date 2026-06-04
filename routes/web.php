<?php

use App\Http\Controllers\PublicApplicationVerificationController;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/verify/applications/{application}', PublicApplicationVerificationController::class)
    ->middleware('signed')
    ->name('applications.verify');

Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '.*');
