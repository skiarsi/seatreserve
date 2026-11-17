<?php

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::seat-reservation')->name('home');

Route::middleware('guest')->group(function (): void {
    Route::livewire('/login', 'pages::auth.login')->name('login');
    Route::livewire('/register', 'pages::auth.register')->name('register');
});

Route::match(['get', 'post'], '/logout', function (Request $request): RedirectResponse {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('home');
})->middleware('auth')->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::livewire('/my-reservations', 'pages::my-reservations')->name('reservations.mine');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::livewire('/layout', 'pages::admin.hall-layout')->name('layout');
    Route::livewire('/reservations', 'pages::admin.reservations')->name('reservations');
});
