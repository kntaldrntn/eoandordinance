<?php

use App\Http\Controllers\CityEmployeeController;
use App\Http\Controllers\ClassificationController;
use App\Http\Controllers\CommitteeMemberController;
use App\Http\Controllers\CommitteeRegistryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EOController;
use App\Http\Controllers\ExternalMemberController;
use App\Http\Controllers\IRRController;
use App\Http\Controllers\MembershipController;
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
        Route::resource('classifications', ClassificationController::class);
        Route::resource('committee-members', CommitteeMemberController::class);
        Route::resource('committee-registries', CommitteeRegistryController::class);
        Route::post('/committee-registries/{id}/sync', [CommitteeRegistryController::class, 'syncMembers'])
            ->name('committee-registries.sync'); // Matches the route() call in your Vue file
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('eo', EOController::class);
    Route::resource('irr', IRRController::class);
    Route::resource('ordinances', OrdinanceController::class);
    
    // --- NEW: Custom IRR Routes for Ordinances ---
    Route::post('/ordinances/{ordinance}/irr', [OrdinanceController::class, 'storeIrr'])->name('ordinance.irr.store');
    Route::post('/irrs/{id}/disable', [OrdinanceController::class, 'disableIrr'])->name('ordinance.irr.disable');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/generate', [ReportController::class, 'generate'])->name('reports.generate');
    Route::resource('membership', MembershipController::class);
});

require __DIR__.'/settings.php';