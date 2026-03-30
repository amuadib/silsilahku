<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;


Volt::route('pengguna/{id}/pohon-v2', 'pengguna.pohon-v2')->name('pengguna.pohon-v2');
Volt::route('pengguna/{id}/pohon', 'pengguna.pohon')->name('pengguna.pohon');
Volt::route('pengguna/{id}/daftar', 'pengguna.daftar')->name('pengguna.daftar');
Volt::route('pengguna/{id}/daftar-collapse', 'pengguna.daftar-collapse')->name('pengguna.daftar-collapse');
Route::middleware(['auth', 'verified'])->group(function () {
Volt::route('/', 'dashboard')->name('dashboard');
    Volt::route('pengguna/{id}/edit', 'pengguna.edit')->name( 'pengguna.edit');
    Volt::route('pengguna/{id}', 'pengguna.show')->name('pengguna.show');
    Volt::route('activity-log', 'activity-log.index')
        ->name('activity-log.index')
        ->middleware('can:viewAny,App\Models\ActivityLog');
    Volt::route('database', 'database.management')->name('database.management');
});

require __DIR__.'/settings.php';

