<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Clients\ClientContactController;
use App\Http\Controllers\Clients\ClientController;
use App\Http\Controllers\Clients\ClientLookupController;
use App\Http\Controllers\Clients\ClientStatusController;
use App\Http\Controllers\Clients\ClientUserLinkController;
use App\Http\Controllers\Clients\MergeClientController;
use App\Http\Controllers\Clients\QuickCreateClientController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LoginHistoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Settings\BranchController;
use App\Http\Controllers\Settings\MasterDataController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\UiStyleGuideController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/up', HealthController::class)->name('health');
Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.update');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])
        ->name('profile.password.update');

    Route::patch('/users/{user}/status', UserStatusController::class)->name('users.status.update');
    Route::resource('users', UserController::class);

    Route::get('/clients/lookup', ClientLookupController::class)->name('clients.lookup');
    Route::post('/clients/quick-create', QuickCreateClientController::class)->name('clients.quick-create');
    Route::patch('/clients/{client}/status', ClientStatusController::class)->name('clients.status');
    Route::put('/clients/{client}/user-link', ClientUserLinkController::class)->name('clients.user-link');
    Route::post('/clients/{client}/merge', MergeClientController::class)->name('clients.merge');
    Route::post('/clients/{client}/contacts', [ClientContactController::class, 'store'])->name('clients.contacts.store');
    Route::get('/clients/{client}/contacts/{contact}/edit', [ClientContactController::class, 'edit'])->name('clients.contacts.edit');
    Route::put('/clients/{client}/contacts/{contact}', [ClientContactController::class, 'update'])->name('clients.contacts.update');
    Route::delete('/clients/{client}/contacts/{contact}', [ClientContactController::class, 'destroy'])->name('clients.contacts.destroy');
    Route::resource('clients', ClientController::class)->except(['destroy']);

    Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');
    Route::get('/audit/logins', [LoginHistoryController::class, 'index'])->name('audit.logins.index');
    Route::get('/audit/logins/{loginHistory}', [LoginHistoryController::class, 'show'])->name('audit.logins.show');
    Route::get('/audit/{auditLog}', [AuditLogController::class, 'show'])->name('audit.show');

    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/create', [DocumentController::class, 'create'])->name('documents.create');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::post('/documents/{document}/versions', [DocumentController::class, 'replace'])->name('documents.versions.store');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('/documents/{document}/versions/{version}/download', [DocumentController::class, 'downloadVersion'])->name('documents.versions.download');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings/company', [SettingsController::class, 'updateCompany'])->name('settings.company.update');
    Route::put('/settings/system', [SettingsController::class, 'updateSystem'])->name('settings.system.update');
    Route::resource('/settings/branches', BranchController::class)
        ->except(['show'])
        ->names('settings.branches');
    Route::prefix('/settings/master-data/{type}')->name('settings.master-data.')->group(function () {
        Route::get('/', [MasterDataController::class, 'index'])->name('index');
        Route::get('/create', [MasterDataController::class, 'create'])->name('create');
        Route::post('/', [MasterDataController::class, 'store'])->name('store');
        Route::get('/{category}/edit', [MasterDataController::class, 'edit'])->name('edit');
        Route::put('/{category}', [MasterDataController::class, 'update'])->name('update');
        Route::delete('/{category}', [MasterDataController::class, 'destroy'])->name('destroy');
    });

    if (app()->environment(['local', 'testing'])) {
        Route::get('/style-guide', UiStyleGuideController::class)->name('style-guide');
    }
});
