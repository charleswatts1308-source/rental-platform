<?php

namespace App\Services;

use App\Models\LetterTemplate;

/**
 * Renders a LetterTemplate row into final subject + body strings by
 * substituting `{{token}}` placeholders against a fixed whitelist of
 * variables.
 *
 * Deliberately NOT Blade and not any engine capable of executing PHP.
 * Template bodies are data edited via phpMyAdmin (and later an admin
 * CRUD); they must never be a code execution vector. Compromise of an
 * admin account must not become RCE.
 *
 * Unknown tokens are rendered AS-IS rather than silently dropped — a
 * misspelled `{{tennant_name}}` shows up greppably in a test send,
 * which is what we want until the Phase 5 admin preview lands.
 */
class LetterTemplateRenderer
{
    /**
     * Fixed whitelist of substitutable variables. Anything not on this
     * list passes through to the rendered output as the literal
     * `{{token}}` text — see class docblock for why.
     *
     * `magic_link` (D12) is rendered into tenant-bound mail by the
     * caller; landlord-bound mail callers don't pass it, so the
     * `{{magic_link}}` placeholder (when absent from a template body)
     * is simply not referenced. When the placeholder IS present and
     * the var is null/missing, it renders as empty string (see
     * `substitute`).
     */
    public const WHITELIST = [
        'tenant_name',
        'landlord_name',
        'case_reference',
        'property_address',
        'issue_description',
        'deadline_date',
        'response_days',
        'notice_number',
        'magic_link',
    ];

    /**
     * D9 — header block injected at the top of every rendered body.
     * Carries property address + case reference + original
     * description, so every system-rendered outbound email is
     * self-contained. Landlord-facing letters and tenant-facing
     * notifications both get it.
     *
     * Templates store body content only (no <doctype>, no <html>,
     * no <body> wrapper). The renderer wraps with a minimal
     * envelope and prepends this block inside.
     *
     * @param  array<string, string|int|null>  $vars
     * @return array{subject: string, body: string}
     */
    public function render(LetterTemplate $template, array $vars): array
    {
        $renderedSubject = $this->substitute($template->subject, $vars);
        $renderedBody = $this->substitute($template->body, $vars);

        // ui_copy templates (e.g. the create-case authorisation
        // wording per D13) are rendered as bare HTML fragments — they
        // go onto a web page, not into an outbound email. Skip the
        // header block + envelope wrap; just hand back the rendered
        // string.
        if ($template->type === 'ui_copy') {
            return [
                'subject' => $renderedSubject,
                'body' => $renderedBody,
            ];
        }

        return [
            'subject' => $renderedSubject,
            'body' => $this->wrapWithHeader($renderedBody, $vars),
        ];
    }

    /**
     * Render a free-form body (no template row) with the same
     * placeholder substitution + D9 header wrap. Used by tenant
     * replies — the body is the tenant's verbatim text, not a
     * template, but evidential framing (header block) is
     * required (D9).
     *
     * @param  array<string, string|int|null>  $vars
     * @return array{subject: string, body: string}
     */
    public function renderFreeForm(string $body, string $subject, array $vars): array
    {
        return [
            'subject' => $this->substitute($subject, $vars),
            'body' => $this->wrapWithHeader($this->substitute($body, $vars), $vars),
        ];
    }

    /**
     * Wrap the template body in a minimal HTML envelope with the
     * D9 header block at the top of <body>.
     *
     * @param  array<string, string|int|null>  $vars
     */
    private function wrapWithHeader(string $body, array $vars): string
    {
        $header = $this->buildHeaderBlock($vars);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
</head>
<body style="font-family: Arial, sans-serif; font-size: 14px; color: #222; line-height: 1.5;">
{$header}
{$body}
</body>
</html>
HTML;
    }

    /**
     * @param  array<string, string|int|null>  $vars
     */
    private function buildHeaderBlock(array $vars): string
    {
        $address = (string) ($vars['property_address'] ?? '');
        $reference = (string) ($vars['case_reference'] ?? '');
        $description = (string) ($vars['issue_description'] ?? '');

        return <<<HTML
<div style="background: #f4f4f4; border-left: 4px solid #888; padding: 12px 16px; margin-bottom: 20px; font-size: 13px;">
  <div><strong>Property:</strong> {$address}</div>
  <div><strong>Case reference:</strong> {$reference}</div>
  <div style="margin-top: 8px;"><strong>Issue:</strong> {$description}</div>
</div>
HTML;
    }

    /**
     * @param  array<string, string|int|null>  $vars
     */
    private function substitute(string $text, array $vars): string
    {
        return preg_replace_callback(
            '/\{\{\s*([a-z_][a-z0-9_]*)\s*\}\}/i',
            function (array $match) use ($vars): string {
                $name = $match[1];

                // Non-whitelist tokens stay visible — a misspelled
                // `{{tennant_name}}` survives to a test send rather than
                // silently vanishing.
                if (! in_array($name, self::WHITELIST, true)) {
                    return $match[0];
                }

                // Whitelist tokens always substitute. Null/missing values
                // collapse to empty string — fallback wording (e.g. "Sir
                // or Madam" when the landlord name is unknown) is the
                // caller's responsibility, computed before substitution.
                return (string) ($vars[$name] ?? '');
            },
            $text
        );
    }
}
