<?php

use App\Mail\CaseNotice;
use App\Models\CaseMessage;
use App\Models\LandlordContact;
use App\Models\Property;
use App\Models\RepairCase;
use App\Models\RepairCategory;
use App\Models\ReplyToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeCaseFor(int $stage, ?string $tokenValue = null): array
{
    $tenant = User::factory()->create(['name' => 'Alex Smith']);
    $property = Property::factory()->create([
        'address_line1' => '12 Example Street',
        'address_line2' => 'Flat 3',
        'city' => 'London',
        'postcode' => 'SW1A 1AA',
    ]);
    $contact = LandlordContact::factory()->create(['name' => 'Mr Landlord']);
    $category = RepairCategory::factory()->create(['label' => 'Damp and mould']);
    $case = RepairCase::factory()->create([
        'tenant_user_id' => $tenant->id,
        'property_id' => $property->id,
        'landlord_contact_id' => $contact->id,
        'category_key' => $category->key,
    ]);
    $token = ReplyToken::factory()->create(array_filter([
        'case_id' => $case->id,
        'token' => $tokenValue,
    ]));
    $message = CaseMessage::factory()->create([
        'case_id' => $case->id,
        'stage_at_send' => $stage,
        'template_key' => match ($stage) {
            1 => 'stage_1_initial_notice',
            2 => 'stage_2_follow_up',
            3 => 'stage_3_formal_warning',
            4 => 'stage_4_pre_action',
        },
        'tenant_statement' => 'Damp patch on the bedroom ceiling, getting worse since last month.',
    ]);

    return compact('case', 'message', 'token');
}

it('renders the stage 1 initial notice template', function () {
    ['case' => $case, 'message' => $message, 'token' => $token] = makeCaseFor(1);

    $rendered = (new CaseNotice($case, $message, $token))->render();

    expect($rendered)->toContain('formally notify');
    expect($rendered)->toContain('12 Example Street');
    expect($rendered)->toContain('SW1A 1AA');
    expect($rendered)->toContain('Damp and mould');
    expect($rendered)->toContain('section 11 of the Landlord and Tenant Act 1985');
    expect($rendered)->toContain('Alex');
    expect($rendered)->toContain('Damp patch on the bedroom ceiling');
});

it('renders the stage 2 follow-up template', function () {
    ['case' => $case, 'message' => $message, 'token' => $token] = makeCaseFor(2);

    $rendered = (new CaseNotice($case, $message, $token))->render();

    expect($rendered)->toContain('follow up');
    expect($rendered)->toContain('section 11 of the Landlord and Tenant Act 1985');
});

it('renders the stage 3 formal warning template', function () {
    ['case' => $case, 'message' => $message, 'token' => $token] = makeCaseFor(3);

    $rendered = (new CaseNotice($case, $message, $token))->render();

    expect($rendered)->toContain('formal warning');
    expect($rendered)->toContain('Pre-Action Protocol');
});

it('renders the stage 4 pre-action template', function () {
    ['case' => $case, 'message' => $message, 'token' => $token] = makeCaseFor(4);

    $rendered = (new CaseNotice($case, $message, $token))->render();

    expect($rendered)->toContain('pre-action letter');
    expect($rendered)->toContain('Pre-Action Protocol for Housing Conditions');
    expect($rendered)->toContain('Housing Act 2004');
});

it('sets the From envelope to cases@mg.renters.rent with the tenant first name', function () {
    ['case' => $case, 'message' => $message, 'token' => $token] = makeCaseFor(1);

    $envelope = (new CaseNotice($case, $message, $token))->envelope();
    $from = $envelope->from;

    expect($from->address)->toBe(config('services.mailgun.cases_from_address'));
    expect($from->name)->toBe('Alex via renters.rent');
});

it('sets the Reply-To envelope to the token-bound inbox address', function () {
    ['case' => $case, 'message' => $message, 'token' => $token] = makeCaseFor(1, 'abcdefghij1234567890');

    $envelope = (new CaseNotice($case, $message, $token))->envelope();

    expect($envelope->replyTo)->toHaveCount(1);
    expect($envelope->replyTo[0]->address)->toBe('abcdefghij1234567890@'.config('services.mailgun.inbound_domain'));
});

it('uses a stage-appropriate subject line', function () {
    ['case' => $c1, 'message' => $m1, 'token' => $t1] = makeCaseFor(1);
    ['case' => $c2, 'message' => $m2, 'token' => $t2] = makeCaseFor(2);
    ['case' => $c3, 'message' => $m3, 'token' => $t3] = makeCaseFor(3);
    ['case' => $c4, 'message' => $m4, 'token' => $t4] = makeCaseFor(4);

    expect((new CaseNotice($c1, $m1, $t1))->envelope()->subject)->toContain('Repair issue notification');
    expect((new CaseNotice($c2, $m2, $t2))->envelope()->subject)->toContain('Follow-up');
    expect((new CaseNotice($c3, $m3, $t3))->envelope()->subject)->toContain('Formal warning');
    expect((new CaseNotice($c4, $m4, $t4))->envelope()->subject)->toContain('Pre-action');
});
