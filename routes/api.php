<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExamController; // <-- IMPORT THE NEW CONTROLLER

// This group protects all routes inside it. A user must be authenticated
// via Sanctum to access them.
Route::middleware(['auth:sanctum'])->group(function () {
    
    // The /user route from before now lives inside the group
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // 👇 OUR NEW ROUTE 👇
    // When a POST request is made to '/exams', call the 'store' method
    // on the ExamController.
    Route::post('/exams', [ExamController::class, 'store']);

    // We can add more exam-related routes here later (GET, UPDATE, DELETE)

});


// The auth routes (login, register) are outside the group because
// a user needs to be able to access them when they are NOT logged in.
require __DIR__.'/auth.php';