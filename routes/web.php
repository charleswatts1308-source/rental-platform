<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Admin\CaseOversightController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PropertyController;
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
    Route::get('/cases/preview', [CaseController::class, 'preview'])->name('cases.preview');
    Route::post('/cases/preview/confirm', [CaseController::class, 'confirm'])->name('cases.confirm');
    Route::get('/cases/{slug}', [CaseController::class, 'show'])->name('cases.show');
    Route::post('/cases/{slug}/reply', [CaseController::class, 'reply'])->name('cases.reply');
    // D15 — engagement-gated escalation: tenant authorises a withheld notice.
    Route::get('/cases/{slug}/authorise', [CaseController::class, 'escalationPreview'])->name('cases.escalate.preview');
    Route::post('/cases/{slug}/authorise', [CaseController::class, 'escalationAuthorise'])->name('cases.escalate.authorise');
    Route::post('/cases/{slug}/hold', [CaseController::class, 'hold'])->name('cases.hold');
    Route::post('/cases/{slug}/resolve', [CaseController::class, 'resolve'])->name('cases.resolve');
    Route::post('/cases/{slug}/abandon', [CaseController::class, 'abandon'])->name('cases.abandon');
    // D14 — set the cosmetic exhausted_stance label (escalation_exhausted only).
    Route::post('/cases/{slug}/stance', [CaseController::class, 'setStance'])->name('cases.stance');

    // Auth-only member pages
    Route::prefix('members')->name('members.')->group(function () {
        Route::get('/tenantandlandlord', fn() => view('members.tenantandlandlord'))->name('tenantandlandlord');
        Route::get('/thought-experiment', fn() => view('members.thought-experiment'))->name('thought-experiment');
        Route::get('/equity-conversation', fn() => view('members.equity-conversation'))->name('equity-conversation');
        // D14 — signposting page reached from the exhausted case + notice.
        // Members-wall (auth+verified), deliberately NOT in public nav.
        // Content is a solicitor-deferred stub.
        Route::get('/escalation-routes', fn() => view('members.escalation-routes'))->name('escalation-routes');
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

// Admin routes — gated by the `admin` middleware (User::is_admin boolean).
// No id or environment check; access is purely the is_admin column.
Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::get('/page-views', [AdminController::class, 'pageViews'])->name('page-views');
    Route::get('/contact-messages', [AdminController::class, 'contactMessages'])->name('contact-messages');
    Route::get('/contact-messages/{contactMessage}', [AdminController::class, 'contactMessageShow'])->name('contact-messages.show');
    Route::post('/contact-messages/{contactMessage}/reply', [AdminController::class, 'contactMessageReply'])->name('contact-messages.reply');

    // D16 / Surface A — letter template editor (edit -> preview -> confirm).
    Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
    Route::get('/templates/{letterTemplate}/edit', [TemplateController::class, 'edit'])->name('templates.edit');
    Route::post('/templates/{letterTemplate}/preview', [TemplateController::class, 'preview'])->name('templates.preview');
    Route::put('/templates/{letterTemplate}', [TemplateController::class, 'update'])->name('templates.update');

    // D16 / Surface B — settings editor (values only; no create/delete).
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');

    // D16 / Surface C — read-only case oversight (no actions; view only).
    Route::get('/cases', [CaseOversightController::class, 'index'])->name('cases.index');
    Route::get('/cases/{case}', [CaseOversightController::class, 'show'])->name('cases.show');
});

Route::post('/webhooks/mailgun/inbound', MailgunInboundController::class)
    ->middleware('verify.mailgun.signature')
    ->name('webhooks.mailgun.inbound');

// D12 — magic-link sign-in for tenant inbox arrivals. Public route
// (no auth middleware); the signed-URL signature and the single-use
// + expiry checks inside the controller are the auth boundary.
Route::get('/magic-link/{token}', [\App\Http\Controllers\MagicLinkController::class, 'consume'])
    ->middleware('signed')
    ->name('magic-link.consume');

require __DIR__.'/auth.php';
