<?php

use App\Models\LetterTemplate;
use App\Services\LetterTemplateRenderer;

it('substitutes whitelisted placeholders in both subject and body', function () {
    $template = new LetterTemplate([
        'subject' => 'Notice {{notice_number}} — case {{case_reference}}',
        'body' => 'Dear {{landlord_name}}, the property is {{property_address}}.',
    ]);

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
    $template = new LetterTemplate([
        'subject' => 'Hello {{tennant_name}}',
        'body' => 'Body {{frobnicate}} more body.',
    ]);

    $rendered = (new LetterTemplateRenderer)->render($template, [
        'tennant_name' => 'Should be ignored — wrong spelling',
    ]);

    expect($rendered['subject'])->toBe('Hello {{tennant_name}}');
    expect($rendered['body'])->toBe('Body {{frobnicate}} more body.');
});

it('renders null whitelisted values as empty string — fallbacks are the caller\'s responsibility', function () {
    $template = new LetterTemplate([
        'subject' => 'X',
        'body' => 'Dear {{landlord_name}}, ref {{case_reference}}.',
    ]);

    $rendered = (new LetterTemplateRenderer)->render($template, [
        'landlord_name' => null,
        'case_reference' => 'AB12CD',
    ]);

    expect($rendered['body'])->toBe('Dear , ref AB12CD.');
});

it('does not execute PHP — Blade-style directives stay as plain text', function () {
    $template = new LetterTemplate([
        'subject' => 'X',
        'body' => "@php echo 'pwned'; @endphp and {{ \$evil = 1 }} and <?php echo 1; ?>",
    ]);

    $rendered = (new LetterTemplateRenderer)->render($template, []);

    expect($rendered['body'])->toContain("@php echo 'pwned'; @endphp");
    expect($rendered['body'])->toContain('<?php echo 1; ?>');
});

it('tolerates whitespace inside braces — `{{  notice_number  }}` substitutes too', function () {
    $template = new LetterTemplate([
        'subject' => '{{  notice_number  }}',
        'body' => '{{   case_reference   }}',
    ]);

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
});
