<?php

use App\Models\LetterTemplate;
use App\Services\LetterTemplateRenderer;

/**
 * Phase 3 — the renderer auto-injects the D9 header block and wraps
 * with a minimal HTML envelope on every non-ui_copy template render.
 * For substitution-only assertions, these tests use `type=ui_copy`,
 * which the renderer recognises as bare-fragment output (rendered
 * onto a web page, not into an outbound email) and skips the wrap.
 *
 * Wrap behaviour is asserted separately at the bottom of this file.
 */
function uiCopyTemplate(string $subject, string $body): LetterTemplate
{
    return new LetterTemplate([
        'type' => 'ui_copy',
        'subject' => $subject,
        'body' => $body,
    ]);
}

it('substitutes whitelisted placeholders in both subject and body', function () {
    $template = uiCopyTemplate(
        'Notice {{notice_number}} — case {{case_reference}}',
        'Dear {{landlord_name}}, the property is {{property_address}}.',
    );

    $rendered = (new LetterTemplateRenderer)->render($template, [
        'notice_number' => 2,
        'case_reference' => 'AB12CD',
        'landlord_name' => 'Ms Smith',
        'property_address' => '12 Example St, SW1A 1AA',
    ]);

    expect($rendered['subject'])->toBe('Notice 2 — case AB12CD');
    expect($rendered['body'])->toBe('Dear Ms Smith, the property is 12 Example St, SW1A 1AA.');
});

it('passes unknown (non-whitelist) tokens through verbatim — misspellings are visible, not silent', function () {
    $template = uiCopyTemplate(
        'Hello {{tennant_name}}',
        'Body {{frobnicate}} more body.',
    );

    $rendered = (new LetterTemplateRenderer)->render($template, [
        'tennant_name' => 'Should be ignored — wrong spelling',
    ]);

    expect($rendered['subject'])->toBe('Hello {{tennant_name}}');
    expect($rendered['body'])->toBe('Body {{frobnicate}} more body.');
});

it('renders null whitelisted values as empty string — fallbacks are the caller\'s responsibility', function () {
    $template = uiCopyTemplate(
        'X',
        'Dear {{landlord_name}}, ref {{case_reference}}.',
    );

    $rendered = (new LetterTemplateRenderer)->render($template, [
        'landlord_name' => null,
        'case_reference' => 'AB12CD',
    ]);

    expect($rendered['body'])->toBe('Dear , ref AB12CD.');
});

it('does not execute PHP — Blade-style directives stay as plain text', function () {
    $template = uiCopyTemplate(
        'X',
        "@php echo 'pwned'; @endphp and {{ \$evil = 1 }} and <?php echo 1; ?>",
    );

    $rendered = (new LetterTemplateRenderer)->render($template, []);

    expect($rendered['body'])->toContain("@php echo 'pwned'; @endphp");
    expect($rendered['body'])->toContain('<?php echo 1; ?>');
});

it('tolerates whitespace inside braces — `{{  notice_number  }}` substitutes too', function () {
    $template = uiCopyTemplate(
        '{{  notice_number  }}',
        '{{   case_reference   }}',
    );

    $rendered = (new LetterTemplateRenderer)->render($template, [
        'notice_number' => 3,
        'case_reference' => 'ZZ99XX',
    ]);

    expect($rendered['subject'])->toBe('3');
    expect($rendered['body'])->toBe('ZZ99XX');
});

it('exposes the whitelist for code that needs to know which keys are supported', function () {
    expect(LetterTemplateRenderer::WHITELIST)->toContain('tenant_name');
    expect(LetterTemplateRenderer::WHITELIST)->toContain('landlord_name');
    expect(LetterTemplateRenderer::WHITELIST)->toContain('case_reference');
    expect(LetterTemplateRenderer::WHITELIST)->toContain('property_address');
    expect(LetterTemplateRenderer::WHITELIST)->toContain('issue_description');
    expect(LetterTemplateRenderer::WHITELIST)->toContain('deadline_date');
    expect(LetterTemplateRenderer::WHITELIST)->toContain('response_days');
    expect(LetterTemplateRenderer::WHITELIST)->toContain('notice_number');
    // Phase 3 D12 — magic_link added to the whitelist for tenant-bound mail.
    expect(LetterTemplateRenderer::WHITELIST)->toContain('magic_link');
});

it('wraps non-ui_copy templates with the D9 header block and HTML envelope', function () {
    $template = new LetterTemplate([
        'type' => 'escalation',
        'subject' => 'X',
        'body' => '<p>The letter body.</p>',
    ]);

    $rendered = (new LetterTemplateRenderer)->render($template, [
        'property_address' => '12 Example St',
        'case_reference' => 'AB12CD',
        'issue_description' => 'Boiler not working.',
    ]);

    expect($rendered['body'])->toContain('<!DOCTYPE html>');
    expect($rendered['body'])->toContain('12 Example St');
    expect($rendered['body'])->toContain('AB12CD');
    expect($rendered['body'])->toContain('Boiler not working.');
    expect($rendered['body'])->toContain('<p>The letter body.</p>');
    // The header block precedes the body.
    expect(strpos($rendered['body'], '12 Example St'))
        ->toBeLessThan(strpos($rendered['body'], '<p>The letter body.</p>'));
});

it('skips the header wrap for ui_copy templates — they render as bare fragments', function () {
    $template = new LetterTemplate([
        'type' => 'ui_copy',
        'subject' => 'X',
        'body' => '<p>Authorisation copy.</p>',
    ]);

    $rendered = (new LetterTemplateRenderer)->render($template, [
        'property_address' => '12 Example St',
        'case_reference' => 'AB12CD',
        'issue_description' => 'Boiler not working.',
    ]);

    expect($rendered['body'])->toBe('<p>Authorisation copy.</p>');
    expect($rendered['body'])->not->toContain('<!DOCTYPE html>');
});

it('renders free-form bodies via renderFreeForm with the same header wrap', function () {
    $rendered = (new LetterTemplateRenderer)->renderFreeForm(
        'Hello, this is my reply with {{tenant_name}}.',
        'Reply on {{case_reference}}',
        [
            'tenant_name' => 'Alice',
            'case_reference' => 'AB12CD',
            'property_address' => '12 Example St',
            'issue_description' => 'Boiler not working.',
        ],
    );

    expect($rendered['subject'])->toBe('Reply on AB12CD');
    expect($rendered['body'])->toContain('Hello, this is my reply with Alice.');
    expect($rendered['body'])->toContain('<!DOCTYPE html>');
    expect($rendered['body'])->toContain('12 Example St');
});
