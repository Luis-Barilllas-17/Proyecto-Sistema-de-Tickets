<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    Route::get('/recuperar-contrasena', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/recuperar-contrasena', [ForgotPasswordController::class, 'store'])->name('password.email');
    Route::get('/restablecer-contrasena/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/restablecer-contrasena', [ResetPasswordController::class, 'store'])->name('password.store');
});

Route::middleware(['auth', 'active', 'session.timeout'])->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/cambiar-contrasena', [PasswordController::class, 'edit'])->name('password.change');
    Route::put('/cambiar-contrasena', [PasswordController::class, 'update'])->name('password.update');

    Route::middleware('password.changed')->group(function (): void {
        Route::get('/panel', DashboardController::class)->name('dashboard');
        Route::delete('/sesiones/{session}', [SessionController::class, 'destroy'])->name('sessions.destroy');

        Route::prefix('seguridad')->name('security.')->middleware('super.admin')->group(function (): void {
            Route::get('/', [SecurityController::class, 'index'])->name('index');
            Route::get('/usuarios/{user}/contrasena', [SecurityController::class, 'editPassword'])->name('password.edit');
            Route::put('/usuarios/{user}/contrasena', [SecurityController::class, 'updatePassword'])->name('password.update');
            Route::patch('/usuarios/{user}/estado', [SecurityController::class, 'updateStatus'])->name('users.status');
            Route::patch('/usuarios/{user}/desbloqueo', [SecurityController::class, 'unlock'])->name('users.unlock');
            Route::delete('/sesiones/{session}', [SecurityController::class, 'destroySession'])->name('sessions.destroy');
        });
    });
});
