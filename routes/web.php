<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RentalController;
use App\Http\Controllers\FileAttachmentController;
use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

Route::get('/about', function () {
    return view('about');
});

Route::get('/privacy', function () {
    return view('privacy');
});

Route::get('/cookies', function () {
    return view('cookies');
});

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

    // Member information pages
    Route::prefix('members')->name('members.')->group(function () {
        Route::get('/renters-rights-act', fn() => view('members.renters-rights-act'))->name('renters-rights-act');
        Route::get('/know-your-landlord', fn() => view('members.know-your-landlord'))->name('know-your-landlord');
        Route::get('/landlord-database', fn() => view('members.landlord-database'))->name('landlord-database');
        Route::get('/tenantandlandlord', fn() => view('members.tenantandlandlord'))->name('tenantandlandlord');
        Route::get('/the-long-game', fn() => view('members.the-long-game'))->name('the-long-game');
        Route::get('/repair-notices', fn() => view('members.repair-notices'))->name('repair-notices');
        Route::get('/the-big-picture', fn() => view('members.the-big-picture'))->name('the-big-picture');
        Route::get('/property-types', fn() => view('members.property-types'))->name('property-types');
        Route::get('/renter-rights-explained', fn() => view('members.renter-rights-explained'))->name('renter-rights-explained');
        Route::get('/beyond-repair', fn() => view('members.beyond-repair'))->name('beyond-repair');
    });
});

// Admin routes (user ID 1 only)
Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::get('/page-views', [AdminController::class, 'pageViews'])->name('page-views');
    Route::get('/rentals', [AdminController::class, 'rentals'])->name('rentals');
});

require __DIR__.'/auth.php';
