<?php

use App\Models\CaseMessage;
use App\Models\LetterTemplate;
use App\Models\LetterTextChangeHistory;
use App\Models\User;
use App\Services\LetterTemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function admin(): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    return $admin;
}

function wakeupTemplate(): LetterTemplate
{
    return LetterTemplate::where('code', 'landlord_wakeup_generic')->firstOrFail();
}

// ---- gate -------------------------------------------------------------

it('forbids a non-admin on every template route', function () {
    $user = User::factory()->create();
    $template = wakeupTemplate();

    $this->actingAs($user)->get('/admin/templates')->assertForbidden();
    $this->actingAs($user)->get("/admin/templates/{$template->id}/edit")->assertForbidden();
    $this->actingAs($user)->post("/admin/templates/{$template->id}/preview", [
        'subject' => 'x', 'body' => 'y',
    ])->assertForbidden();
    $this->actingAs($user)->put("/admin/templates/{$template->id}", [
        'subject' => 'x', 'body' => 'y',
    ])->assertForbidden();
});

// ---- list + edit screens ---------------------------------------------

it('lists templates for an admin', function () {
    $this->actingAs(admin())
        ->get('/admin/templates')
        ->assertOk()
        ->assertSee('landlord_wakeup_generic');
});

it('shows the edit form for an admin', function () {
    $template = wakeupTemplate();

    $this->actingAs(admin())
        ->get("/admin/templates/{$template->id}/edit")
        ->assertOk()
        ->assertSee($template->subject);
});

// ---- A3 mandatory preview --------------------------------------------

it('renders a preview without saving (A3)', function () {
    $template = wakeupTemplate();
    $original = $template->body;

    $this->actingAs(admin())
        ->post("/admin/templates/{$template->id}/preview", [
            'subject' => 'New subject for {{case_reference}}',
            'body' => 'Hello {{tenant_name}}, updated body.',
        ])
        ->assertOk()
        ->assertSee('Confirm and save');

    expect($template->fresh()->body)->toBe($original);
    expect(LetterTextChangeHistory::count())->toBe(0);
});

it('the edit form posts to preview, not straight to update (A3)', function () {
    $template = wakeupTemplate();

    $this->actingAs(admin())
        ->get("/admin/templates/{$template->id}/edit")
        ->assertSee(route('admin.templates.preview', $template), false);
});

// ---- A2 token validation ---------------------------------------------

it('rejects a misspelled placeholder (A2)', function () {
    $template = wakeupTemplate();

    $this->actingAs(admin())
        ->post("/admin/templates/{$template->id}/preview", [
            'subject' => 'ok {{case_reference}}',
            'body' => 'Please fix {{issue_desciption}} now.', // misspelled
        ])
        ->assertSessionHasErrors('body');

    expect(LetterTextChangeHistory::count())->toBe(0);
});

it('rejects malformed placeholder braces (A2)', function () {
    $template = wakeupTemplate();

    $this->actingAs(admin())
        ->post("/admin/templates/{$template->id}/preview", [
            'subject' => 'ok',
            'body' => 'Notice { {notice_number}} is broken.', // malformed
        ])
        ->assertSessionHasErrors('body');
});

it('accepts a clean set of known placeholders (A2)', function () {
    $template = wakeupTemplate();

    $this->actingAs(admin())
        ->post("/admin/templates/{$template->id}/preview", [
            'subject' => 'Notice {{notice_number}} — {{case_reference}}',
            'body' => 'Dear {{landlord_name}}, re {{property_address}}.',
        ])
        ->assertOk()
        ->assertSessionHasNoErrors();
});

// ---- A1 history + commit ---------------------------------------------

it('writes a version-1 history row and updates the template on confirm (A1)', function () {
    $template = wakeupTemplate();
    $beforeSubject = $template->subject;
    $beforeBody = $template->body;
    $admin = admin();

    $this->actingAs($admin)
        ->put("/admin/templates/{$template->id}", [
            'subject' => 'Brand new subject {{case_reference}}',
            'body' => 'Brand new body for {{tenant_name}}.',
        ])
        ->assertRedirect(route('admin.templates.edit', $template));

    $template->refresh();
    expect($template->subject)->toBe('Brand new subject {{case_reference}}');
    expect($template->body)->toBe('Brand new body for {{tenant_name}}.');

    $row = LetterTextChangeHistory::sole();
    expect($row->version)->toBe(1);
    expect($row->letter_template_id)->toBe($template->id);
    expect($row->edited_by_user_id)->toBe($admin->id);
    expect($row->before_subject)->toBe($beforeSubject);
    expect($row->after_subject)->toBe('Brand new subject {{case_reference}}');
    expect($row->before_body)->toBe($beforeBody);
    expect($row->after_body)->toBe('Brand new body for {{tenant_name}}.');
});

it('increments the version on a second edit (A1)', function () {
    $template = wakeupTemplate();
    $admin = admin();

    $this->actingAs($admin)->put("/admin/templates/{$template->id}", [
        'subject' => 'v1 subject', 'body' => 'v1 body',
    ]);
    $this->actingAs($admin)->put("/admin/templates/{$template->id}", [
        'subject' => 'v2 subject', 'body' => 'v2 body',
    ]);

    expect(LetterTextChangeHistory::where('letter_template_id', $template->id)
        ->pluck('version')->sort()->values()->all())->toBe([1, 2]);
});

it('writes no history row for a no-op save', function () {
    $template = wakeupTemplate();

    $this->actingAs(admin())->put("/admin/templates/{$template->id}", [
        'subject' => $template->subject,
        'body' => $template->body,
    ])->assertRedirect(route('admin.templates.edit', $template));

    expect(LetterTextChangeHistory::count())->toBe(0);
});

// ---- invariant: future sends only ------------------------------------

it('leaves already-sent case_messages untouched by a template edit', function () {
    $template = wakeupTemplate();
    $frozenSubject = 'Frozen subject at send time';
    $frozenBody = $template->body; // the body as it was when sent

    $message = CaseMessage::factory()->create([
        'letter_template_id' => $template->id,
        'subject' => $frozenSubject,
        'body_raw' => $frozenBody,
    ]);

    $this->actingAs(admin())->put("/admin/templates/{$template->id}", [
        'subject' => 'Completely rewritten subject {{case_reference}}',
        'body' => 'Completely rewritten body {{tenant_name}}.',
    ]);

    $message->refresh();
    expect($message->subject)->toBe($frozenSubject);
    expect($message->body_raw)->toBe($frozenBody);
});

// ---- #20 dark-mode header block --------------------------------------

it('renders the D9 header block with an explicit text colour (#20)', function () {
    $template = wakeupTemplate();

    $rendered = app(LetterTemplateRenderer::class)->render($template, [
        'property_address' => '1 Example St',
        'case_reference' => 'ABC234',
        'issue_description' => 'Leak',
    ]);

    expect($rendered['body'])->toContain('color: #222');
});
