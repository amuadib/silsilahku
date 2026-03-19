<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Http\Controllers\TreeController;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Volt::route('profil', 'pengguna.profil')->name('profil');
    Volt::route('pengguna/{id}/edit', 'pengguna.edit')->name( 'pengguna.edit');
});

require __DIR__.'/settings.php';

Volt::route('pengguna/{id}', 'pengguna.show')->name('pengguna.show');
Volt::route('pengguna/{id}/pohon', 'pengguna.pohon')->name('pengguna.pohon');
