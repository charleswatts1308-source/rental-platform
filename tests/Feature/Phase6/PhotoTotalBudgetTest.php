<?php

use App\Http\Controllers\CaseController;
use App\Models\Property;
use App\Models\RepairCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * #58 — the client-side photo check summed nothing.
 *
 * The per-file limit was never the binding constraint on a multi-photo
 * selection: PHP refuses a multipart body over post_max_size BEFORE any
 * validation runs, so the tenant loses the whole submission and the
 * application never learns it happened.
 *
 * The margin was held by arithmetic rather than design — a ceiling of 3
 * and a 4MB per-file cap against a 16M post_max_size. Neither number is
 * set or watched by this application, and post_max_size has already been
 * changed once mid-project, in the hosting panel, without the app
 * knowing.
 *
 * The test below is the part that removes the luck: it fails if the
 * worst-case selection stops fitting.
 */

function invokePrivate(object $object, string $method): mixed
{
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke($object);
}

it('#58 — the worst-case selection still fits inside post_max_size', function () {
    $controller = app(CaseController::class);

    $budget = invokePrivate($controller, 'effectivePhotoTotalBytes');

    if ($budget === 0) {
        expect(true)->toBeTrue(); // post_max_size unlimited: nothing to guard.

        return;
    }

    $perFile = invokePrivate($controller, 'effectivePhotoMaxBytes');

    // 3 is the hard clamp in photoCeiling(), not the shipped default. The
    // shipped default is 1, but the ceiling is the FEATURE, so the
    // invariant has to hold at the top of the range an admin can set.
    $worstCase = 3 * $perFile;

    expect($worstCase)->toBeLessThanOrEqual(
        $budget,
        'Three photos at the advertised per-file limit no longer fit inside '
        .'post_max_size. Either the hosting limit dropped or the per-file cap '
        .'rose. The form now refuses such a selection rather than losing it to '
        .'a 413, but the advertised ceiling is promising more than the box takes.'
    );
});

it('#58 — the create form carries the total budget so the script can enforce it', function () {
    Storage::fake('local');

    RepairCategory::factory()->create(['key' => 'damp_mould', 'active' => true]);

    $tenant = User::factory()->create();
    Property::factory()->create(['registered_by_user_id' => $tenant->id]);

    allowPhotoCeiling(3);

    $response = $this->actingAs($tenant)->get('/cases/create');

    $response->assertOk();
    $response->assertSee('data-photo-total-max-bytes', false);
});

it('#58 — an unreadable or unlimited post_max_size yields no total limit, not a zero one', function () {
    $controller = app(CaseController::class);

    // A budget of 0 is the documented "no total check" signal, and the
    // script treats it that way. What must never happen is a SMALL
    // positive budget appearing from nowhere and refusing every photo.
    $budget = invokePrivate($controller, 'effectivePhotoTotalBytes');

    expect($budget === 0 || $budget > 1024 * 1024)->toBeTrue();
});
