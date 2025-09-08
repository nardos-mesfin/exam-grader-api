<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExamController; 
use App\Http\Controllers\ExamSubmissionController;

// This group protects all routes inside it. A user must be authenticated
// via Sanctum to access them.
Route::middleware(['auth:sanctum'])->group(function () {
    
    // The /user route from before now lives inside the group
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/exams', [ExamController::class, 'store']);
    Route::get('/exams', [ExamController::class, 'index']);

    Route::post('/exam-submissions', [ExamSubmissionController::class, 'store']);

});


// The auth routes (login, register) are outside the group because
// a user needs to be able to access them when they are NOT logged in.
require __DIR__.'/auth.php';