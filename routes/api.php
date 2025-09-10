<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatchController;
use App\Http\Controllers\Api\GoalController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Authentication routes
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
        Route::get('user', [AuthController::class, 'user']);
        Route::put('profile', [AuthController::class, 'updateProfile']);
        Route::put('preferences', [AuthController::class, 'updatePreferences']);
        Route::put('password', [AuthController::class, 'changePassword']);
        Route::delete('account', [AuthController::class, 'deleteAccount']);
    });

    // Catch management
    Route::prefix('catches')->group(function () {
        Route::get('/', [CatchController::class, 'index']);
        Route::post('/', [CatchController::class, 'store']);
        Route::get('statistics', [CatchController::class, 'statistics']);
        Route::get('nearby', [CatchController::class, 'nearby']);
        Route::get('{catch}', [CatchController::class, 'show']);
        Route::put('{catch}', [CatchController::class, 'update']);
        Route::delete('{catch}', [CatchController::class, 'destroy']);
    });

    // Goal management
    Route::prefix('goals')->group(function () {
        Route::get('/', [GoalController::class, 'index']);
        Route::post('/', [GoalController::class, 'store']);
        Route::get('statistics', [GoalController::class, 'statistics']);
        Route::post('refresh-progress', [GoalController::class, 'refreshProgress']);
        Route::get('{goal}', [GoalController::class, 'show']);
        Route::put('{goal}', [GoalController::class, 'update']);
        Route::delete('{goal}', [GoalController::class, 'destroy']);
        Route::post('{goal}/complete', [GoalController::class, 'complete']);
        Route::post('{goal}/pause', [GoalController::class, 'pause']);
        Route::post('{goal}/resume', [GoalController::class, 'resume']);
    });

    // Health check for authenticated requests
    Route::get('health', function (Request $request) {
        return response()->json([
            'status' => 'ok',
            'user' => $request->user()->name,
            'timestamp' => now()->toISOString(),
        ]);
    });
});