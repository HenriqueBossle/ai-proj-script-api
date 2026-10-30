<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ScriptController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protegidas — precisam do token
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/scripts/{script}/revise', [ScriptController::class, 'revise'])
        ->middleware('throttle:script-revision');

    Route::apiResource('/scripts', ScriptController::class)
        ->middlewareFor(['store', 'update'], 'throttle:script-generation');
});
