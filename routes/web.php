<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CriterionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(Auth::check() ? 'dashboard' : 'login');
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard')->middleware('permission:dashboard');

    // Suppliers
    Route::middleware('permission:suppliers')->group(function () {
        Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show')->whereNumber('supplier');
    });
    Route::middleware('permission:suppliers,manage')->group(function () {
        Route::get('/suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create');
        Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit')->whereNumber('supplier');
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update')->whereNumber('supplier');
        Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy')->whereNumber('supplier');
    });

    // Criteria
    Route::middleware('permission:criteria')->group(function () {
        Route::get('/criteria', [CriterionController::class, 'index'])->name('criteria.index');
    });
    Route::middleware('permission:criteria,manage')->group(function () {
        Route::post('/criteria', [CriterionController::class, 'store'])->name('criteria.store');
        Route::put('/criteria/weights', [CriterionController::class, 'updateWeights'])->name('criteria.weights');
        Route::put('/criteria/{criterion}', [CriterionController::class, 'update'])->name('criteria.update');
        Route::delete('/criteria/{criterion}', [CriterionController::class, 'destroy'])->name('criteria.destroy');
    });

    // Evaluations
    Route::middleware('permission:evaluations')->group(function () {
        Route::get('/evaluations', [EvaluationController::class, 'index'])->name('evaluations.index');
        Route::get('/evaluations/{evaluation}', [EvaluationController::class, 'show'])->name('evaluations.show')->whereNumber('evaluation');
        Route::post('/evaluations/{evaluation}/ai-narrative', [EvaluationController::class, 'aiNarrative'])
            ->name('evaluations.ai')->whereNumber('evaluation');
    });
    Route::middleware('permission:evaluations,manage')->group(function () {
        Route::get('/evaluations-create', [EvaluationController::class, 'create'])->name('evaluations.create');
        Route::post('/evaluations', [EvaluationController::class, 'store'])->name('evaluations.store');
        Route::delete('/evaluations/{evaluation}', [EvaluationController::class, 'destroy'])->name('evaluations.destroy')->whereNumber('evaluation');
    });

    // Reports
    Route::middleware('permission:reports')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{evaluation}', [ReportController::class, 'show'])->name('reports.show')->whereNumber('evaluation');
        Route::get('/reports/{evaluation}/print', [ReportController::class, 'print'])->name('reports.print')->whereNumber('evaluation');
        Route::get('/reports/{evaluation}/pdf', [ReportController::class, 'pdf'])->name('reports.pdf')->whereNumber('evaluation');
        Route::get('/reports/{evaluation}/excel', [ReportController::class, 'excel'])->name('reports.excel')->whereNumber('evaluation');
    });

    // Activity history
    Route::middleware('permission:activity')->group(function () {
        Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');
    });
    Route::middleware('permission:activity,manage')->group(function () {
        Route::post('/activity/{activity}/revert', [ActivityController::class, 'revert'])->name('activity.revert')->whereNumber('activity');
        Route::post('/activity/{activity}/restore', [ActivityController::class, 'restore'])->name('activity.restore')->whereNumber('activity');
        Route::post('/activity/{activity}/force-delete', [ActivityController::class, 'forceDelete'])->name('activity.force-delete')->whereNumber('activity');
    });

    // Settings
    Route::middleware('permission:settings,manage')->group(function () {
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
        Route::post('/settings/test-ai', [SettingController::class, 'testAi'])->name('settings.test-ai');
    });

    // Permissions and users
    Route::middleware('permission:permissions,manage')->group(function () {
        Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::put('/permissions', [PermissionController::class, 'update'])->name('permissions.update');
        Route::post('/permissions/users', [PermissionController::class, 'storeUser'])->name('permissions.users.store');
        Route::put('/permissions/users/{user}', [PermissionController::class, 'updateUser'])->name('permissions.users.update');
        Route::delete('/permissions/users/{user}', [PermissionController::class, 'destroyUser'])->name('permissions.users.destroy');
    });

    // Database tools
    Route::middleware('permission:database,manage')->group(function () {
        Route::get('/database', [DatabaseController::class, 'index'])->name('database.index');
        Route::get('/database/export/{format}', [DatabaseController::class, 'export'])->name('database.export');
        Route::get('/database/export-table/{table}', [DatabaseController::class, 'exportTable'])->name('database.export-table');
        Route::get('/database/table/{table}', [DatabaseController::class, 'showTable'])->name('database.table');
        Route::post('/database/import', [DatabaseController::class, 'import'])->name('database.import');
    });
});
