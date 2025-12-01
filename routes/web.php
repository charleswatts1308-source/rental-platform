<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RentalController;
use App\Http\Controllers\FileAttachmentController;
use App\Http\Controllers\PageViewController;
use Illuminate\Support\Facades\Route;

Route::get('/about', function () {
    return view('about');
});

Route::get('/privacy', function () {
    return view('privacy');
});

Route::get('/old-home-page', function () {
    return view('old-home-page');
})->name('old-home-page');

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

    // Rental routes (delete functionality removed for GDPR compliance)
    Route::get('/rentals', [RentalController::class, 'index'])->name('rentals.index');
    Route::get('/rentals/create', [RentalController::class, 'create'])->name('rentals.create');
    Route::post('/rentals', [RentalController::class, 'store'])->name('rentals.store');
    Route::get('/rentals/{rental}', [RentalController::class, 'show'])->name('rentals.show');
    Route::get('/rentals/{rental}/edit', [RentalController::class, 'edit'])->name('rentals.edit');
    Route::put('/rentals/{rental}', [RentalController::class, 'update'])->name('rentals.update');

    // File attachment routes
    Route::get('/files/{id}/download', [FileAttachmentController::class, 'download'])->name('files.download');
    Route::delete('/files/{id}', [FileAttachmentController::class, 'destroy'])->name('files.destroy');

    // Page view stats (TODO: Add admin authorization later)
    Route::get('/stats/page-views', [PageViewController::class, 'index'])->name('stats.page-views');

    // Member information pages
    Route::prefix('members')->name('members.')->group(function () {
        Route::get('/renters-rights-bill', fn() => view('members.renters-rights-bill'))->name('renters-rights-bill');
        Route::get('/know-your-landlord', fn() => view('members.know-your-landlord'))->name('know-your-landlord');
        Route::get('/renter-database', fn() => view('members.renter-database'))->name('renter-database');
        Route::get('/landlord-database', fn() => view('members.landlord-database'))->name('landlord-database');
        Route::get('/renter-support-services', fn() => view('members.renter-support-services'))->name('renter-support-services');
        Route::get('/landlord-support-services', fn() => view('members.landlord-support-services'))->name('landlord-support-services');
        Route::get('/property-data-services', fn() => view('members.property-data-services'))->name('property-data-services');
    });
});

require __DIR__.'/auth.php';
