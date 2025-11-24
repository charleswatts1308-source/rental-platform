<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RentalController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    // Profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Complete rental CRUD routes
    Route::get('/rentals', [RentalController::class, 'index'])->name('rentals.index');
    Route::get('/rentals/create', [RentalController::class, 'create'])->name('rentals.create');
    Route::post('/rentals', [RentalController::class, 'store'])->name('rentals.store');
    Route::get('/rentals/{rental}', [RentalController::class, 'show'])->name('rentals.show');
    Route::get('/rentals/{rental}/edit', [RentalController::class, 'edit'])->name('rentals.edit');
    Route::put('/rentals/{rental}', [RentalController::class, 'update'])->name('rentals.update');
    Route::delete('/rentals/{rental}', [RentalController::class, 'destroy'])->name('rentals.destroy');
});

require __DIR__.'/auth.php';
