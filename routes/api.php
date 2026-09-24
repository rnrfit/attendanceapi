<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\StudentEmbeddingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All routes below require a valid X-API-Key header (see ApiKeyAuth
| middleware) and are scoped server-side to the tenant/location configured
| in .env (config/tenant.php) — never trusted from the request.
|
*/

Route::prefix('v1')->middleware('api.key')->group(function () {
    Route::get('/students', [StudentController::class, 'all']);
    Route::get('/students/today', [StudentController::class, 'today']);

    Route::get('/students/{studentId}/embeddings', [StudentEmbeddingController::class, 'index']);
    Route::post('/students/{studentId}/embeddings', [StudentEmbeddingController::class, 'store']);
    Route::post('/students/{studentId}/embeddings/replace', [StudentEmbeddingController::class, 'replace']);
    Route::get('/embeddings', [StudentEmbeddingController::class, 'all']);
    Route::get('/embeddings/today', [StudentEmbeddingController::class, 'today']);
    Route::post('/embeddings/match', [StudentEmbeddingController::class, 'match']);

    Route::get('/attendance/stats', [AttendanceController::class, 'stats']);
    Route::get('/attendance', [AttendanceController::class, 'index']);
    Route::post('/attendance', [AttendanceController::class, 'store']);
});
