<?php

declare(strict_types=1);

use App\Http\Controllers\LogoutController;
use App\Livewire\Account;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Setup;
use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/setup', Setup::class)->middleware('setup.pending')->name('setup');
    Route::get('/login', Login::class)->middleware('setup.done')->name('login');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/', Dashboard::class)->name('dashboard');
    Route::get('/account', Account::class)->name('account');
    Route::post('/logout', LogoutController::class)->name('logout');
});
