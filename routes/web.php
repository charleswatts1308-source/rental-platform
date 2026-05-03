<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\FileAttachmentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\RentalController;
use App\Http\Controllers\Webhooks\MailgunInboundController;
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
    return view('welcome-3');
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

    // Contact us
    Route::get('/contact', [ContactMessageController::class, 'create'])->name('contact.create');
    Route::post('/contact', [ContactMessageController::class, 'store'])->name('contact.store');

    // Properties (tenant-side registry)
    Route::get('/properties', [PropertyController::class, 'index'])->name('properties.index');
    Route::get('/properties/create', [PropertyController::class, 'create'])->name('properties.create');
    Route::post('/properties', [PropertyController::class, 'store'])->name('properties.store');
    Route::get('/properties/{property}/edit', [PropertyController::class, 'edit'])->name('properties.edit');
    Route::patch('/properties/{property}', [PropertyController::class, 'update'])->name('properties.update');

    // Repair cases (Landlord Contact Service)
    Route::get('/cases', [CaseController::class, 'index'])->name('cases.index');
    Route::get('/cases/create', [CaseController::class, 'create'])->name('cases.create');
    Route::post('/cases', [CaseController::class, 'store'])->name('cases.store');
    Route::get('/cases/{slug}', [CaseController::class, 'show'])->name('cases.show');
    Route::post('/cases/{slug}/send-next', [CaseController::class, 'sendNext'])->name('cases.send-next');
    Route::post('/cases/{slug}/hold', [CaseController::class, 'hold'])->name('cases.hold');
    Route::post('/cases/{slug}/resolve', [CaseController::class, 'resolve'])->name('cases.resolve');
    Route::post('/cases/{slug}/abandon', [CaseController::class, 'abandon'])->name('cases.abandon');
    Route::post('/cases/{slug}/re-engage', [CaseController::class, 'reEngage'])->name('cases.re-engage');

    // File attachment routes
    Route::get('/files/{id}/download', [FileAttachmentController::class, 'download'])->name('files.download');
    Route::delete('/files/{id}', [FileAttachmentController::class, 'destroy'])->name('files.destroy');

    // Auth-only member pages
    Route::prefix('members')->name('members.')->group(function () {
        Route::get('/tenantandlandlord', fn() => view('members.tenantandlandlord'))->name('tenantandlandlord');
        Route::get('/thought-experiment', fn() => view('members.thought-experiment'))->name('thought-experiment');
        Route::get('/equity-conversation', fn() => view('members.equity-conversation'))->name('equity-conversation');
    });
});

// Public member pages
Route::prefix('members')->name('members.')->group(function () {
    Route::get('/renters-rights-act', fn() => view('members.renters-rights-act'))->name('renters-rights-act');
    Route::get('/know-your-landlord', fn() => view('members.know-your-landlord'))->name('know-your-landlord');
    Route::get('/landlord-database', fn() => view('members.landlord-database'))->name('landlord-database');
    Route::get('/repair-notices', fn() => view('members.repair-notices'))->name('repair-notices');
    Route::get('/scale-of-prs', fn() => view('members.scale-of-prs'))->name('scale-of-prs');
    Route::get('/property-types', fn() => view('members.property-types'))->name('property-types');
    Route::get('/renter-rights-explained', fn() => view('members.renter-rights-explained'))->name('renter-rights-explained');
});

// Admin routes (user ID 1 only)
Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::get('/page-views', [AdminController::class, 'pageViews'])->name('page-views');
    Route::get('/rentals', [AdminController::class, 'rentals'])->name('rentals');
    Route::get('/contact-messages', [AdminController::class, 'contactMessages'])->name('contact-messages');
    Route::get('/contact-messages/{contactMessage}', [AdminController::class, 'contactMessageShow'])->name('contact-messages.show');
    Route::post('/contact-messages/{contactMessage}/reply', [AdminController::class, 'contactMessageReply'])->name('contact-messages.reply');
});

Route::post('/webhooks/mailgun/inbound', MailgunInboundController::class)
    ->middleware('verify.mailgun.signature')
    ->name('webhooks.mailgun.inbound');

require __DIR__.'/auth.php';
