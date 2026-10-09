<?php

declare(strict_types=1);

use App\Http\Controllers\LogoutController;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Setup;
use App\Livewire\BankAccounts;
use App\Livewire\Dashboard;
use App\Livewire\Settings;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/setup', Setup::class)->middleware('setup.pending')->name('setup');
    Route::get('/login', Login::class)->middleware('setup.done')->name('login');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/', Dashboard::class)->name('dashboard');
    Route::get('/accounts', BankAccounts::class)->name('accounts');
    Route::get('/settings', Settings::class)->name('settings');
    Route::post('/logout', LogoutController::class)->name('logout');
});
