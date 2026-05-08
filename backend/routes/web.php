<?php

use App\Http\Controllers\Api\EmailVerificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// E-Mail-Verifikation (Link aus der E-Mail → Backend verifiziert → Redirect zum Frontend)
Route::get('/email-verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::get('/', function () {
    return view('welcome');
});
