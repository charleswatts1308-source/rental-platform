<?php

use App\Enums\CaseStatus;
use App\Http\Controllers\Admin\CaseOversightController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\MagicLinkController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\PropertyLandlordContactController;
use App\Http\Controllers\Webhooks\MailgunDeliveryEventController;
use App\Http\Controllers\Webhooks\MailgunInboundController;
use App\Models\Property;
use App\Models\RepairCase;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

Route::get('/about', function () {
    return view('about');
});

// Landing page for a landlord who has just received a repair notice and is
// asking "who are these people?". Reached from the email footer, but named
// and linked in the nav so it can be typed directly — a suspicious reader
// should not have to click a link in the message they are suspicious of.
Route::get('/landlords', fn () => view('landlords'))->name('landlords');

// PWA offline fallback — served by the service worker when a navigation fails.
Route::get('/offline', fn () => view('offline'))->name('offline');

Route::get('/privacy', function () {
    return view('privacy');
});

Route::get('/cookies', function () {
    return view('cookies');
});

// welcome-3 (the "Erin" homepage) retired 18 Jul 2026 — archived at
// /content-archive/homepage-erin-18-july-2026.
Route::get('/', function () {
    return view('welcome-4');
});

// Dashboard is the post-verification landing page and the site's hub: it
// carries the "what do I do next" signposting a new tenant needs, since the
// property-then-case ordering is otherwise left to be inferred.
Route::get('/dashboard', function (Request $request) {
    $userId = $request->user()->id;

    $propertyCount = Property::where('registered_by_user_id', $userId)->count();

    $cases = RepairCase::query()
        ->where('tenant_user_id', $userId)
        ->with(['property', 'category'])
        ->orderByDesc('opened_at')
        ->get();

    return view('dashboard', [
        'propertyCount' => $propertyCount,
        'cases' => $cases,
        // Cases where the ball is with the tenant — these are the ones the
        // user must act on, so they lead the page.
        'needsAttention' => $cases->where('status', CaseStatus::AwaitingTenantReview),
    ]);
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

    // The landlord contact is a property of the PROPERTY, versioned.
    // Correcting it here is the whole of snag #24 — separate from the
    // address edit above because a correction inserts a new version and
    // writes a case event, rather than overwriting a row.
    Route::get('/properties/{property}/landlord', [PropertyLandlordContactController::class, 'edit'])->name('properties.contact.edit');
    Route::patch('/properties/{property}/landlord', [PropertyLandlordContactController::class, 'update'])->name('properties.contact.update');

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
        // D14 — signposting page reached from the exhausted case + notice.
        // Members-wall (auth+verified), deliberately NOT in public nav.
        // Content is a solicitor-deferred stub.
        Route::get('/escalation-routes', fn () => view('members.escalation-routes'))->name('escalation-routes');
    });
});

// Public member pages
Route::prefix('members')->name('members.')->group(function () {
    // How It Works — single public page (The Process + Whoever You Are +
    // Whatever Property + Where This Can Lead as stacked sections).
    Route::get('/how-it-works', fn () => view('members.how-it-works'))->name('how-it-works');
});

// Content archive — dev box only. Retired pages live under
// resources/views/content-archive/. This single catch-all makes any Blade
// view dropped into that folder instantly runnable at
// /content-archive/<filename>, with an index listing all of them. Gated to
// `local`, so production never exposes these pages.
if (app()->environment('local')) {
    Route::get('/content-archive', function () {
        // Newest first — the archive is a reference shelf and the page you
        // just retired belongs at the top.
        //
        // Date preference: the trailing date in the filename (the naming
        // convention, e.g. about-us-11-july-2026) BEATS file mtime, because
        // mtime records when the content was last EDITED, not when it was
        // retired. Archiving copies preserve the original timestamp, so
        // pages written in April and archived in July were sorting as April.
        // Filename dates also survive a fresh git clone, which rewrites every
        // mtime. Undated legacy pages fall back to mtime and settle wherever
        // they land — fine for a reference shelf.
        $pages = collect(File::files(resource_path('views/content-archive')))
            ->map(function ($f) {
                $name = str_replace('.blade.php', '', $f->getFilename());

                $dated = null;
                if (preg_match('/-(\d{1,2}-[a-z]+-\d{4})$/i', $name, $m)) {
                    // strtotime handles "11-july-2026" once the hyphens go.
                    $parsed = strtotime(str_replace('-', ' ', $m[1]));
                    $dated = $parsed ? Carbon::createFromTimestamp($parsed) : null;
                }

                return [
                    'name' => $name,
                    'date' => $dated ?? Carbon::createFromTimestamp($f->getMTime()),
                    // Distinguishes "retired on this date" from "last edited
                    // on this date" in the listing — an mtime row is a guess.
                    'dated' => $dated !== null,
                ];
            })
            ->sortByDesc('date')
            ->values();

        return view('content-archive-index', ['pages' => $pages]);
    })->name('content-archive.index');

    Route::get('/content-archive/{page}', function (string $page) {
        abort_unless(view()->exists("content-archive.$page"), 404);

        return view("content-archive.$page");
    })->where('page', '[A-Za-z0-9\-_]+')->name('content-archive.show');
}

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

// #25 — delivery events. A SECOND route, not a widening of the one above:
// events nest the signature fields and arrive as JSON, where inbound
// routing carries them flat and form-encoded.
Route::post('/webhooks/mailgun/events', MailgunDeliveryEventController::class)
    ->middleware('verify.mailgun.event.signature')
    ->name('webhooks.mailgun.events');

// D12 — magic-link sign-in for tenant inbox arrivals. Public route
// (no auth middleware); the signed-URL signature and the single-use
// + expiry checks inside the controller are the auth boundary.
Route::get('/magic-link/{token}', [MagicLinkController::class, 'consume'])
    ->middleware('signed')
    ->name('magic-link.consume');

require __DIR__.'/auth.php';
