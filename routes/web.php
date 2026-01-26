<?php

use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EOController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['role:system_admin'])->group(function () {
        
        Route::resource('users', UserController::class);
        Route::resource('statuses', StatusController::class);
        Route::resource('departments', DepartmentController::class);
    });

Route::middleware(['auth', 'verified'])->group(function () {
    
    Route::resource('eo', EOController::class);
});

require __DIR__.'/settings.php';
