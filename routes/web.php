<?php

use App\Http\Controllers\CityEmployeeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EOController;
use App\Http\Controllers\ExternalMemberController;
use App\Http\Controllers\IRRController;
use App\Http\Controllers\OrdinanceController;
use App\Http\Controllers\PublicEOController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', [PublicEOController::class, 'index'])->name('home');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

Route::middleware(['role:system_admin'])->group(function () {
        Route::resource('users', UserController::class);
        Route::resource('statuses', StatusController::class);
        Route::resource('departments', DepartmentController::class);
        Route::resource('employees', CityEmployeeController::class);
        Route::resource('external-members', ExternalMemberController::class);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('eo', EOController::class);
    Route::resource('irr', IRRController::class);
    Route::resource('ordinances', OrdinanceController::class);
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/generate', [ReportController::class, 'generate'])->name('reports.generate');
});

require __DIR__.'/settings.php';