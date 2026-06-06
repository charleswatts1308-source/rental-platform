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
    ];

    /**
     * @param  array<string, string|int|null>  $vars
     * @return array{subject: string, body: string}
     */
    public function render(LetterTemplate $template, array $vars): array
    {
        return [
            'subject' => $this->substitute($template->subject, $vars),
            'body' => $this->substitute($template->body, $vars),
        ];
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
