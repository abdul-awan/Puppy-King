<?php

/*
|--------------------------------------------------------------------------
| Platform administration routes
|--------------------------------------------------------------------------
|
| The area for the people who run the service (users with platform admin
| rights): every company and user of the platform. Registered before the
| company workspace routes, so "/admin/..." is never read as a workspace.
|
*/

use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'verified', 'platform-admin'])
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('companies', [CompanyController::class, 'index'])->name('companies.index');
        Route::get('companies/{team}', [CompanyController::class, 'show'])->name('companies.show');
        Route::post('companies/{team}/suspend', [CompanyController::class, 'suspend'])->name('companies.suspend');
        Route::post('companies/{team}/reactivate', [CompanyController::class, 'reactivate'])->name('companies.reactivate');
        Route::delete('companies/{team}', [CompanyController::class, 'destroy'])->name('companies.destroy');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::post('users/{user}/admin', [UserController::class, 'grant'])->name('users.grant');
        Route::delete('users/{user}/admin', [UserController::class, 'revoke'])->name('users.revoke');
        Route::post('users/{user}/verify', [UserController::class, 'verify'])->name('users.verify');
    });
