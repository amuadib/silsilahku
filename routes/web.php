<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;


Route::middleware(['auth', 'verified'])->group(function () {
Volt::route('/', 'dashboard')->name('dashboard');
    Volt::route('profil', 'pengguna.profil')->name('profil');
    Volt::route('pengguna/{id}/edit', 'pengguna.edit')->name( 'pengguna.edit');
    Volt::route('activity-log', 'activity-log.index')
        ->name('activity-log.index')
        ->middleware('can:viewAny,App\Models\ActivityLog');
    Volt::route('database', 'database.management')->name('database.management');
    Volt::route('pengguna/{id}', 'pengguna.show')->name('pengguna.show');
});

require __DIR__.'/settings.php';

Volt::route('pengguna/{id}/pohon', 'pengguna.pohon')->name('pengguna.pohon');
